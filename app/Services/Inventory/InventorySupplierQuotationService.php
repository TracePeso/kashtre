<?php

namespace App\Services\Inventory;

use App\Models\InventoryOrder;
use App\Models\InventorySupplierQuotation;
use App\Models\InventorySupplierQuotationLine;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventorySupplierQuotationService
{
    public function supplierForRfq(InventoryOrder $order): ?array
    {
        $order->loadMissing('supplier');

        if (! $order->supplier_id) {
            return null;
        }

        return [
            'supplier_id' => (int) $order->supplier_id,
            'supplier_name' => $order->supplier?->name ?? 'Supplier',
            'lines_count' => $order->lines()->count(),
        ];
    }

    /**
     * @return Collection<int, array{supplier_id: ?int, supplier_name: string, lines_count: int}>
     */
    public function suppliersForRfq(InventoryOrder $order): Collection
    {
        $supplier = $this->supplierForRfq($order);

        return $supplier ? collect([$supplier]) : collect();
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

        $supplierId = $supplierId ?? (int) $order->supplier_id;

        if (! $supplierId) {
            throw ValidationException::withMessages([
                'supplier_id' => 'This RFQ has no supplier assigned.',
            ]);
        }

        if ((int) $order->supplier_id !== (int) $supplierId) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Quotations must be recorded for the RFQ supplier.',
            ]);
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
                ]);
            }

            if ($quotation->lines()->count() < 1) {
                throw ValidationException::withMessages([
                    'lines' => 'Enter at least one quoted line.',
                ]);
            }

            $quotation->update(['total_amount' => round($total, 2)]);

            return $quotation->fresh(['lines.item', 'supplier', 'purchaseOrder']);
        });
    }

    public function accept(InventorySupplierQuotation $quotation): InventorySupplierQuotation
    {
        if (! $quotation->canAccept()) {
            throw ValidationException::withMessages([
                'status' => 'Only received quotations can be accepted.',
            ]);
        }

        $quotation->update(['status' => InventorySupplierQuotation::STATUS_ACCEPTED]);

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

        return $quotation->fresh(['lines.item', 'supplier']);
    }
}
