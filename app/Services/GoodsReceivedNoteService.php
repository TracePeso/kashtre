<?php

namespace App\Services;

use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteApproval;
use App\Models\GoodsReceivedNoteLine;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Models\InventoryStockMovement;
use App\Models\User;
use App\Services\Inventory\InventoryOrderFulfillmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceivedNoteService
{
    public function submit(GoodsReceivedNote $grn, User $user): GoodsReceivedNote
    {
        if (! $grn->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Only draft GRNs can be submitted.',
            ]);
        }

        if ($grn->lines()->count() < 1) {
            throw ValidationException::withMessages([
                'lines' => 'Add at least one line item before submitting.',
            ]);
        }

        if (! $grn->store_id) {
            throw ValidationException::withMessages([
                'store_id' => 'Select the receiving store before submitting this GRN.',
            ]);
        }

        $approvers = $this->configuredApprovers($grn->business_id);

        if ($approvers->isEmpty()) {
            throw ValidationException::withMessages([
                'approvers' => 'No GRN approvers are configured. Set them under Inventory → GRN Approvers.',
            ]);
        }

        $firstApprovalOrder = (int) $approvers->min('approval_order');

        return DB::transaction(function () use ($grn, $user, $approvers, $firstApprovalOrder) {
            $grn->update([
                'status' => GoodsReceivedNote::STATUS_PENDING,
                'current_approval_order' => $firstApprovalOrder,
                'submitted_by_user_id' => $user->id,
                'submitted_at' => now(),
                'entry_by_user_id' => $grn->entry_by_user_id ?? $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($approvers as $approver) {
                GoodsReceivedNoteApproval::create([
                    'goods_received_note_id' => $grn->id,
                    'approver_user_id' => $approver->user_id,
                    'approval_order' => $approver->approval_order,
                    'status' => GoodsReceivedNoteApproval::STATUS_PENDING,
                ]);
            }

            return $grn->fresh(['lines', 'approvals.approver', 'supplier', 'store', 'entryBy']);
        });
    }

    public function approve(GoodsReceivedNote $grn, User $user, ?string $comment = null): GoodsReceivedNote
    {
        if (! $grn->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'This GRN is not awaiting approval.',
            ]);
        }

        $pending = $this->currentPendingApproval($grn);

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

        return DB::transaction(function () use ($grn, $user, $pending, $comment) {
            $pending->update([
                'status' => GoodsReceivedNoteApproval::STATUS_APPROVED,
                'comment' => $comment,
                'acted_at' => now(),
            ]);

            $nextPending = $grn->approvals()
                ->where('status', GoodsReceivedNoteApproval::STATUS_PENDING)
                ->orderBy('approval_order')
                ->first();

            if ($nextPending) {
                $grn->update([
                    'current_approval_order' => $nextPending->approval_order,
                    'updated_by' => $user->id,
                ]);

                return $grn->fresh(['lines', 'approvals.approver', 'supplier', 'store', 'entryBy']);
            }

            $this->finalizeApproval($grn, $user);

            return $grn->fresh(['lines', 'approvals.approver', 'supplier', 'store', 'entryBy']);
        });
    }

    public function reject(GoodsReceivedNote $grn, User $user, string $reason): GoodsReceivedNote
    {
        if (! $grn->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'This GRN is not awaiting approval.',
            ]);
        }

        $pending = $this->currentPendingApproval($grn);

        if (! $pending || (int) $pending->approver_user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'approver' => 'You are not the approver for the current step.',
            ]);
        }

        return DB::transaction(function () use ($grn, $user, $pending, $reason) {
            $pending->update([
                'status' => GoodsReceivedNoteApproval::STATUS_REJECTED,
                'comment' => $reason,
                'acted_at' => now(),
            ]);

            $grn->update([
                'status' => GoodsReceivedNote::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'current_approval_order' => null,
                'updated_by' => $user->id,
            ]);

            return $grn->fresh(['lines', 'approvals.approver', 'supplier', 'store', 'entryBy']);
        });
    }

    public function applyStockIfNeeded(GoodsReceivedNote $grn): void
    {
        $grn->refresh();

        if ($grn->stock_applied_at || ! $grn->isApproved()) {
            return;
        }

        $this->applyStock($grn);

        $grn->update(['stock_applied_at' => now()]);
    }

    public function applyStock(GoodsReceivedNote $grn): void
    {
        $grn->loadMissing('lines');

        if (! $grn->store_id) {
            throw ValidationException::withMessages([
                'store_id' => 'A receiving store is required before stock can be posted.',
            ]);
        }

        foreach ($grn->lines as $line) {
            $saleUnits = (float) $line->sale_units_purchased;

            if ($saleUnits <= 0) {
                continue;
            }

            $stock = InventoryStockLevel::firstOrCreate(
                [
                    'business_id' => $grn->business_id,
                    'store_id' => $grn->store_id,
                    'item_id' => $line->item_id,
                ],
                [
                    'quantity_suom' => 0,
                ]
            );

            $unitPrice = (float) $line->purchase_price;
            $balanceBefore = (float) $stock->quantity_suom;
            $balanceAfter = $balanceBefore + $saleUnits;
            $valueBefore = $balanceBefore * (float) ($stock->weighted_avg_cost ?? $stock->last_purchase_price ?? $unitPrice);
            $lineValuation = round($saleUnits * $unitPrice, 2);
            $balanceValuation = round($valueBefore + $lineValuation, 2);
            $weightedAvgCost = $balanceAfter > 0
                ? round($balanceValuation / $balanceAfter, 2)
                : 0.0;

            $stock->quantity_suom = $balanceAfter;
            $stock->last_purchase_price = $unitPrice;
            $stock->weighted_avg_cost = $weightedAvgCost;
            $stock->save();

            InventoryStockMovement::create([
                'business_id' => $grn->business_id,
                'item_id' => $line->item_id,
                'store_id' => $grn->store_id,
                'movement_type' => InventoryStockMovement::TYPE_GRN_RECEIPT,
                'quantity_delta' => $saleUnits,
                'balance_after' => $balanceAfter,
                'unit_price' => $unitPrice,
                'line_valuation' => $lineValuation,
                'balance_valuation' => $balanceValuation,
                'goods_received_note_id' => $grn->id,
                'goods_received_note_line_id' => $line->id,
                'reference_label' => $grn->grn_number,
                'recorded_by_user_id' => $grn->entry_by_user_id,
                'occurred_at' => now(),
            ]);
        }
    }

    public function calculateLeadTimeDays(string $dateOfOrder, string $dateOfDelivery): int
    {
        $order = \Carbon\Carbon::parse($dateOfOrder)->startOfDay();
        $delivery = \Carbon\Carbon::parse($dateOfDelivery)->startOfDay();

        return max(0, (int) $order->diffInDays($delivery));
    }

    public function userCanApprove(GoodsReceivedNote $grn, User $user): bool
    {
        if (! $grn->isPending()) {
            return false;
        }

        $pending = $this->currentPendingApproval($grn);

        return $pending && (int) $pending->approver_user_id === (int) $user->id;
    }

    private function finalizeApproval(GoodsReceivedNote $grn, User $user): void
    {
        $grn->update([
            'status' => GoodsReceivedNote::STATUS_APPROVED,
            'approved_at' => now(),
            'current_approval_order' => null,
            'updated_by' => $user->id,
        ]);

        $this->applyStockIfNeeded($grn->fresh());

        if ($grn->inventory_order_id) {
            app(InventoryOrderFulfillmentService::class)->applyGrnReceipt($grn->fresh(['lines', 'inventoryOrder.lines']));
        }
    }

    private function currentPendingApproval(GoodsReceivedNote $grn): ?GoodsReceivedNoteApproval
    {
        return $grn->approvals()
            ->where('status', GoodsReceivedNoteApproval::STATUS_PENDING)
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
