<?php

namespace App\Services;

use App\Models\Business;
use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteApproval;
use App\Models\GoodsReceivedNoteLine;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Models\InventoryStockMovement;
use App\Models\Item;
use App\Models\User;
use App\Services\Inventory\InventoryProcurementAudit;
use App\Services\Inventory\InventoryPurchaseOrderFulfillmentService;
use Illuminate\Support\Collection;
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

        $business = Business::query()->find($grn->business_id);

        if ($business?->isGrnTechnicalSupervisorRequired() && ! $grn->technical_supervisor_user_id) {
            throw ValidationException::withMessages([
                'technical_supervisor_user_id' => 'A technical supervisor is required for goods receive notes in your organisation.',
            ]);
        }

        $approvers = $this->configuredRegularApprovers($grn->business_id);

        if ($approvers->isEmpty()) {
            throw ValidationException::withMessages([
                'approvers' => 'No goods receive note approvers are configured. Set them under Inventory → Goods receive note approvers.',
            ]);
        }

        $approvalSteps = collect();

        if ($grn->technical_supervisor_user_id) {
            $approvalSteps->push([
                'approver_user_id' => (int) $grn->technical_supervisor_user_id,
                'approval_order' => 0,
            ]);
        }

        foreach ($approvers as $approver) {
            $approvalSteps->push([
                'approver_user_id' => (int) $approver->user_id,
                'approval_order' => (int) $approver->approval_order,
            ]);
        }

        $firstApprovalOrder = (int) $approvalSteps->min('approval_order');

        return DB::transaction(function () use ($grn, $user, $approvalSteps, $firstApprovalOrder) {
            $grn->update([
                'status' => GoodsReceivedNote::STATUS_PENDING,
                'current_approval_order' => $firstApprovalOrder,
                'submitted_by_user_id' => $user->id,
                'submitted_at' => now(),
                'entry_by_user_id' => $grn->entry_by_user_id ?? $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($approvalSteps as $step) {
                GoodsReceivedNoteApproval::create([
                    'goods_received_note_id' => $grn->id,
                    'approver_user_id' => $step['approver_user_id'],
                    'approval_order' => $step['approval_order'],
                    'status' => GoodsReceivedNoteApproval::STATUS_PENDING,
                ]);
            }

            return $grn->fresh(['lines', 'approvals.approver', 'supplier', 'store', 'entryBy', 'technicalSupervisor']);
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

    private function finalizeApproval(GoodsReceivedNote $grn, User $user): void
    {
        $grn->refresh();

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
            'GRN '.$grn->grn_number.' approved — stock updated',
            why: 'Final approval',
        );
    }

    private function currentPendingApproval(GoodsReceivedNote $grn): ?GoodsReceivedNoteApproval
    {
        return $grn->approvals()
            ->where('status', GoodsReceivedNoteApproval::STATUS_PENDING)
            ->orderBy('approval_order')
            ->first();
    }

    private function configuredRegularApprovers(int $businessId)
    {
        $config = InventoryModuleConfig::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->first();

        if (! $config) {
            return collect();
        }

        return $config->regularApprovers()->get();
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

    /**
     * Latest approved GRN purchase price per delivery unit, keyed by item id.
     *
     * @return array<int, float>
     */
    public function lastApprovedPurchasePricesPerOuom(int $businessId): array
    {
        $lines = GoodsReceivedNoteLine::query()
            ->select([
                'goods_received_note_lines.item_id',
                'goods_received_note_lines.purchase_price',
            ])
            ->join('goods_received_notes', 'goods_received_notes.id', '=', 'goods_received_note_lines.goods_received_note_id')
            ->where('goods_received_notes.business_id', $businessId)
            ->where('goods_received_notes.status', GoodsReceivedNote::STATUS_APPROVED)
            ->whereNotNull('goods_received_notes.approved_at')
            ->where('goods_received_note_lines.purchase_price', '>', 0)
            ->orderByDesc('goods_received_notes.approved_at')
            ->orderByDesc('goods_received_notes.id')
            ->orderByDesc('goods_received_note_lines.id')
            ->get();

        $prices = [];

        foreach ($lines as $line) {
            $itemId = (int) $line->item_id;

            if (! isset($prices[$itemId])) {
                $prices[$itemId] = round((float) $line->purchase_price, 2);
            }
        }

        return $prices;
    }

    /**
     * Latest GRN line per item (any non-rejected GRN), keyed by item id.
     *
     * @return array<int, array{quantity: float, line_total: float, purchase_price_per_ouom: float}>
     */
    public function lastGrnLineSnapshotsByItem(int $businessId): array
    {
        $lines = GoodsReceivedNoteLine::query()
            ->select([
                'goods_received_note_lines.item_id',
                'goods_received_note_lines.quantity',
                'goods_received_note_lines.purchase_price',
            ])
            ->join('goods_received_notes', 'goods_received_notes.id', '=', 'goods_received_note_lines.goods_received_note_id')
            ->where('goods_received_notes.business_id', $businessId)
            ->where('goods_received_notes.status', '!=', GoodsReceivedNote::STATUS_REJECTED)
            ->where('goods_received_note_lines.quantity', '>', 0)
            ->where('goods_received_note_lines.purchase_price', '>', 0)
            ->orderByDesc('goods_received_notes.id')
            ->orderByDesc('goods_received_note_lines.id')
            ->get();

        $snapshots = [];

        foreach ($lines as $line) {
            $itemId = (int) $line->item_id;

            if (! isset($snapshots[$itemId])) {
                $quantity = (float) $line->quantity;
                $purchasePrice = (float) $line->purchase_price;

                $snapshots[$itemId] = [
                    'quantity' => $quantity,
                    'line_total' => round($quantity * $purchasePrice, 2),
                    'purchase_price_per_ouom' => round($purchasePrice, 2),
                ];
            }
        }

        return $snapshots;
    }

    /**
     * @param  array<int, float>  $lastGrnPricesByItem
     */
    public function purchasePricePerOuomForItem(Item $item, array $lastGrnPricesByItem = []): float
    {
        $fromGrn = $lastGrnPricesByItem[$item->id] ?? null;

        if ($fromGrn !== null && (float) $fromGrn > 0) {
            return round((float) $fromGrn, 2);
        }

        return $item->purchasePricePerOuom();
    }

    /**
     * @param  Collection<int, Item>  $items
     * @param  array<int, float>  $lastGrnPricesByItem
     * @param  array<int, array{quantity: float, line_total: float, purchase_price_per_ouom: float}>  $lastGrnLineSnapshots
     * @return array<int, array<string, mixed>>
     */
    public function itemsForGrnForm(Collection $items, array $lastGrnPricesByItem, array $lastGrnLineSnapshots = []): array
    {
        return $items->map(function (Item $item) use ($lastGrnPricesByItem, $lastGrnLineSnapshots) {
            $snapshot = $lastGrnLineSnapshots[$item->id] ?? null;
            $fromLastGrn = $snapshot !== null;

            return [
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code,
                'suom' => $item->itemUnit?->name,
                'order_unit' => $item->orderUnit?->name,
                'suom_per_ouom' => (float) ($item->suom_per_ouom ?? 0),
                'default_price' => (float) ($item->default_price ?? 0),
                'purchase_price_per_ouom' => $item->purchasePricePerOuom(),
                'default_purchase_price_per_ouom' => $fromLastGrn
                    ? $snapshot['purchase_price_per_ouom']
                    : $this->purchasePricePerOuomForItem($item, $lastGrnPricesByItem),
                'last_grn_total_amount' => $fromLastGrn ? $snapshot['line_total'] : 0,
                'last_grn_quantity' => $fromLastGrn ? $snapshot['quantity'] : 1,
                'from_last_grn' => $fromLastGrn,
            ];
        })->values()->all();
    }
}
