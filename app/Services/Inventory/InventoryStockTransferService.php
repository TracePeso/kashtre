<?php

namespace App\Services\Inventory;

use App\Models\InventoryStockLevel;
use App\Models\InventoryStockMovement;
use App\Models\StockTransfer;
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

    public function submit(StockTransfer $transfer, User $user): StockTransfer
    {
        if (! $transfer->isDraft()) {
            throw ValidationException::withMessages(['status' => 'Only draft transfers can be submitted.']);
        }

        $transfer->update([
            'status' => StockTransfer::STATUS_PENDING,
            'requested_at' => now(),
            'requested_by_user_id' => $user->id,
        ]);

        return $transfer->fresh(['lines.item', 'fromStore', 'toStore']);
    }

    public function approve(StockTransfer $transfer, User $user): StockTransfer
    {
        if (! $transfer->isPending()) {
            throw ValidationException::withMessages(['status' => 'This transfer is not awaiting dispatch approval.']);
        }

        return DB::transaction(function () use ($transfer, $user) {
            $transfer->load('lines.item');

            foreach ($transfer->lines as $line) {
                $qty = (float) $line->approved_quantity_suom;

                if ($qty <= 0) {
                    continue;
                }

                $this->adjustStock(
                    (int) $transfer->business_id,
                    (int) $transfer->from_store_id,
                    (int) $line->item_id,
                    -$qty,
                    InventoryStockMovement::TYPE_TRANSFER_OUT,
                    $transfer,
                    $user->id,
                    'Transfer out '.$transfer->reference
                );
            }

            $transfer->update([
                'status' => StockTransfer::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by_user_id' => $user->id,
            ]);

            return $transfer->fresh(['lines.item', 'fromStore', 'toStore']);
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

                $this->adjustStock(
                    (int) $transfer->business_id,
                    (int) $transfer->to_store_id,
                    (int) $line->item_id,
                    $qty,
                    InventoryStockMovement::TYPE_TRANSFER_IN,
                    $transfer,
                    $user->id,
                    'Transfer in '.$transfer->reference
                );
            }

            $transfer->update([
                'status' => StockTransfer::STATUS_RECEIVED,
                'received_at' => now(),
                'received_by_user_id' => $user->id,
            ]);

            return $transfer->fresh(['lines.item', 'fromStore', 'toStore']);
        });
    }

    public function reject(StockTransfer $transfer, User $user, string $reason): StockTransfer
    {
        if (! $transfer->isPending()) {
            throw ValidationException::withMessages(['status' => 'Only pending transfers can be rejected.']);
        }

        $transfer->update([
            'status' => StockTransfer::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'approved_by_user_id' => $user->id,
        ]);

        return $transfer->fresh(['lines.item', 'fromStore', 'toStore']);
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
}
