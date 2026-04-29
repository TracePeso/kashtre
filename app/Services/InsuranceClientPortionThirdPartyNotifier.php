<?php

namespace App\Services;

use App\Models\InsuranceCompany;
use App\Models\Invoice;
use App\Models\ThirdPartyPayerBalanceHistory;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

/**
 * When an insurance client's portion is paid on Kashtre, records the payment on the third-party
 * insurer app (payments list + client account). Used after MM confirmation (cron) and for cash.
 *
 * Multi-vendor: for cascade invoices, each vendor is notified using a recalibrated
 * client_portion_allocated (sums to the final collected client amount), with vendor-specific
 * authorization_reference so deductible ledgers are updated without double-counting payments.
 */
class InsuranceClientPortionThirdPartyNotifier
{
    public static function notifyIfApplicable(Invoice $invoice, Transaction $transaction): void
    {
        try {
            $snapshot = $invoice->insurance_authorization_snapshot;
            if (!is_array($snapshot) || empty($snapshot)) {
                return;
            }

            $service = new ThirdPartyApiService();
            $method  = $transaction->method ?? 'mobile_money';
            if (!in_array($method, ['cash', 'bank_transfer', 'mobile_money', 'cheque', 'card', 'credit', 'other'], true)) {
                $method = 'mobile_money';
            }
            $paymentReference = 'CP-' . $transaction->created_at->format('Ymd') . '-' . $transaction->id;

            // ── Multi-vendor cascade ─────────────────────────────────────────────
            if (!empty($snapshot['multi_vendor'])) {
                self::notifyMultiVendor($invoice, $transaction, $snapshot, $service, $method, $paymentReference);
                return;
            }

            // ── Legacy single-vendor ─────────────────────────────────────────────
            $client = $invoice->client;
            if (!$client || !$client->insurance_company_id) {
                return;
            }

            $localInsurance = InsuranceCompany::find($client->insurance_company_id);
            if (!$localInsurance || !$localInsurance->third_party_business_id) {
                return;
            }

            $vendor = \App\Models\ThirdPartyPayer::where('insurance_company_id', $client->insurance_company_id)
                ->where('business_id', $invoice->business_id)
                ->first();
            if ($vendor && ($vendor->isSuspended() || $vendor->isBlocked())) {
                Log::warning('InsuranceClientPortionThirdPartyNotifier: vendor is suspended/blocked', [
                    'invoice_id'   => $invoice->id,
                    'vendor_status' => $vendor->status,
                ]);
                return;
            }

            $policyNumber = trim((string) ($client->policy_number ?? ''));
            if ($policyNumber === '') {
                return;
            }

            $clientPortion = isset($snapshot['client_total'])
                ? (float) $snapshot['client_total']
                : (float) ($invoice->insurance_client_total ?? $invoice->total_amount);

            if ($clientPortion <= 0) {
                return;
            }

            $authorizationReference = $invoice->insurance_authorization_reference
                ?? ($snapshot['authorization_reference'] ?? null);

            $payload = [
                'insurance_company_id'   => (int) $localInsurance->third_party_business_id,
                'policy_number'          => $policyNumber,
                'amount'                 => $clientPortion,
                'payment_reference'      => $paymentReference,
                'kashtre_invoice_id'     => (string) $invoice->id,
                'invoice_number'         => $invoice->invoice_number,
                'authorization_reference' => $authorizationReference,
                'connected_business_id'  => $invoice->business_id,
                'payment_method'         => $method,
                'mobile_money_number'    => $transaction->phone_number ?? null,
                'payment_date'           => now()->format('Y-m-d'),
            ];

            $result = $service->recordClientPortionPayment($payload);

            self::recordLocalVendorClientPortion(
                $invoice,
                $transaction,
                $vendor?->id,
                $clientPortion,
                $paymentReference,
                $localInsurance->name ?? 'Insurance',
                $method
            );

            Log::info('InsuranceClientPortionThirdPartyNotifier: single-vendor record-client-portion', [
                'invoice_id'    => $invoice->id,
                'transaction_id' => $transaction->id,
                'success'       => $result['success'] ?? false,
                'message'       => $result['message'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('InsuranceClientPortionThirdPartyNotifier: exception', [
                'invoice_id'     => $invoice->id ?? null,
                'transaction_id' => $transaction->id ?? null,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * For cascade invoices, notify each vendor with its allocated share of the
     * collected client payment using its own authorization_reference.
     */
    private static function notifyMultiVendor(
        Invoice $invoice,
        Transaction $transaction,
        array $snapshot,
        ThirdPartyApiService $service,
        string $method,
        string $paymentReference
    ): void {
        $vendors = $snapshot['vendors'] ?? [];
        if (empty($vendors)) {
            return;
        }

        foreach ($vendors as $index => $vendorSnap) {
            $authRef      = $vendorSnap['authorization_reference'] ?? null;
            $clientPortion = isset($vendorSnap['client_portion_allocated'])
                ? (float) $vendorSnap['client_portion_allocated']
                : (float) ($vendorSnap['client_total'] ?? 0);
            $vendorName   = $vendorSnap['vendor_name'] ?? 'Vendor ' . ($index + 1);
            $status       = $vendorSnap['authorization_status'] ?? '';
            $policyNumber = $vendorSnap['policy_number'] ?? null;

            // Skip vendors whose authorization failed or was skipped
            if (in_array($status, ['failed', 'skipped'], true) || !$authRef) {
                Log::info('InsuranceClientPortionThirdPartyNotifier: skipping vendor (no auth ref or failed)', [
                    'invoice_id'  => $invoice->id,
                    'vendor_name' => $vendorName,
                    'status'      => $status,
                ]);
                continue;
            }

            if ($clientPortion <= 0) {
                Log::info('InsuranceClientPortionThirdPartyNotifier: skipping vendor (zero client portion)', [
                    'invoice_id'  => $invoice->id,
                    'vendor_name' => $vendorName,
                ]);
                continue;
            }

            // Resolve the local third-party payer robustly (snapshot vendor_id is not always local payer id).
            $thirdPartyPayer  = self::resolveLocalThirdPartyPayer($invoice, $vendorSnap, $vendorName);
            $insuranceCompany = $thirdPartyPayer?->insuranceCompany;

            if (!$insuranceCompany || !$insuranceCompany->third_party_business_id) {
                Log::warning('InsuranceClientPortionThirdPartyNotifier: cannot resolve third_party_business_id for vendor', [
                    'invoice_id'  => $invoice->id,
                    'vendor_name' => $vendorName,
                ]);
                continue;
            }

            $payload = [
                'insurance_company_id'    => (int) $insuranceCompany->third_party_business_id,
                'policy_number'           => trim((string) ($policyNumber ?? '')),
                'amount'                  => $clientPortion,
                'payment_reference'       => $paymentReference . '-V' . ($index + 1),
                'kashtre_invoice_id'      => (string) $invoice->id,
                'invoice_number'          => $invoice->invoice_number,
                'authorization_reference' => $authRef,
                'connected_business_id'   => $invoice->business_id,
                'payment_method'          => $method,
                'mobile_money_number'     => $transaction->phone_number ?? null,
                'payment_date'            => now()->format('Y-m-d'),
            ];

            try {
                $result = $service->recordClientPortionPayment($payload);
                self::recordLocalVendorClientPortion(
                    $invoice,
                    $transaction,
                    $thirdPartyPayer?->id,
                    $clientPortion,
                    (string) $payload['payment_reference'],
                    $vendorName,
                    $method
                );
                Log::info('InsuranceClientPortionThirdPartyNotifier: multi-vendor record-client-portion', [
                    'invoice_id'    => $invoice->id,
                    'vendor_name'   => $vendorName,
                    'client_portion' => $clientPortion,
                    'success'       => $result['success'] ?? false,
                    'message'       => $result['message'] ?? null,
                ]);
            } catch (\Throwable $e) {
                Log::error('InsuranceClientPortionThirdPartyNotifier: exception for vendor', [
                    'invoice_id'  => $invoice->id,
                    'vendor_name' => $vendorName,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Resolve a local ThirdPartyPayer from cascade snapshot fields.
     */
    private static function resolveLocalThirdPartyPayer(Invoice $invoice, array $vendorSnap, string $vendorName): ?\App\Models\ThirdPartyPayer
    {
        $baseQuery = \App\Models\ThirdPartyPayer::query()
            ->where('business_id', $invoice->business_id)
            ->where('type', 'insurance_company')
            ->whereNull('client_id');

        $vendorId = $vendorSnap['vendor_id'] ?? null;
        if ($vendorId !== null) {
            // 1) Direct local payer id
            $payer = (clone $baseQuery)->where('id', (int) $vendorId)->first();
            if ($payer) {
                return $payer;
            }

            // 2) Snapshot vendor_id may actually be local insurance_company_id
            $payer = (clone $baseQuery)->where('insurance_company_id', (int) $vendorId)->first();
            if ($payer) {
                return $payer;
            }

            // 3) Snapshot vendor_id may be third_party_business_id on InsuranceCompany
            $insuranceCompany = \App\Models\InsuranceCompany::query()
                ->where('business_id', $invoice->business_id)
                ->where('third_party_business_id', (int) $vendorId)
                ->first();
            if ($insuranceCompany) {
                $payer = (clone $baseQuery)->where('insurance_company_id', $insuranceCompany->id)->first();
                if ($payer) {
                    return $payer;
                }
            }
        }

        // 4) Fallback by vendor name (most stable in snapshot rows)
        if ($vendorName !== '') {
            $payer = (clone $baseQuery)->where('name', $vendorName)->first();
            if ($payer) {
                return $payer;
            }
        }

        return null;
    }

    /**
     * Mirror per-vendor client allocations in local vendor statements, idempotently.
     */
    private static function recordLocalVendorClientPortion(
        Invoice $invoice,
        Transaction $transaction,
        ?int $thirdPartyPayerId,
        float $amount,
        string $reference,
        string $vendorName,
        string $method
    ): void {
        if (!$thirdPartyPayerId || $amount <= 0) {
            return;
        }

        $alreadyRecorded = ThirdPartyPayerBalanceHistory::where('third_party_payer_id', $thirdPartyPayerId)
            ->where('invoice_id', $invoice->id)
            ->where('transaction_type', 'credit')
            ->where('reference_number', $reference)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        $thirdPartyPayer = \App\Models\ThirdPartyPayer::find($thirdPartyPayerId);
        if (!$thirdPartyPayer) {
            return;
        }

        ThirdPartyPayerBalanceHistory::recordCredit(
            $thirdPartyPayer,
            $amount,
            'Client allocation collected for invoice ' . $invoice->invoice_number . ' (' . $vendorName . ')',
            $reference,
            'Transaction ID: ' . $transaction->id,
            $method,
            $invoice->client_id,
            $invoice->id
        );
    }
}
