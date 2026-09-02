<?php

namespace App\Services\Inventory;

use App\Mail\InventoryOrderApprovedMail;
use App\Mail\InventoryOrderApprovalRequestedMail;
use App\Mail\InventoryRfqSentMail;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryOrder;
use App\Models\InventoryOrderApproval;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventoryRfqSupplier;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InventoryProcurementNotificationService
{
    public function __construct(
        private readonly InventoryProcurementPdfService $pdfService,
    ) {}

    /**
     * After submit: email each configured pending approver (approval_order order).
     * Also notify finance that an RFQ/internal order awaits approval.
     */
    public function notifySubmitted(InventoryOrder $order): void
    {
        $order->loadMissing(['approvals.approver', 'store', 'supplier', 'createdBy', 'business']);

        $config = $this->moduleConfig((int) $order->business_id);

        $pending = $order->approvals
            ->where('status', InventoryOrderApproval::STATUS_PENDING)
            ->sortBy('approval_order');

        if ($config?->notify_approvers_on_order_submitted ?? true) {
            foreach ($pending as $approval) {
                if ($approval->approver?->email) {
                    $this->safeSend(
                        $approval->approver->email,
                        new InventoryOrderApprovalRequestedMail($order, $approval)
                    );
                }
            }
        }

        if ($config?->notify_finance_on_order_submitted ?? true) {
            foreach ($this->financeEmails((int) $order->business_id) as $email) {
                $first = $pending->first();
                if ($first) {
                    $this->safeSend($email, new InventoryOrderApprovalRequestedMail($order, $first));
                }
            }
        }
    }

    /**
     * After one step approves: notify the next pending approver.
     */
    public function notifyNextApprover(InventoryOrder $order): void
    {
        $config = $this->moduleConfig((int) $order->business_id);

        if (! ($config?->notify_next_approver_on_approval ?? true)) {
            return;
        }

        $order->loadMissing(['approvals.approver', 'store', 'supplier', 'createdBy', 'business']);

        $next = $order->approvals
            ->where('status', InventoryOrderApproval::STATUS_PENDING)
            ->sortBy('approval_order')
            ->first();

        if ($next?->approver?->email) {
            $this->safeSend(
                $next->approver->email,
                new InventoryOrderApprovalRequestedMail($order, $next)
            );
        }
    }

    /**
     * Final approval: notify finance + configured approvers, and send RFQ PDF to supplier (external).
     */
    public function notifyFullyApproved(InventoryOrder $order, User $approver): void
    {
        $config = $this->moduleConfig((int) $order->business_id);

        $order->loadMissing(['store', 'supplier', 'createdBy', 'business', 'lines.item']);

        if ($config?->notify_on_order_fully_approved ?? true) {
            foreach ($this->financeAndApproverEmails((int) $order->business_id) as $email) {
                $this->safeSend($email, new InventoryOrderApprovedMail($order, $approver));
            }
        }

        if ($order->isExternal() && ($config?->notify_suppliers_on_rfq_approved ?? true)) {
            $this->sendRfqToAllInvitedSuppliers($order);
        }
    }

    public function sendRfqToAllInvitedSuppliers(InventoryOrder $order): void
    {
        $order->loadMissing(['invitedSuppliers', 'supplier', 'store', 'business', 'lines.item']);

        app(InventorySupplierQuotationService::class)->ensurePrimarySupplierInvited($order);
        $order->load('invitedSuppliers');

        app(InventoryOrderService::class)->refreshRfqDocument($order);
        $order->refresh();
        $pdf = $this->pdfService->rfqPdf($order)->output();

        $sent = [];

        foreach ($order->invitedSuppliers as $supplier) {
            if (! filled($supplier->email) || isset($sent[$supplier->email])) {
                continue;
            }

            if (! empty($supplier->pivot?->rfq_sent_at)) {
                continue;
            }

            $this->safeSend($supplier->email, new InventoryRfqSentMail($order, $pdf));
            $sent[$supplier->email] = true;

            InventoryRfqSupplier::query()
                ->where('inventory_order_id', $order->id)
                ->where('supplier_id', $supplier->id)
                ->update(['rfq_sent_at' => now()]);
        }

        if ($sent === [] && filled($order->supplier?->email)) {
            $this->sendRfqToSupplier($order);
        }
    }

    public function sendRfqToSupplier(InventoryOrder $order): void
    {
        $order->loadMissing(['supplier', 'store', 'business', 'lines.item']);

        $email = $order->supplier?->email;

        if (! filled($email)) {
            Log::info('RFQ not emailed: supplier has no email', [
                'order_id' => $order->id,
                'supplier_id' => $order->supplier_id,
            ]);

            return;
        }

        app(InventoryOrderService::class)->refreshRfqDocument($order);
        $order->refresh();

        $pdf = $this->pdfService->rfqPdf($order)->output();
        $this->safeSend($email, new InventoryRfqSentMail($order, $pdf));
    }

    /**
     * @return list<string>
     */
    public function financeEmails(int $businessId): array
    {
        $config = InventoryModuleConfig::query()
            ->forBusiness($businessId)
            ->active()
            ->first();

        return $config?->financeNotificationEmailList() ?? [];
    }

    /**
     * @return list<string>
     */
    public function financeAndApproverEmails(int $businessId): array
    {
        $config = InventoryModuleConfig::query()
            ->forBusiness($businessId)
            ->active()
            ->with('approvers.user')
            ->first();

        if (! $config) {
            return [];
        }

        $emails = $config->financeNotificationEmailList();

        foreach ($config->approvers as $approver) {
            if ($approver->user?->email) {
                $emails[] = $approver->user->email;
            }
        }

        return array_values(array_unique($emails));
    }

    public function supplierEmailForPurchaseOrder(InventoryPurchaseOrder $po): ?string
    {
        $po->loadMissing('supplier');

        $email = $po->supplier?->email;

        return filled($email) ? (string) $email : null;
    }

    private function safeSend(string $email, object $mailable): void
    {
        try {
            Mail::to($email)->send($mailable);
        } catch (\Throwable $e) {
            Log::warning('Procurement notification failed', [
                'email' => $email,
                'mailable' => $mailable::class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function moduleConfig(int $businessId): ?InventoryModuleConfig
    {
        return InventoryModuleConfig::query()
            ->forBusiness($businessId)
            ->active()
            ->first();
    }
}
