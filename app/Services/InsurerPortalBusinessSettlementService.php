<?php

namespace App\Services;

use App\Models\BusinessBalanceHistory;
use App\Models\ThirdPartyPayer;
use App\Models\ThirdPartyPayerBalanceHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * When an insurer pays via the portal, mirror settlement on business (and Kashtre) balance statements
 * so pending credits become paid and available balance updates.
 *
 * Status vocabulary (not the same as third-party Payment.status = "completed"):
 * - BusinessBalanceHistory: paid | pending_payment → UI "Paid" / "Pending"
 * - ThirdPartyPayerBalanceHistory: paid | pending_payment on ledger lines
 */
class InsurerPortalBusinessSettlementService
{
    /**
     * Portal collection fee: debit insurer vendor ledger (done in payment service) and credit Kashtre as paid immediately.
     *
     * @return int|null BusinessBalanceHistory id
     */
    public function recordPortalServiceChargeCredit(
        float $amount,
        string $paymentReference,
        string $paymentMethod,
        int $providerBusinessId
    ): ?int {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return null;
        }

        $existing = BusinessBalanceHistory::query()
            ->where('business_id', 1)
            ->where('type', 'credit')
            ->where('reference_type', 'insurer_portal_payment')
            ->where('metadata->payment_reference', $paymentReference)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $kashtreAccount = app(MoneyTrackingService::class)->getOrCreateKashtreAccount();
        $method = $this->normalizeBusinessPaymentMethod($paymentMethod);

        $history = BusinessBalanceHistory::recordChange(
            1,
            $kashtreAccount->id,
            $amount,
            'credit',
            'Insurer portal vendor service charge',
            'insurer_portal_payment',
            null,
            [
                'payment_reference' => $paymentReference,
                'provider_business_id' => $providerBusinessId,
                'source' => 'insurer_portal',
            ],
            null,
            'paid',
            $method
        );

        Log::info('InsurerPortalBusinessSettlement: Kashtre service charge credited (paid)', [
            'business_balance_history_id' => $history->id,
            'amount' => $amount,
            'payment_reference' => $paymentReference,
        ]);

