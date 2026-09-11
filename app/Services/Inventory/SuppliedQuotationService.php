<?php

namespace App\Services\Inventory;

use App\Models\InventoryOrder;
use App\Models\InventoryRfqSupplier;
use App\Models\InventorySupplierQuotation;
use Illuminate\Database\Eloquent\Builder;

class SuppliedQuotationService
{
    public function __construct(
        private readonly IncomingRfqService $incomingRfqService,
    ) {}

    /**
     * @return Builder<InventorySupplierQuotation>
     */
    public function quotationsQuery(int $entityBusinessId): Builder
    {
        return InventorySupplierQuotation::query()
            ->whereHas('supplier', fn (Builder $query) => $query->where('linked_business_id', $entityBusinessId))
            ->whereHas('inventoryOrder', fn (Builder $query) => $query
                ->where('order_type', InventoryOrder::TYPE_EXTERNAL)
                ->where('status', '!=', InventoryOrder::STATUS_DRAFT))
            ->whereIn('status', [
                InventorySupplierQuotation::STATUS_RECEIVED,
                InventorySupplierQuotation::STATUS_ACCEPTED,
                InventorySupplierQuotation::STATUS_REJECTED,
            ])
            ->with([
                'supplier:id,name,business_id,linked_business_id',
                'inventoryOrder' => fn ($query) => $query->select([
                    'id',
                    'business_id',
                    'store_id',
                    'order_number',
                    'status',
                ]),
                'inventoryOrder.business:id,name,entity_code',
                'inventoryOrder.store:id,name',
                'purchaseOrder:id,inventory_supplier_quotation_id,po_number,status',
            ])
            ->latest('received_at')
            ->latest('updated_at');
    }

    public function authorizeQuotation(InventorySupplierQuotation $quotation, int $entityBusinessId): void
    {
        $quotation->loadMissing('supplier');

        if ((int) ($quotation->supplier?->linked_business_id ?? 0) !== $entityBusinessId) {
            abort(403);
        }
    }

    public function outcomeLabel(InventorySupplierQuotation $quotation): string
    {
        $invitation = $this->invitationForQuotation($quotation);

        if ($invitation) {
            return $this->incomingRfqService->statusLabel($invitation);
        }

        return match ($quotation->status) {
            InventorySupplierQuotation::STATUS_ACCEPTED => $quotation->purchaseOrder ? 'LPO issued' : 'Quote accepted',
            InventorySupplierQuotation::STATUS_REJECTED => 'Quote not selected',
            default => 'Quotation submitted',
        };
    }

    public function outcomeColor(string $label): string
    {
        return $this->incomingRfqService->statusColor($label);
    }

    public function invitationForQuotation(InventorySupplierQuotation $quotation): ?InventoryRfqSupplier
    {
        return InventoryRfqSupplier::query()
            ->where('inventory_order_id', $quotation->inventory_order_id)
            ->where('supplier_id', $quotation->supplier_id)
            ->first();
    }
}
