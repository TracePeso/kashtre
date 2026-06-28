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

            return $po->fresh(['lines.item', 'supplier', 'store', 'inventoryOrder', 'supplierQuotation']);
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

        if (! $config) {
            return;
        }

        $recipients = $config->financeNotificationEmailList();

        if ((bool) ($config->lpo_email_copy_to_approvers ?? true)) {
            foreach ($config->approvers as $approver) {
                if ($approver->user?->email) {
                    $recipients[] = $approver->user->email;
                }
            }
        }

        $recipients = array_values(array_unique($recipients));

        if ($recipients === []) {
            return;
        }

        $pdfContent = $this->pdfService->lpoPdfContent($po);

        foreach ($recipients as $email) {
            Mail::to($email)->send(new InventoryLpoIssuedMail($po, $pdfContent));
        }
    }
}