        return (int) $history->id;
    }

    /**
     * @param  array<int, int>  $settledVendorHistoryIds
     * @return array{business_balance_history_ids: array<int, int>}
     */
    public function settleForVendorHistories(
        ThirdPartyPayer $payer,
        array $settledVendorHistoryIds,
        string $paymentMethod,
        string $paymentReference
    ): array {
        if ($settledVendorHistoryIds === []) {
            return ['business_balance_history_ids' => []];
        }

        $histories = ThirdPartyPayerBalanceHistory::query()
            ->where('third_party_payer_id', $payer->id)
            ->whereIn('id', $settledVendorHistoryIds)
            ->where('payment_status', 'paid')
            ->with('invoice')
            ->get();

        $updatedIds = [];

        foreach ($histories as $history) {
            $updatedIds = array_merge(
                $updatedIds,
                $this->settleBusinessBalanceForVendorHistory($payer, $history, $paymentMethod, $paymentReference)
            );
        }

        return [
            'business_balance_history_ids' => array_values(array_unique($updatedIds)),
        ];
    }

    /**
     * @return array<int, int>
     */
    protected function settleBusinessBalanceForVendorHistory(
        ThirdPartyPayer $payer,
        ThirdPartyPayerBalanceHistory $history,
        string $paymentMethod,
        string $paymentReference
    ): array {
        if (! $history->invoice_id) {
            return [];
        }

        $businessId = (int) $payer->business_id;
        $amount = round(abs((float) $history->change_amount), 2);
        $description = strtolower((string) $history->description);
        $notes = strtolower((string) ($history->notes ?? ''));

        $targetBusinessId = $this->isServiceChargeDebit($description, $notes) ? 1 : $businessId;

        return $this->markSinglePendingCreditPaid(
            $targetBusinessId,
            (int) $history->invoice_id,
            $paymentMethod,
            $paymentReference,
            $amount,
            (string) $history->description
        );
    }

    /**
     * Mark exactly one pending business credit that corresponds to a settled vendor ledger line.
     *
     * @return array<int, int>
     */
    protected function markSinglePendingCreditPaid(
        int $businessId,
        int $invoiceId,
        string $paymentMethod,
        string $paymentReference,
        float $amount,
        string $vendorDescription
    ): array {
        $row = $this->findMatchingPendingCredit($businessId, $invoiceId, $amount, $vendorDescription);

        if (! $row) {
            Log::warning('InsurerPortalBusinessSettlement: no matching business balance credit for settled item', [
                'business_id' => $businessId,
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'vendor_description' => $vendorDescription,
            ]);

            return [];
        }

        return $this->applyPaidToRows(collect([$row]), $paymentMethod, $paymentReference);
    }

    protected function findMatchingPendingCredit(
        int $businessId,
        int $invoiceId,
        float $amount,
        string $vendorDescription
    ): ?BusinessBalanceHistory {
        $base = $this->pendingCreditQuery($businessId, $invoiceId)
            ->whereBetween('amount', [$amount - 0.01, $amount + 0.01])
            ->orderBy('id');

        foreach ($this->descriptionMatchPatterns($vendorDescription) as $pattern) {
            if ($pattern === '') {
                continue;
            }

            $match = (clone $base)
                ->whereRaw('LOWER(description) LIKE ?', ['%'.addcslashes($pattern, '%_\\').'%'])
                ->first();

            if ($match) {
                return $match;
            }
        }

        // Only settle when amount (and optionally description) match — never all invoice credits.
        return null;
    }

    /**
     * @return list<string>
     */
    protected function descriptionMatchPatterns(string $vendorDescription): array
    {
        $description = strtolower(trim($vendorDescription));
        $patterns = [];

        if ($description !== '' && ! str_contains($description, 'insurance guarantee for invoice')) {
            $patterns[] = $description;

            if (preg_match('/^(.+?)\s*\(x\d+\)\s*$/i', $description, $matches)) {
                $patterns[] = trim($matches[1]);
            }
        }

        return array_values(array_unique(array_filter($patterns)));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<BusinessBalanceHistory>
     */
    protected function pendingCreditQuery(int $businessId, int $invoiceId)
    {
        return BusinessBalanceHistory::query()
            ->where('business_id', $businessId)
            ->where('reference_type', 'invoice')
            ->where('reference_id', $invoiceId)
            ->where('type', 'credit')
            ->where(function ($query): void {
                $query->where('payment_status', 'pending_payment')
                    ->orWhereNull('payment_status');
            });
    }

    /**
     * @param  Collection<int, BusinessBalanceHistory>  $rows
     * @return array<int, int>
     */
    protected function applyPaidToRows(Collection $rows, string $paymentMethod, string $paymentReference): array
    {
        $method = $this->normalizeBusinessPaymentMethod($paymentMethod);
        $ids = [];

        foreach ($rows as $row) {
            $metadata = is_array($row->metadata) ? $row->metadata : [];
            $metadata['insurer_settlement_reference'] = $paymentReference;
            $metadata['insurer_settled_at'] = now()->toIso8601String();

            $row->update([
                'payment_status' => 'paid',
                'payment_method' => $method,
                'metadata' => $metadata,
            ]);

            $ids[] = (int) $row->id;
        }

        if ($ids !== []) {
            Log::info('InsurerPortalBusinessSettlement: marked business credits paid', [
                'business_balance_history_ids' => $ids,
                'payment_reference' => $paymentReference,
            ]);
        }

        return $ids;
    }

    protected function isServiceChargeDebit(string $description, string $notes): bool
    {
        foreach ([$description, $notes] as $text) {
            if (str_contains($text, 'service charge')
                || str_contains($text, 'service fee')
                || str_contains($text, 'vendor service charge')) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeBusinessPaymentMethod(string $method): string
    {
        $method = strtolower(str_replace([' ', '-'], '_', trim($method)));
        $valid = ['account_balance', 'mobile_money', 'bank_transfer', 'v_card', 'p_card', 'insurance', 'credit_arrangement'];

        return in_array($method, $valid, true) ? $method : 'insurance';
    }
}
