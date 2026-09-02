<?php

namespace App\Services\Inventory;

use App\Models\InventoryOrder;
use App\Models\InventoryRfqSupplier;
use App\Models\InventorySupplierQuotation;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;

class IncomingRfqService
{
    public function entityHasIncomingRfqProfiles(int $entityBusinessId): bool
    {
        return Supplier::query()
            ->where('linked_business_id', $entityBusinessId)
            ->exists();
    }

    /**
     * @return Builder<InventoryRfqSupplier>
     */
    public function invitationsQuery(int $entityBusinessId): Builder
    {
        return InventoryRfqSupplier::query()
            ->whereHas('supplier', fn (Builder $query) => $query->where('linked_business_id', $entityBusinessId))
            ->whereHas('inventoryOrder', fn (Builder $query) => $query
                ->where('order_type', InventoryOrder::TYPE_EXTERNAL)
                ->where('status', '!=', InventoryOrder::STATUS_DRAFT))
            ->with([
                'supplier:id,name,business_id,linked_business_id',
                'inventoryOrder' => fn ($query) => $query->select([
                    'id',
                    'business_id',
                    'store_id',
                    'order_number',
                    'status',
                    'notes',
                    'created_at',
                ]),
                'inventoryOrder.business:id,name,entity_code',
                'inventoryOrder.store:id,name',
            ])
            ->with(['inventoryOrder.supplierQuotations' => fn ($query) => $query
                ->select(['id', 'inventory_order_id', 'supplier_id', 'status', 'total_amount', 'reference_number'])])
            ->latest('invited_at');
    }

    public function authorizeInvitation(InventoryRfqSupplier $invitation, int $entityBusinessId): void
    {
        $invitation->loadMissing('supplier');

        if ((int) ($invitation->supplier?->linked_business_id ?? 0) !== $entityBusinessId) {
            abort(403);
        }
    }

    public function statusLabel(InventoryRfqSupplier $invitation): string
    {
        $invitation->loadMissing([
            'inventoryOrder.supplierQuotations.purchaseOrder',
            'supplier',
        ]);

        $order = $invitation->inventoryOrder;
        $quotation = $order?->supplierQuotations
            ?->firstWhere('supplier_id', $invitation->supplier_id);

        if (! $order) {
            return 'Unknown';
        }

        if ($order->status === InventoryOrder::STATUS_REJECTED) {
            return 'Buyer cancelled';
        }

        if ($order->isPendingApproval()) {
            return 'Awaiting buyer approval';
        }

        if ($quotation?->purchaseOrder) {
            return 'LPO issued';
        }

        if ($quotation?->status === InventorySupplierQuotation::STATUS_ACCEPTED) {
            return 'Quote accepted';
        }

        if ($quotation?->status === InventorySupplierQuotation::STATUS_REJECTED) {
            return 'Quote not selected';
        }

        if ($quotation) {
            return 'Quotation submitted';
        }

        if ($order->canManageSupplierQuotations()) {
            return 'RFQ open';
        }

        return 'Invited';
    }

    public function statusColor(string $label): string
    {
        return match ($label) {
            'RFQ open' => 'warning',
            'Quotation submitted' => 'info',
            'Quote accepted', 'LPO issued' => 'success',
            'Quote not selected', 'Buyer cancelled' => 'danger',
            'Awaiting buyer approval' => 'gray',
            default => 'gray',
        };
    }

    public function canSubmitQuotation(InventoryRfqSupplier $invitation): bool
    {
        $invitation->loadMissing(['inventoryOrder.supplierQuotations', 'supplier']);
        $order = $invitation->inventoryOrder;

        if (! $order || ! $order->canManageSupplierQuotations()) {
            return false;
        }

        $quotation = $order->supplierQuotations
            ->firstWhere('supplier_id', $invitation->supplier_id);

        return ! $quotation || ! $quotation->isAccepted();
    }
}
