<?php

namespace App\Services\Inventory;

use App\Models\InventoryOrder;
use App\Models\InventoryRfqSupplier;
use App\Models\InventorySupplierQuotation;
use App\Models\InventorySupplierQuotationLine;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventorySupplierQuotationService
{
    /**
     * Suppliers invited to quote on this RFQ (plus primary supplier if set).
     *
     * @return Collection<int, array{supplier_id: int, supplier_name: string, lines_count: int, email: ?string, quotation: ?InventorySupplierQuotation}>
     */
    public function suppliersForRfq(InventoryOrder $order): Collection
    {
        $order->loadMissing(['supplier', 'invitedSuppliers', 'supplierQuotations.lines', 'lines']);

        $this->ensurePrimarySupplierInvited($order);

        $order->load('invitedSuppliers');

        return $order->invitedSuppliers->map(function (Supplier $supplier) use ($order) {
            $quotation = $order->supplierQuotations->firstWhere('supplier_id', $supplier->id);

            return [
                'supplier_id' => (int) $supplier->id,
                'supplier_name' => $supplier->name,
                'email' => $supplier->email,
                'lines_count' => $order->lines->count(),
                'quotation' => $quotation,
            ];
        })->values();
    }

    public function inviteSuppliers(InventoryOrder $order, array $supplierIds): void
    {
        if (! $order->isExternal()) {
            throw ValidationException::withMessages([
                'order_type' => 'Only external RFQs can invite suppliers.',
            ]);
        }

        if (! $order->isDraft() && ! $order->isRfqApproved() && ! $order->isPendingApproval()) {
            // Allow invite while draft or after approve for late adds; block once PO issued.
            if (in_array($order->status, [
                InventoryOrder::STATUS_PO_ISSUED,
                InventoryOrder::STATUS_PARTIALLY_RECEIVED,
                InventoryOrder::STATUS_FULFILLED,
                InventoryOrder::STATUS_REJECTED,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Cannot invite suppliers after the RFQ is closed or LPO has been issued.',
                ]);
            }
        }

        $ids = array_values(array_unique(array_map('intval', $supplierIds)));

        if ($order->supplier_id) {
            $ids[] = (int) $order->supplier_id;
            $ids = array_values(array_unique($ids));
        }

        if ($ids === []) {
            throw ValidationException::withMessages([
                'supplier_ids' => 'Select at least one supplier.',
            ]);
        }

        $valid = Supplier::query()
            ->where('business_id', $order->business_id)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        foreach ($valid as $supplierId) {
            InventoryRfqSupplier::query()->firstOrCreate(
                [
                    'inventory_order_id' => $order->id,
                    'supplier_id' => $supplierId,
                ],
                ['invited_at' => now()]
            );
        }

        InventoryProcurementAudit::log(
            'rfq_suppliers_invited',
            $order,
            'Suppliers invited to RFQ '.$order->order_number,
            why: 'Multi-supplier RFQ distribution',
            newValues: ['supplier_ids' => $valid],
        );
    }

    public function ensurePrimarySupplierInvited(InventoryOrder $order): void
    {
        if (! $order->supplier_id) {
            return;
        }

        InventoryRfqSupplier::query()->firstOrCreate(
            [
                'inventory_order_id' => $order->id,
                'supplier_id' => (int) $order->supplier_id,
            ],
            ['invited_at' => now()]
        );
    }

    public function createOrUpdateFromRfq(
        InventoryOrder $order,
        ?int $supplierId,
        User $user,
        array $lineInputs,
        ?string $referenceNumber = null,
        ?string $notes = null
    ): InventorySupplierQuotation {
        if (! $order->canManageSupplierQuotations()) {
            throw ValidationException::withMessages([
                'status' => 'Supplier quotations can only be recorded after the RFQ is approved.',
            ]);
        }

        $this->ensurePrimarySupplierInvited($order);

        $supplierId = $supplierId ?? (int) $order->supplier_id;

        if (! $supplierId) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Select a supplier for this quotation.',
            ]);
        }

        $invited = InventoryRfqSupplier::query()
            ->where('inventory_order_id', $order->id)
            ->where('supplier_id', $supplierId)
            ->exists();

        if (! $invited && (int) $order->supplier_id !== (int) $supplierId) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Invite this supplier to the RFQ before recording their quotation.',
            ]);
        }

        if (! $invited) {
            $this->ensurePrimarySupplierInvited($order);
        }

        return DB::transaction(function () use ($order, $supplierId, $user, $lineInputs, $referenceNumber, $notes) {
            $quotation = InventorySupplierQuotation::query()->firstOrNew([
                'inventory_order_id' => $order->id,
                'supplier_id' => $supplierId,
            ]);

            if ($quotation->exists && $quotation->isAccepted()) {
                throw ValidationException::withMessages([
                    'status' => 'This supplier quotation has already been accepted and cannot be edited.',
                ]);
            }

            $quotation->fill([
                'business_id' => $order->business_id,
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'status' => InventorySupplierQuotation::STATUS_RECEIVED,
                'received_at' => now(),
                'created_by_user_id' => $quotation->created_by_user_id ?? $user->id,
            ]);
            $quotation->save();

            $quotation->lines()->delete();
            $total = 0.0;
            $order->loadMissing('lines');

            foreach ($lineInputs as $input) {
                $orderLineId = (int) ($input['inventory_order_line_id'] ?? 0);
                $orderLine = $order->lines->firstWhere('id', $orderLineId);

                if (! $orderLine) {
                    continue;
                }

                $qty = max(0, (float) ($input['quoted_quantity_suom'] ?? 0));
                $unitPrice = max(0, (float) ($input['unit_price'] ?? 0));
                $lineTotal = round($qty * $unitPrice, 2);
                $total += $lineTotal;

                InventorySupplierQuotationLine::create([
                    'inventory_supplier_quotation_id' => $quotation->id,
                    'inventory_order_line_id' => $orderLine->id,
                    'item_id' => $orderLine->item_id,
                    'quoted_quantity_suom' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'comments' => filled($input['comments'] ?? null) ? trim((string) $input['comments']) : null,
                ]);
            }

            if ($quotation->lines()->count() < 1) {
                throw ValidationException::withMessages([
                    'lines' => 'Enter at least one quoted line.',
                ]);
            }

            $quotation->update(['total_amount' => round($total, 2)]);

            InventoryProcurementAudit::log(
                'quotation_recorded',
                $quotation,
                'Quotation recorded for '.$quotation->supplier?->name.' on '.$order->order_number,
                why: 'Supplier quotation entered for comparative analysis',
                newValues: ['total_amount' => $quotation->total_amount],
            );

            return $quotation->fresh(['lines.item', 'supplier', 'purchaseOrder']);
        });
    }

    /**
     * Side-by-side comparison rows for the computation sheet.
     *
     * @return array{lines: list<array<string, mixed>>, suppliers: list<array<string, mixed>>}
     */
    public function comparisonSheet(InventoryOrder $order): array
    {
        $order->loadMissing(['lines.item', 'supplierQuotations.lines', 'supplierQuotations.supplier', 'rfqLineAwards.supplier']);

        $quotations = $order->supplierQuotations
            ->filter(fn (InventorySupplierQuotation $q) => in_array($q->status, [
                InventorySupplierQuotation::STATUS_RECEIVED,
                InventorySupplierQuotation::STATUS_ACCEPTED,
                InventorySupplierQuotation::STATUS_REJECTED,
            ], true))
            ->values();

        $suppliers = $quotations->map(fn (InventorySupplierQuotation $q) => [
            'quotation_id' => $q->id,
            'supplier_id' => $q->supplier_id,
            'supplier_name' => $q->supplier?->name ?? '—',
            'status' => $q->status,
            'status_label' => $q->statusLabel(),
            'total_amount' => (float) $q->total_amount,
            'can_accept' => $q->canAccept(),
            'is_accepted' => $q->isAccepted(),
            'has_lpo' => (bool) $q->purchaseOrder,
        ])->all();

        $lines = [];

        $awardsByLine = $order->rfqLineAwards->groupBy('inventory_order_line_id');

        foreach ($order->lines as $orderLine) {
            $bySupplier = [];
            $bestPrice = null;
            $bestSupplierId = null;
            $lineAwards = $awardsByLine[$orderLine->id] ?? collect();
            $awardedTotal = (float) $lineAwards->sum('awarded_quantity_suom');
            $rfqQty = (float) $orderLine->order_quantity_suom;

            foreach ($quotations as $quotation) {
                $qLine = $quotation->lines->firstWhere('inventory_order_line_id', $orderLine->id);
                $unitPrice = $qLine ? (float) $qLine->unit_price : null;
                $qty = $qLine ? (float) $qLine->quoted_quantity_suom : null;
                $lineTotal = $qLine ? (float) $qLine->line_total : null;
                $award = $lineAwards->firstWhere('supplier_id', $quotation->supplier_id);

                $bySupplier[$quotation->supplier_id] = [
                    'unit_price' => $unitPrice,
                    'quoted_qty' => $qty,
                    'line_total' => $lineTotal,
                    'comments' => $qLine?->comments,
                    'is_awarded' => $award !== null,
                    'awarded_qty' => $award ? (float) $award->awarded_quantity_suom : null,
                ];

                if ($unitPrice !== null && $unitPrice > 0 && ($bestPrice === null || $unitPrice < $bestPrice)) {
                    $bestPrice = $unitPrice;
                    $bestSupplierId = (int) $quotation->supplier_id;
                }
            }

            $lines[] = [
                'order_line_id' => (int) $orderLine->id,
                'item_name' => $orderLine->item?->name ?? '—',
                'item_code' => $orderLine->item?->code,
                'rfq_qty' => $rfqQty,
                'analysis_comment' => $orderLine->quotation_analysis_comment,
                'awarded_total' => $awardedTotal,
                'remaining_qty' => max(0, $rfqQty - $awardedTotal),
                'fulfillment_label' => $this->fulfillmentLabel($awardedTotal, $rfqQty),
                'quotes' => $bySupplier,
                'best_supplier_id' => $bestSupplierId,
                'best_unit_price' => $bestPrice,
                'awards' => $lineAwards->map(fn ($award) => [
                    'supplier_id' => (int) $award->supplier_id,
                    'supplier_name' => $award->supplier?->name ?? '—',
                    'awarded_quantity_suom' => (float) $award->awarded_quantity_suom,
                    'unit_price' => (float) $award->unit_price,
                ])->values()->all(),
            ];
        }

        return [
            'suppliers' => $suppliers,
            'lines' => $lines,
        ];
    }

    private function fulfillmentLabel(float $awarded, float $rfqQty): string
    {
        if ($awarded <= 0) {
            return 'Unallocated';
        }

        if ($awarded >= $rfqQty - 0.0001) {
            return 'Fully allocated';
        }

        return 'Partial ('.number_format($awarded, 0).' / '.number_format($rfqQty, 0).')';
    }

    public function accept(InventorySupplierQuotation $quotation): InventorySupplierQuotation
    {
        if (! $quotation->canAccept()) {
            throw ValidationException::withMessages([
                'status' => 'Only received quotations can be accepted.',
            ]);
        }

        $quotation->update(['status' => InventorySupplierQuotation::STATUS_ACCEPTED]);

        InventoryProcurementAudit::log(
            'quotation_accepted',
            $quotation,
            'Supplier quotation accepted for '.$quotation->inventoryOrder?->order_number,
            why: 'Admin finalized supplier selection (may accept multiple suppliers for LPO split)',
            newValues: [
                'status' => $quotation->status,
                'supplier_id' => $quotation->supplier_id,
                'total_amount' => $quotation->total_amount,
            ],
        );

        return $quotation->fresh(['lines.item', 'supplier', 'purchaseOrder']);
    }

    public function reject(InventorySupplierQuotation $quotation): InventorySupplierQuotation
    {
        if ($quotation->purchaseOrder) {
            throw ValidationException::withMessages([
                'status' => 'A purchase order already exists for this quotation.',
            ]);
        }

        $quotation->update(['status' => InventorySupplierQuotation::STATUS_REJECTED]);

        InventoryProcurementAudit::log(
            'quotation_rejected',
            $quotation,
            'Supplier quotation rejected for '.$quotation->inventoryOrder?->order_number,
            why: 'Admin rejected this quotation',
            newValues: [
                'status' => $quotation->status,
                'supplier_id' => $quotation->supplier_id,
            ],
        );

        return $quotation->fresh(['lines.item', 'supplier']);
    }

    /**
     * @param  list<array{inventory_order_line_id: int, quotation_analysis_comment?: ?string}>  $lineComments
     */
    public function saveLineComments(InventoryOrder $order, array $lineComments): void
    {
        if (! $order->canManageSupplierQuotations()) {
            throw ValidationException::withMessages([
                'status' => 'Item comments can only be saved after the RFQ is approved.',
            ]);
        }

        $order->loadMissing('lines');
        $validLineIds = $order->lines->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($lineComments as $index => $input) {
            $lineId = (int) ($input['inventory_order_line_id'] ?? 0);

            if (! in_array($lineId, $validLineIds, true)) {
                throw ValidationException::withMessages([
                    "line_comments.{$index}.inventory_order_line_id" => 'One or more lines do not belong to this RFQ.',
                ]);
            }

            $comment = trim((string) ($input['quotation_analysis_comment'] ?? ''));
            $orderLine = $order->lines->firstWhere('id', $lineId);

            if ($orderLine) {
                $orderLine->update([
                    'quotation_analysis_comment' => $comment !== '' ? $comment : null,
                ]);
            }
        }

        InventoryProcurementAudit::log(
            'quotation_line_comments_saved',
            $order,
            'Item comments updated on '.$order->order_number,
            why: 'Procurement notes captured during quotation analysis',
            newValues: ['line_count' => count($lineComments)],
        );
    }
}
