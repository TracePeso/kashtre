<?php

namespace App\Services\Inventory;

use App\Mail\InventoryLpoIssuedMail;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryOrder;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventoryPurchaseOrderLine;
use App\Models\InventorySupplierQuotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class InventoryPurchaseOrderService
{
    public function __construct(
        private readonly InventoryProcurementPdfService $pdfService,
    ) {}

    public function createFromQuotation(InventorySupplierQuotation $quotation, User $user): InventoryPurchaseOrder
    {
        if (! $quotation->isAccepted()) {
            throw ValidationException::withMessages([
                'status' => 'Accept the supplier quotation before generating an LPO.',
            ]);
        }

        if ($quotation->purchaseOrder) {
            throw ValidationException::withMessages([
                'status' => 'An LPO already exists for this supplier quotation.',
            ]);
        }

        $quotation->loadMissing(['lines', 'inventoryOrder']);

        return DB::transaction(function () use ($quotation, $user) {
            $order = $quotation->inventoryOrder;
            $total = 0.0;

            $po = InventoryPurchaseOrder::create([
                'business_id' => $quotation->business_id,
                'inventory_order_id' => $quotation->inventory_order_id,
                'inventory_supplier_quotation_id' => $quotation->id,
                'supplier_id' => $quotation->supplier_id,
                'store_id' => $order->store_id,
                'po_number' => InventoryPurchaseOrder::generateNumber((int) $quotation->business_id),
                'status' => InventoryPurchaseOrder::STATUS_DRAFT,
                'created_by_user_id' => $user->id,
            ]);

            foreach ($quotation->lines as $quoteLine) {
                $lineTotal = round((float) $quoteLine->line_total, 2);
                $total += $lineTotal;

                InventoryPurchaseOrderLine::create([
                    'inventory_purchase_order_id' => $po->id,
                    'inventory_order_line_id' => $quoteLine->inventory_order_line_id,
                    'item_id' => $quoteLine->item_id,
                    'quantity_suom' => $quoteLine->quoted_quantity_suom,
                    'unit_price' => $quoteLine->unit_price,
                    'line_total' => $lineTotal,
                ]);
            }

            $po->update(['total_amount' => round($total, 2)]);

            $po = $po->fresh(['lines.item', 'supplier', 'store', 'inventoryOrder', 'supplierQuotation']);

            InventoryProcurementAudit::log(
                'lpo_created',
                $po,
                'LPO '.$po->po_number.' created from accepted quotation',
                why: 'Generated from accepted supplier quotation',
                newValues: [
                    'status' => $po->status,
                    'total_amount' => $po->total_amount,
                    'rfq' => $order?->order_number,
                ],
            );

            return $po;
        });
    }

    public function issue(InventoryPurchaseOrder $po, User $user): InventoryPurchaseOrder
    {
        if ($po->status !== InventoryPurchaseOrder::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft LPOs can be issued.',
            ]);
        }

        return DB::transaction(function () use ($po, $user) {
            $po->update([
                'status' => InventoryPurchaseOrder::STATUS_ISSUED,
                'issued_at' => now(),
                'issued_by_user_id' => $user->id,
            ]);

            $order = $po->inventoryOrder;

            if ($order && $order->isRfqApproved()) {
                $order->update(['status' => InventoryOrder::STATUS_PO_ISSUED]);
            }

            $po = $po->fresh(['lines.item', 'supplier', 'store', 'inventoryOrder.business']);

            InventoryProcurementAudit::log(
                'lpo_issued',
                $po,
                'LPO '.$po->po_number.' issued',
                why: 'Issued by '.$user->name,
                newValues: [
                    'status' => $po->status,
                    'supplier_id' => $po->supplier_id,
                    'total_amount' => $po->total_amount,
                    'rfq' => $po->inventoryOrder?->order_number,
                ],
            );

            $this->emailIssuedLpo($po);

            return $po;
        });
    }

    public function emailIssuedLpo(InventoryPurchaseOrder $po): void
    {
        $config = InventoryModuleConfig::query()
            ->forBusiness((int) $po->business_id)
            ->active()
            ->with('approvers.user')
            ->first();

        if (! ($config?->notify_on_lpo_issued ?? true)) {
            return;
        }

        $recipients = $config?->financeNotificationEmailList() ?? [];

        if ($config && (bool) ($config->lpo_email_copy_to_approvers ?? true)) {
            foreach ($config->approvers as $approver) {
                if ($approver->user?->email) {
                    $recipients[] = $approver->user->email;
                }
            }
        }

        $supplierEmail = app(InventoryProcurementNotificationService::class)
            ->supplierEmailForPurchaseOrder($po);

        if ($supplierEmail) {
            $recipients[] = $supplierEmail;
        }

        $recipients = array_values(array_unique(array_filter($recipients)));

        if ($recipients === []) {
            return;
        }

        $pdfContent = $this->pdfService->lpoPdfContent($po);

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new InventoryLpoIssuedMail($po, $pdfContent));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('LPO email failed', [
                    'email' => $email,
                    'po' => $po->po_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
