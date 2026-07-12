<?php

namespace App\Services\Inventory;

use App\Models\InventoryModuleConfig;
use App\Models\InventoryOrder;
use App\Models\InventoryOrderLine;
use App\Models\InventoryStockLevel;
use App\Models\InventoryStockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferApproval;
use App\Models\StockTransferLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryStockTransferService
{
    public function generateReference(int $businessId): string
    {
        $prefix = 'ST-'.now()->format('Ymd');
        $count = StockTransfer::query()
            ->where('business_id', $businessId)
            ->where('reference', 'like', $prefix.'%')
            ->count();

        return $prefix.'-'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<int, array{item_id: int, quantity_suom: float}>  $lines
     */
    public function createDraft(
        int $businessId,
        int $fromStoreId,
        int $toStoreId,
        array $lines,
        User $user,
        ?string $notes = null
    ): StockTransfer {
        if ($fromStoreId === $toStoreId) {
            throw ValidationException::withMessages([
                'to_store_id' => 'Dispatch and receiving stores must be different.',
            ]);
        }

        $fromStore = \App\Models\Store::query()
            ->where('business_id', $businessId)
            ->find($fromStoreId);
        $toStore = \App\Models\Store::query()
            ->where('business_id', $businessId)
            ->find($toStoreId);

        if (! $fromStore || ! $toStore) {
            throw ValidationException::withMessages([
                'from_store_id' => 'The selected stores are invalid for this organisation.',
            ]);
        }

        if (! $fromStore->canTransferStockTo($toStore)) {
            throw ValidationException::withMessages([
                'to_store_id' => 'Child stores cannot transfer stock directly to other child stores. Move stock through the parent distribution store first.',
            ]);
        }

        return DB::transaction(function () use ($businessId, $fromStoreId, $toStoreId, $lines, $user, $notes) {
            $transfer = StockTransfer::create([
                'business_id' => $businessId,
                'reference' => $this->generateReference($businessId),
                'status' => StockTransfer::STATUS_DRAFT,
                'from_store_id' => $fromStoreId,
                'to_store_id' => $toStoreId,
                'notes' => $notes,
                'requested_by_user_id' => $user->id,
            ]);

            foreach ($lines as $line) {
                $qty = (float) ($line['quantity_suom'] ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                StockTransferLine::create([
                    'stock_transfer_id' => $transfer->id,
                    'item_id' => (int) $line['item_id'],
                    'requested_quantity_suom' => $qty,
                    'approved_quantity_suom' => $qty,
                    'received_quantity_suom' => $qty,
                ]);
            }

            if ($transfer->lines()->count() === 0) {
                throw ValidationException::withMessages([
                    'lines' => 'Add at least one item with quantity greater than zero.',
                ]);
            }

            return $transfer->fresh(['lines.item', 'fromStore', 'toStore']);
        });
    }

    public function createFromInternalOrder(InventoryOrder $order, User $user): StockTransfer
    {
        $order->loadMissing(['lines.item', 'sourceStore', 'store']);

        if (! $order->canCreateStockTransfer()) {
            throw ValidationException::withMessages([
                'status' => 'This internal order cannot start a stock transfer in its current state.',
            ]);
        }

        if (! $order->source_store_id || ! $order->store_id) {
            throw ValidationException::withMessages([
                'stores' => 'Internal order is missing supplying or receiving store.',
            ]);
        }

        $lines = $order->lines
            ->map(function ($line) {
                $ordered = (float) ($line->order_quantity_suom ?? $line->suggested_quantity_suom ?? 0);
                $alreadyReceived = (float) ($line->received_quantity_suom ?? 0);
                $remaining = max(0, round($ordered - $alreadyReceived, 4));

                return [
                    'item_id' => (int) $line->item_id,
                    'quantity_suom' => $remaining,
                ];
            })
            ->filter(fn (array $line) => $line['quantity_suom'] > 0)
            ->values()
            ->all();

        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => 'Internal order has no remaining quantities to transfer.',
            ]);
        }

        return DB::transaction(function () use ($order, $user, $lines) {
            $transfer = StockTransfer::create([
                'business_id' => $order->business_id,
                'inventory_order_id' => $order->id,
                'reference' => $this->generateReference((int) $order->business_id),
                'status' => StockTransfer::STATUS_DRAFT,
                'from_store_id' => $order->source_store_id,
                'to_store_id' => $order->store_id,
                'notes' => 'From internal order '.$order->order_number,
                'requested_by_user_id' => $user->id,
            ]);

            foreach ($lines as $line) {
                StockTransferLine::create([
                    'stock_transfer_id' => $transfer->id,
                    'item_id' => $line['item_id'],
                    'requested_quantity_suom' => $line['quantity_suom'],
                    'approved_quantity_suom' => $line['quantity_suom'],
                    'received_quantity_suom' => $line['quantity_suom'],
                ]);
            }

            return $transfer->fresh(['lines.item', 'fromStore', 'toStore', 'inventoryOrder']);
        });
    }

    public function submit(StockTransfer $transfer, User $user): StockTransfer
    {
        if (! $transfer->isDraft()) {
            throw ValidationException::withMessages(['status' => 'Only draft transfers can be submitted.']);
        }

        if ($transfer->lines()->count() < 1) {
            throw ValidationException::withMessages([
                'lines' => 'Add at least one item with quantity greater than zero.',
            ]);
        }

        $this->assertTransferRouteAllowed($transfer);

        $approvers = $this->configuredApprovers((int) $transfer->business_id);

        if ($approvers->isEmpty()) {
            throw ValidationException::withMessages([
                'approvers' => 'No goods receive note approvers are configured. Set them under Inventory → Goods receive note approvers.',
            ]);
        }

        $firstApprovalOrder = (int) $approvers->min('approval_order');

        return DB::transaction(function () use ($transfer, $user, $approvers, $firstApprovalOrder) {
            $transfer->update([
                'status' => StockTransfer::STATUS_PENDING,
                'current_approval_order' => $firstApprovalOrder,
                'requested_at' => now(),
                'requested_by_user_id' => $user->id,
            ]);

            foreach ($approvers as $approver) {
                StockTransferApproval::create([
                    'stock_transfer_id' => $transfer->id,
                    'approver_user_id' => $approver->user_id,
                    'approval_order' => $approver->approval_order,
                    'status' => StockTransferApproval::STATUS_PENDING,
                ]);
            }

            return $transfer->fresh(['lines.item', 'fromStore', 'toStore', 'approvals.approver']);
        });
    }

    public function approve(StockTransfer $transfer, User $user, ?string $comment = null): StockTransfer
    {
        if (! $transfer->isPending()) {
            throw ValidationException::withMessages(['status' => 'This transfer is not awaiting approval.']);
        }

        $this->assertTransferRouteAllowed($transfer);

        $pending = $this->currentPendingApproval($transfer);

        if (! $pending) {
            throw ValidationException::withMessages([
                'status' => 'No pending approval step found.',
            ]);
        }

        if ((int) $pending->approver_user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'approver' => 'You are not the approver for the current step.',
            ]);
        }

        return DB::transaction(function () use ($transfer, $user, $pending, $comment) {
            $pending->update([
                'status' => StockTransferApproval::STATUS_APPROVED,
                'comment' => $comment,
                'acted_at' => now(),
            ]);

            $nextPending = $transfer->approvals()
                ->where('status', StockTransferApproval::STATUS_PENDING)
                ->orderBy('approval_order')
                ->first();

            if ($nextPending) {
                $transfer->update([
                    'current_approval_order' => $nextPending->approval_order,
                ]);

                return $transfer->fresh(['lines.item', 'fromStore', 'toStore', 'approvals.approver']);
            }

            $this->finalizeApproval($transfer, $user);

            return $transfer->fresh(['lines.item', 'fromStore', 'toStore', 'approvals.approver']);
        });
    }

    public function receive(StockTransfer $transfer, User $user): StockTransfer
    {
        if (! $transfer->isApproved()) {
            throw ValidationException::withMessages(['status' => 'Transfer must be approved before receiving.']);
        }

        return DB::transaction(function () use ($transfer, $user) {
            $transfer->load('lines.item');

            foreach ($transfer->lines as $line) {
                $qty = (float) $line->received_quantity_suom;

                if ($qty <= 0) {
                    continue;
                }

                $this->completeInTransitReceipt(
                    (int) $transfer->business_id,
                    (int) $transfer->from_store_id,
                    (int) $transfer->to_store_id,
                    (int) $line->item_id,
                    $qty,
                    $transfer,
                    $user->id
                );
            }

            $transfer->update([
                'status' => StockTransfer::STATUS_RECEIVED,
                'received_at' => now(),
                'received_by_user_id' => $user->id,
            ]);

            $this->finalizeLinkedInternalOrder($transfer);

            InventoryProcurementAudit::log(
                'transfer_received',
                $transfer,
                'Transfer '.$transfer->reference.' received — in-transit cleared, destination stock updated',
                why: 'Requesting store confirmed receipt',
            );

            return $transfer->fresh(['lines.item', 'fromStore', 'toStore', 'inventoryOrder']);
        });
    }

    private function finalizeLinkedInternalOrder(StockTransfer $transfer): void
    {
        if (! $transfer->inventory_order_id) {
            return;
        }

        $order = InventoryOrder::query()
            ->with('lines')
            ->find($transfer->inventory_order_id);

        if (! $order || ! $order->isInternal()) {
            return;
        }

        $receivedByItem = $transfer->lines
            ->groupBy('item_id')
            ->map(fn ($lines) => $lines->sum(fn ($line) => (float) $line->received_quantity_suom));

        foreach ($order->lines as $orderLine) {
            $received = (float) ($receivedByItem[$orderLine->item_id] ?? 0);

            if ($received <= 0) {
                continue;
            }

            $orderLine->update([
                'received_quantity_suom' => round((float) ($orderLine->received_quantity_suom ?? 0) + $received, 4),
            ]);
        }

        $order->load('lines');

        $allReceived = $order->lines->every(function (InventoryOrderLine $line) {
            $ordered = (float) ($line->order_quantity_suom ?? 0);

            if ($ordered <= 0) {
                return true;
            }

            return (float) ($line->received_quantity_suom ?? 0) >= $ordered - 0.0001;
        });

        $order->update([
            'status' => $allReceived
                ? InventoryOrder::STATUS_FULFILLED
                : InventoryOrder::STATUS_PARTIALLY_RECEIVED,
        ]);
    }

    public function reject(StockTransfer $transfer, User $user, string $reason): StockTransfer
    {
        if (! $transfer->isPending()) {
            throw ValidationException::withMessages(['status' => 'Only pending transfers can be rejected.']);
        }

        $pending = $this->currentPendingApproval($transfer);

        if (! $pending || (int) $pending->approver_user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'approver' => 'You are not the approver for the current step.',
            ]);
        }

        return DB::transaction(function () use ($transfer, $user, $pending, $reason) {
            $pending->update([
                'status' => StockTransferApproval::STATUS_REJECTED,
                'comment' => $reason,
                'acted_at' => now(),
            ]);

            $transfer->update([
                'status' => StockTransfer::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'current_approval_order' => null,
                'approved_by_user_id' => $user->id,
            ]);

            return $transfer->fresh(['lines.item', 'fromStore', 'toStore', 'approvals.approver']);
        });
    }

    public function userCanApprove(StockTransfer $transfer, User $user): bool
    {
        if (! $transfer->isPending()) {
            return false;
        }

        $pending = $this->currentPendingApproval($transfer);

        return $pending && (int) $pending->approver_user_id === (int) $user->id;
    }

    public function updateLine(StockTransferLine $line, float $approvedQty, float $receivedQty): StockTransferLine
    {
        if (! $line->transfer->isDraft() && ! $line->transfer->isPending()) {
            throw ValidationException::withMessages(['status' => 'Lines can only be edited while draft or pending approval.']);
        }

        $line->update([
            'approved_quantity_suom' => max(0, $approvedQty),
            'received_quantity_suom' => max(0, $receivedQty),
        ]);

        return $line->fresh('item');
    }

    private function adjustStock(
        int $businessId,
        int $storeId,
        int $itemId,
        float $delta,
        string $movementType,
        StockTransfer $transfer,
        int $userId,
        string $label
    ): void {
        $stock = InventoryStockLevel::firstOrCreate(
            [
                'business_id' => $businessId,
                'store_id' => $storeId,
                'item_id' => $itemId,
            ],
            ['quantity_suom' => 0, 'physical_quantity_suom' => 0]
        );

        $before = (float) $stock->quantity_suom;

        if ($delta < 0 && $before + $delta < -0.0001) {
            throw ValidationException::withMessages([
                'quantity' => 'Insufficient system stock at the dispatch store for this transfer.',
            ]);
        }

        $after = $stock->applyOnHandBalance(max(0, round($before + $delta, 4)));

        $stock->save();

        InventoryStockMovement::create([
            'business_id' => $businessId,
            'item_id' => $itemId,
            'store_id' => $storeId,
            'movement_type' => $movementType,
            'quantity_delta' => $delta,
            'balance_after' => $after,
            'stock_transfer_id' => $transfer->id,
            'reference_label' => $label,
            'recorded_by_user_id' => $userId,
            'occurred_at' => now(),
        ]);
    }

    private function assertTransferRouteAllowed(StockTransfer $transfer): void
    {
        $transfer->loadMissing(['fromStore', 'toStore']);

        if (! $transfer->fromStore || ! $transfer->toStore) {
            throw ValidationException::withMessages([
                'status' => 'This transfer references stores that are no longer valid.',
            ]);
        }

        if (! $transfer->fromStore->canTransferStockTo($transfer->toStore)) {
            throw ValidationException::withMessages([
                'to_store_id' => 'Child stores cannot transfer stock directly to other child stores. Move stock through the parent distribution store first.',
            ]);
        }
    }

    private function finalizeApproval(StockTransfer $transfer, User $user): void
    {
        $transfer->load('lines.item');

        foreach ($transfer->lines as $line) {
            $qty = (float) $line->approved_quantity_suom;

            if ($qty <= 0) {
                continue;
            }

            // Temporary decrement: leave available stock, hold as in-transit until receipt confirmed.
            $this->moveToInTransit(
                (int) $transfer->business_id,
                (int) $transfer->from_store_id,
                (int) $line->item_id,
                $qty,
                $transfer,
                $user->id,
                'Issued in transit '.$transfer->reference
            );
        }

        $transfer->update([
            'status' => StockTransfer::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by_user_id' => $user->id,
            'current_approval_order' => null,
        ]);
    }

    /**
     * Source available stock ↓, in-transit ↑ (temporary issuance).
     */
    private function moveToInTransit(
        int $businessId,
        int $storeId,
        int $itemId,
        float $qty,
        StockTransfer $transfer,
        int $userId,
        string $label
    ): void {
        $stock = InventoryStockLevel::firstOrCreate(
            [
                'business_id' => $businessId,
                'store_id' => $storeId,
                'item_id' => $itemId,
            ],
            ['quantity_suom' => 0, 'physical_quantity_suom' => 0, 'quantity_in_transit_suom' => 0]
        );

        $before = (float) $stock->quantity_suom;

        if ($before + 0.0001 < $qty) {
            throw ValidationException::withMessages([
                'quantity' => 'Insufficient available stock at the dispatch store for this transfer.',
            ]);
        }

        $after = $stock->applyOnHandBalance(max(0, round($before - $qty, 4)));
        $stock->quantity_in_transit_suom = round((float) ($stock->quantity_in_transit_suom ?? 0) + $qty, 4);
        $stock->save();

        InventoryStockMovement::create([
            'business_id' => $businessId,
            'item_id' => $itemId,
            'store_id' => $storeId,
            'movement_type' => InventoryStockMovement::TYPE_TRANSFER_OUT,
            'quantity_delta' => -$qty,
            'balance_after' => $after,
            'stock_transfer_id' => $transfer->id,
            'reference_label' => $label,
            'recorded_by_user_id' => $userId,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Complete source decrement (clear in-transit) and credit destination.
     */
    private function completeInTransitReceipt(
        int $businessId,
        int $fromStoreId,
        int $toStoreId,
        int $itemId,
        float $qty,
        StockTransfer $transfer,
        int $userId
    ): void {
        $source = InventoryStockLevel::query()
            ->where('business_id', $businessId)
            ->where('store_id', $fromStoreId)
            ->where('item_id', $itemId)
            ->first();

        if ($source) {
            $inTransit = (float) ($source->quantity_in_transit_suom ?? 0);
            $source->quantity_in_transit_suom = max(0, round($inTransit - $qty, 4));
            $source->save();
        }

        $this->adjustStock(
            $businessId,
            $toStoreId,
            $itemId,
            $qty,
            InventoryStockMovement::TYPE_TRANSFER_IN,
            $transfer,
            $userId,
            'Transfer in '.$transfer->reference
        );
    }

    private function currentPendingApproval(StockTransfer $transfer): ?StockTransferApproval
    {
        return $transfer->approvals()
            ->where('status', StockTransferApproval::STATUS_PENDING)
            ->orderBy('approval_order')
            ->first();
    }

    private function configuredApprovers(int $businessId)
    {
        $config = InventoryModuleConfig::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->first();

        if (! $config) {
            return collect();
        }

        return $config->approvers()->orderBy('approval_order')->get();
    }
}
