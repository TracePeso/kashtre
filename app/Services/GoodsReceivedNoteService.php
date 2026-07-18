<?php

namespace App\Services;

use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteApproval;
use App\Models\GoodsReceivedNoteLine;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Models\InventoryStockMovement;
use App\Models\User;
use App\Services\Inventory\InventoryProcurementAudit;
use App\Services\Inventory\InventoryPurchaseOrderFulfillmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceivedNoteService
{
    public function submit(GoodsReceivedNote $grn, User $user): GoodsReceivedNote
    {
        if (! $grn->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Only draft goods receive notes can be submitted.',
            ]);
        }

        if ($grn->lines()->count() < 1) {
            throw ValidationException::withMessages([
                'lines' => 'Add at least one line item before submitting.',
            ]);
        }

        if (! $grn->supplier_id) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Select the supplier before submitting this goods receive note.',
            ]);
        }

        if (! $grn->store_id) {
            throw ValidationException::withMessages([
                'store_id' => 'Select the receiving store before submitting this goods receive note.',
            ]);
        }

        if (! $grn->date_of_order) {
            throw ValidationException::withMessages([
                'date_of_order' => 'Enter the order date before submitting this goods receive note.',
            ]);
        }

        if (! $grn->date_of_delivery) {
            throw ValidationException::withMessages([
                'date_of_delivery' => 'Enter the delivery date before submitting this goods receive note.',
            ]);
        }

        $approvers = $this->configuredApprovers($grn->business_id);

        if ($approvers->isEmpty()) {
            throw ValidationException::withMessages([
                'approvers' => 'No goods receive note approvers are configured. Set them under Inventory → Goods receive note approvers.',
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
                'status' => 'This goods receive note is not awaiting approval.',
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
                'status' => 'This goods receive note is not awaiting approval.',
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
        $grn->loadMissing('lines');

        if (! $grn->isApproved()) {
            return;
        }

        if ($grn->stock_applied_at && $this->hasCompleteStockPostings($grn)) {
            return;
        }

        $this->ensureLineSaleUnits($grn);

        $postedUnits = $this->applyStock($grn);

        if ($grn->lines->isNotEmpty() && $postedUnits <= 0) {
            throw ValidationException::withMessages([
                'lines' => 'Stock could not be posted because line sale units are zero. Check delivery quantities and sale units per delivery on each line.',
            ]);
        }

        if (! $grn->stock_applied_at) {
            $grn->update(['stock_applied_at' => now()]);
        }
    }

    public function applyStock(GoodsReceivedNote $grn): float
    {
        $grn->loadMissing('lines');

        if (! $grn->store_id) {
            throw ValidationException::withMessages([
                'store_id' => 'A receiving store is required before stock can be posted.',
            ]);
        }

        $postedUnits = 0.0;

        foreach ($grn->lines as $line) {
            $saleUnits = $this->effectiveSaleUnits($line);

            if ($saleUnits <= 0) {
                continue;
            }

            if ($this->movementExistsForLine($line)) {
                $postedUnits += $saleUnits;

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
                    'physical_quantity_suom' => 0,
                ]
            );

            $conversion = max((float) $line->sale_units_per_purchase_unit, 0.0001);
            $pricePerSuom = (float) $line->purchase_price / $conversion;
            $unitPrice = $pricePerSuom;
            $balanceBefore = (float) $stock->quantity_suom;
            $balanceAfter = $balanceBefore + $saleUnits;
            $valueBefore = $balanceBefore * (float) ($stock->weighted_avg_cost ?? $stock->last_purchase_price ?? $unitPrice);
            $lineValuation = round($saleUnits * $unitPrice, 2);
            $balanceValuation = round($valueBefore + $lineValuation, 2);
            $weightedAvgCost = $balanceAfter > 0
                ? round($balanceValuation / $balanceAfter, 2)
                : 0.0;

            $stock->applyOnHandBalance($balanceAfter);
            $stock->last_purchase_price = round($unitPrice, 2);
            $stock->weighted_avg_cost = $weightedAvgCost;
            $stock->save();

            InventoryStockMovement::create([
                'business_id' => $grn->business_id,
                'item_id' => $line->item_id,
                'store_id' => $grn->store_id,
                'movement_type' => InventoryStockMovement::TYPE_GRN_RECEIPT,
                'quantity_delta' => $saleUnits,
                'balance_after' => $balanceAfter,
                'unit_price' => round($unitPrice, 2),
                'line_valuation' => $lineValuation,
                'balance_valuation' => $balanceValuation,
                'goods_received_note_id' => $grn->id,
                'goods_received_note_line_id' => $line->id,
                'reference_label' => $grn->grn_number,
                'recorded_by_user_id' => $grn->entry_by_user_id,
                'occurred_at' => now(),
            ]);

            $postedUnits += $saleUnits;
        }

        return $postedUnits;
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

    public function recordInspection(
        GoodsReceivedNote $grn,
        User $user,
        string $status,
        ?string $notes = null,
        array $lineConditions = []
    ): GoodsReceivedNote {
        if (! in_array($status, [
            GoodsReceivedNote::INSPECTION_PASSED,
            GoodsReceivedNote::INSPECTION_FAILED,
            GoodsReceivedNote::INSPECTION_PENDING,
        ], true)) {
            throw ValidationException::withMessages([
                'inspection_status' => 'Invalid inspection status.',
            ]);
        }

        if (! $grn->isDraft() && ! $grn->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Inspection can only be recorded on draft or pending GRNs.',
            ]);
        }

        return DB::transaction(function () use ($grn, $user, $status, $notes, $lineConditions) {
            $grn->load('lines');

            foreach ($grn->lines as $line) {
                $ordered = $line->ordered_quantity !== null
                    ? (float) $line->ordered_quantity
                    : null;
                $received = (float) $line->quantity;
                $variance = $ordered !== null ? round($received - $ordered, 4) : null;
                $condition = $lineConditions[$line->id] ?? $line->condition_status ?? 'good';

                $line->update([
                    'variance_quantity' => $variance,
                    'condition_status' => $condition,
                ]);
            }

            $grn->update([
                'inspection_status' => $status,
                'inspection_notes' => $notes,
                'inspected_by_user_id' => $user->id,
                'inspected_at' => now(),
                'updated_by' => $user->id,
            ]);

            InventoryProcurementAudit::log(
                'grn_inspection',
                $grn,
                'GRN '.$grn->grn_number.' inspection '.$status,
                why: $notes,
                newValues: [
                    'inspection_status' => $status,
                    'has_variance' => $grn->fresh('lines')->hasVariance(),
                ],
            );

            return $grn->fresh(['lines', 'inspectedBy', 'approvals.approver']);
        });
    }

    private function finalizeApproval(GoodsReceivedNote $grn, User $user): void
    {
        $grn->refresh();

        if ($grn->inspection_status !== GoodsReceivedNote::INSPECTION_PASSED) {
            throw ValidationException::withMessages([
                'inspection_status' => 'Complete QC inspection and mark it as passed before final GRN approval (stock cannot post yet).',
            ]);
        }

        $grn->update([
            'status' => GoodsReceivedNote::STATUS_APPROVED,
            'approved_at' => now(),
            'current_approval_order' => null,
            'updated_by' => $user->id,
        ]);

        $this->applyStockIfNeeded($grn->fresh());

        if ($grn->inventory_order_id || $grn->inventory_purchase_order_id) {
            app(InventoryPurchaseOrderFulfillmentService::class)->applyGrnReceipt($grn->fresh(['lines', 'inventoryOrder.lines', 'purchaseOrder.lines']));
        }

        InventoryProcurementAudit::log(
            'grn_approved',
            $grn,
            'GRN '.$grn->grn_number.' approved after QC — stock updated',
            why: 'Final approval after inspection passed',
        );
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

        return $config->grnApprovers()->get();
    }

    private function ensureLineSaleUnits(GoodsReceivedNote $grn): void
    {
        foreach ($grn->lines as $line) {
            $expected = GoodsReceivedNoteLine::calculateSaleUnitsPurchased(
                (float) $line->quantity,
                (float) $line->sale_units_per_purchase_unit
            );

            if ((float) $line->sale_units_purchased !== $expected) {
                $line->update(['sale_units_purchased' => $expected]);
            }
        }

        $grn->load('lines');
    }

    private function effectiveSaleUnits(GoodsReceivedNoteLine $line): float
    {
        $stored = (float) $line->sale_units_purchased;

        if ($stored > 0) {
            return $stored;
        }

        return GoodsReceivedNoteLine::calculateSaleUnitsPurchased(
            (float) $line->quantity,
            (float) $line->sale_units_per_purchase_unit
        );
    }

    private function movementExistsForLine(GoodsReceivedNoteLine $line): bool
    {
        return InventoryStockMovement::query()
            ->where('goods_received_note_line_id', $line->id)
            ->where('movement_type', InventoryStockMovement::TYPE_GRN_RECEIPT)
            ->exists();
    }

    private function hasCompleteStockPostings(GoodsReceivedNote $grn): bool
    {
        foreach ($grn->lines as $line) {
            if ($this->effectiveSaleUnits($line) <= 0) {
                continue;
            }

            if (! $this->movementExistsForLine($line)) {
                return false;
            }
        }

        return true;
    }
}
