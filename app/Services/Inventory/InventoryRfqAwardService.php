<?php

namespace App\Services\Inventory;

use App\Models\InventoryOrder;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventoryRfqLineAward;
use App\Models\InventorySupplierQuotation;
use App\Models\InventorySupplierQuotationLine;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryRfqAwardService
{
    /**
     * @param  list<array{inventory_order_line_id: int, supplier_id: int, awarded_quantity_suom: float|int|string, unit_price: float|int|string}>  $awardInputs
     * @return Collection<int, InventoryRfqLineAward>
     */
    public function saveAwards(InventoryOrder $order, array $awardInputs): Collection
    {
        if (! $order->canManageSupplierQuotations()) {
            throw ValidationException::withMessages([
                'status' => 'Item supplier selections can only be saved after the RFQ is approved.',
            ]);
        }

        $order->loadMissing(['lines', 'supplierQuotations.lines', 'invitedSuppliers']);

        $normalized = collect($awardInputs)
            ->map(function (array $input) {
                $qty = max(0, (float) ($input['awarded_quantity_suom'] ?? 0));
                $unitPrice = max(0, (float) ($input['unit_price'] ?? 0));

                return [
                    'inventory_order_line_id' => (int) ($input['inventory_order_line_id'] ?? 0),
                    'supplier_id' => (int) ($input['supplier_id'] ?? 0),
                    'awarded_quantity_suom' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => round($qty * $unitPrice, 2),
                ];
            })
            ->filter(fn (array $row) => $row['inventory_order_line_id'] > 0
                && $row['supplier_id'] > 0
                && $row['awarded_quantity_suom'] > 0);

        $validLineIds = $order->lines->pluck('id')->map(fn ($id) => (int) $id)->all();
        $invitedSupplierIds = $order->invitedSuppliers->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($order->supplier_id) {
            $invitedSupplierIds[] = (int) $order->supplier_id;
            $invitedSupplierIds = array_values(array_unique($invitedSupplierIds));
        }

        $perLineTotals = [];
        $seen = [];

        foreach ($normalized as $index => $row) {
            if (! in_array($row['inventory_order_line_id'], $validLineIds, true)) {
                throw ValidationException::withMessages([
                    "awards.{$index}.inventory_order_line_id" => 'One or more lines do not belong to this RFQ.',
                ]);
            }

            if (! in_array($row['supplier_id'], $invitedSupplierIds, true)) {
                throw ValidationException::withMessages([
                    "awards.{$index}.supplier_id" => 'Invite the supplier before awarding them a line.',
                ]);
            }

            $key = $row['inventory_order_line_id'].'-'.$row['supplier_id'];
            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    "awards.{$index}.supplier_id" => 'Each supplier can only be selected once per item.',
                ]);
            }
            $seen[$key] = true;

            $quoteLine = $this->findQuotationLine($order, $row['inventory_order_line_id'], $row['supplier_id']);
            if ($quoteLine && $row['awarded_quantity_suom'] > (float) $quoteLine->quoted_quantity_suom + 0.0001) {
                throw ValidationException::withMessages([
                    "awards.{$index}.awarded_quantity_suom" => 'Award quantity cannot exceed the supplier quoted quantity for this item.',
                ]);
            }

            $perLineTotals[$row['inventory_order_line_id']] = ($perLineTotals[$row['inventory_order_line_id']] ?? 0)
                + $row['awarded_quantity_suom'];
        }

        foreach ($perLineTotals as $orderLineId => $awardedTotal) {
            $orderLine = $order->lines->firstWhere('id', $orderLineId);
            $rfqQty = (float) ($orderLine?->order_quantity_suom ?? 0);

            if ($awardedTotal > $rfqQty + 0.0001) {
                throw ValidationException::withMessages([
                    'awards' => sprintf(
                        'Total awarded quantity for %s cannot exceed the RFQ quantity (%s).',
                        $orderLine?->item?->name ?? 'item',
                        number_format($rfqQty, 0)
                    ),
                ]);
            }
        }

        return DB::transaction(function () use ($order, $normalized) {
            $order->rfqLineAwards()->delete();

            $saved = collect();

            foreach ($normalized as $row) {
                $quoteLine = $this->findQuotationLine($order, $row['inventory_order_line_id'], $row['supplier_id']);

                $award = InventoryRfqLineAward::create([
                    'inventory_order_id' => $order->id,
                    'inventory_order_line_id' => $row['inventory_order_line_id'],
                    'supplier_id' => $row['supplier_id'],
                    'inventory_supplier_quotation_line_id' => $quoteLine?->id,
                    'awarded_quantity_suom' => $row['awarded_quantity_suom'],
                    'unit_price' => $row['unit_price'],
                    'line_total' => $row['line_total'],
                ]);

                $saved->push($award);
            }

            InventoryProcurementAudit::log(
                'rfq_line_awards_saved',
                $order,
                'Supplier selections saved for '.$order->order_number,
                why: 'Per-item supplier allocation with optional partial quantities',
                newValues: ['award_count' => $saved->count()],
            );

            return $saved;
        });
    }

    /**
     * @return array{
     *     lines: list<array<string, mixed>>,
     *     suppliers: list<array<string, mixed>>,
     *     quote_lookup: array<int, array<int, array<string, mixed>>>
     * }
     */
    public function awardFormData(InventoryOrder $order): array
    {
        $order->loadMissing([
            'lines.item',
            'supplierQuotations.lines',
            'supplierQuotations.supplier',
            'rfqLineAwards.supplier',
            'invitedSuppliers',
        ]);

        $quoteLookup = [];

        foreach ($order->supplierQuotations as $quotation) {
            foreach ($quotation->lines as $quoteLine) {
                $quoteLookup[(int) $quoteLine->inventory_order_line_id][(int) $quotation->supplier_id] = [
                    'quotation_line_id' => $quoteLine->id,
                    'quoted_qty' => (float) $quoteLine->quoted_quantity_suom,
                    'unit_price' => (float) $quoteLine->unit_price,
                    'line_total' => (float) $quoteLine->line_total,
                ];
            }
        }

        $awardsByLine = $order->rfqLineAwards->groupBy('inventory_order_line_id');

        $lines = $order->lines->map(function ($orderLine) use ($awardsByLine) {
            $rfqQty = (float) $orderLine->order_quantity_suom;
            $awards = ($awardsByLine[$orderLine->id] ?? collect())->map(fn (InventoryRfqLineAward $award) => [
                'supplier_id' => (int) $award->supplier_id,
                'supplier_name' => $award->supplier?->name ?? '—',
                'awarded_quantity_suom' => (float) $award->awarded_quantity_suom,
                'unit_price' => (float) $award->unit_price,
                'line_total' => (float) $award->line_total,
            ])->values()->all();
            $awardedTotal = (float) collect($awards)->sum('awarded_quantity_suom');

            return [
                'order_line_id' => (int) $orderLine->id,
                'item_name' => $orderLine->item?->name ?? '—',
                'item_code' => $orderLine->item?->code,
                'rfq_qty' => $rfqQty,
                'analysis_comment' => $orderLine->quotation_analysis_comment,
                'awarded_total' => $awardedTotal,
                'remaining_qty' => max(0, $rfqQty - $awardedTotal),
                'is_fully_allocated' => $awardedTotal >= $rfqQty - 0.0001,
                'is_partially_allocated' => $awardedTotal > 0 && $awardedTotal < $rfqQty - 0.0001,
                'awards' => $awards,
            ];
        })->values()->all();

        $suppliers = $order->supplierQuotations
            ->filter(fn (InventorySupplierQuotation $q) => in_array($q->status, [
                InventorySupplierQuotation::STATUS_RECEIVED,
                InventorySupplierQuotation::STATUS_ACCEPTED,
            ], true))
            ->map(fn (InventorySupplierQuotation $q) => [
                'supplier_id' => (int) $q->supplier_id,
                'supplier_name' => $q->supplier?->name ?? '—',
            ])
            ->unique('supplier_id')
            ->values()
            ->all();

        return [
            'lines' => $lines,
            'suppliers' => $suppliers,
            'quote_lookup' => $quoteLookup,
        ];
    }

    /**
     * @return array{created: int, errors: list<string>}
     */
    public function createLposFromAwards(InventoryOrder $order, User $user): array
    {
        if (! $order->canManageSupplierQuotations()) {
            throw ValidationException::withMessages([
                'status' => 'LPOs can only be generated after the RFQ is approved.',
            ]);
        }

        $order->loadMissing(['rfqLineAwards.supplier', 'rfqLineAwards.inventoryOrderLine', 'lines', 'supplierQuotations', 'purchaseOrders']);

        if ($order->rfqLineAwards->isEmpty()) {
            throw ValidationException::withMessages([
                'awards' => 'Save supplier selections per item before generating LPOs.',
            ]);
        }

        $created = 0;
        $errors = [];

        $awardsBySupplier = $order->rfqLineAwards->groupBy('supplier_id');

        foreach ($awardsBySupplier as $supplierId => $awards) {
            $supplierId = (int) $supplierId;

            $existingPo = $order->purchaseOrders
                ->first(fn (InventoryPurchaseOrder $po) => (int) $po->supplier_id === $supplierId
                    && $po->status !== InventoryPurchaseOrder::STATUS_CANCELLED);

            if ($existingPo) {
                $errors[] = ($awards->first()->supplier?->name ?? 'Supplier').': LPO already exists ('.$existingPo->po_number.').';

                continue;
            }

            try {
                $this->createPoForSupplierAwards($order, $supplierId, $awards, $user);
                $created++;
            } catch (ValidationException $e) {
                $errors[] = ($awards->first()->supplier?->name ?? 'Supplier').': '.collect($e->errors())->flatten()->first();
            }
        }

        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * @param  Collection<int, InventoryRfqLineAward>  $awards
     */
    private function createPoForSupplierAwards(
        InventoryOrder $order,
        int $supplierId,
        Collection $awards,
        User $user
    ): InventoryPurchaseOrder {
        return DB::transaction(function () use ($order, $supplierId, $awards, $user) {
            $quotation = $order->supplierQuotations->firstWhere('supplier_id', $supplierId);
            $total = 0.0;

            $po = InventoryPurchaseOrder::create([
                'business_id' => $order->business_id,
                'inventory_order_id' => $order->id,
                'inventory_supplier_quotation_id' => $quotation?->id,
                'supplier_id' => $supplierId,
                'store_id' => $order->store_id,
                'po_number' => InventoryPurchaseOrder::generateNumber((int) $order->business_id),
                'status' => InventoryPurchaseOrder::STATUS_DRAFT,
                'created_by_user_id' => $user->id,
            ]);

            foreach ($awards as $award) {
                $lineTotal = round((float) $award->line_total, 2);
                $total += $lineTotal;

                \App\Models\InventoryPurchaseOrderLine::create([
                    'inventory_purchase_order_id' => $po->id,
                    'inventory_order_line_id' => $award->inventory_order_line_id,
                    'item_id' => $award->inventoryOrderLine?->item_id
                        ?? $order->lines->firstWhere('id', $award->inventory_order_line_id)?->item_id,
                    'quantity_suom' => $award->awarded_quantity_suom,
                    'unit_price' => $award->unit_price,
                    'line_total' => $lineTotal,
                ]);
            }

            $po->update(['total_amount' => round($total, 2)]);

            InventoryProcurementAudit::log(
                'lpo_created',
                $po->fresh(),
                'LPO '.$po->po_number.' created from per-item supplier selections',
                why: 'Generated from RFQ line awards (partial supply supported)',
                newValues: [
                    'status' => $po->status,
                    'total_amount' => $po->total_amount,
                    'supplier_id' => $supplierId,
                    'rfq' => $order->order_number,
                ],
            );

            return $po;
        });
    }

    private function findQuotationLine(
        InventoryOrder $order,
        int $orderLineId,
        int $supplierId
    ): ?InventorySupplierQuotationLine {
        $quotation = $order->supplierQuotations->firstWhere('supplier_id', $supplierId);

        if (! $quotation) {
            return null;
        }

        return $quotation->lines->firstWhere('inventory_order_line_id', $orderLineId);
    }
}
