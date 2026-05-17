<?php

namespace App\Services;

use App\Models\ThirdPartyPayer;
use App\Models\ThirdPartyPayerBalanceHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InsurerPortalVendorPaymentService
{
    public function __construct(
        protected InsurerPortalVendorSummaryService $summaryService,
        protected ThirdPartyVendorServiceChargeService $serviceCharges
    ) {}

    /**
     * Preview service charge for a payment amount.
     *
     * @return array{amount: float, service_charge: float, total: float, tier: ?array, schedule_source: ?string}
     */
    public function previewCharge(int $businessId, int $thirdPartyVendorId, float $amount): array
    {
        $amount = max(0, round($amount, 2));
        $insuranceCompanyId = $this->serviceCharges->resolveLocalInsuranceCompanyId($businessId, $thirdPartyVendorId);
        $result = $this->serviceCharges->calculate($businessId, $amount, $insuranceCompanyId);
        $serviceCharge = round((float) ($result['service_charge'] ?? 0), 2);

        return [
            'amount' => $amount,
            'service_charge' => $serviceCharge,
            'total' => round($amount + $serviceCharge, 2),
            'tier' => $result['tier'] ?? null,
            'schedule_source' => $result['schedule_source'] ?? null,
        ];
    }

    /**
     * Record insurer payment to provider ledger: credit payment + debit service charge.
     *
     * @param  array{amount: float, payment_method: string, reference?: ?string, notes?: ?string}  $data
     * @return array{success: true, payment: array, service_charge: float, total_paid: float, financial: array}|array{success: false, message: string}
     */
    public function recordPayment(int $businessId, int $thirdPartyVendorId, array $data): array
    {
        $payer = $this->summaryService->resolvePayer($businessId, $thirdPartyVendorId);

        if (! $payer) {
            return [
                'success' => false,
                'message' => 'No third-party payer account exists for this provider yet.',
            ];
        }

        if ($payer->status !== 'active') {
            return [
                'success' => false,
                'message' => 'This payer account is not active. Contact the service provider.',
            ];
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            return [
                'success' => false,
                'message' => 'Payment amount must be greater than zero.',
            ];
        }

        $paymentMethod = (string) ($data['payment_method'] ?? '');
        if ($paymentMethod === '') {
            return [
                'success' => false,
                'message' => 'Payment method is required.',
            ];
        }

        $preview = $this->previewCharge($businessId, $thirdPartyVendorId, $amount);
        $serviceCharge = $preview['service_charge'];
        $reference = trim((string) ($data['reference'] ?? ''));
        if ($reference === '') {
            $reference = 'IPAY-'.strtoupper(Str::random(8));
        }
        $notes = trim((string) ($data['notes'] ?? ''));

        $credit = null;
        $chargeDebit = null;

        DB::transaction(function () use (
            $payer,
            $amount,
            $serviceCharge,
            $reference,
            $notes,
            $paymentMethod,
            &$credit,
            &$chargeDebit
        ): void {
            $credit = ThirdPartyPayerBalanceHistory::recordCredit(
                $payer,
                $amount,
                'Payment to service provider',
                $reference,
                $notes !== '' ? $notes : null,
                $paymentMethod
            );
            $credit->update(['payment_reference' => $reference]);

            if ($serviceCharge > 0) {
                $chargeDebit = ThirdPartyPayerBalanceHistory::recordDebit(
                    $payer,
                    $serviceCharge,
                    'Vendor service charge on payment',
                    $reference.'-FEE',
                    null,
                    $paymentMethod
                );
                $chargeDebit->update([
                    'payment_status' => 'paid',
                    'payment_reference' => $reference.'-FEE',
                ]);
            }

            $this->syncPayerBalance($payer);
        });

        $currentBalance = (float) (ThirdPartyPayerBalanceHistory::where('third_party_payer_id', $payer->id)
            ->orderByDesc('id')
            ->value('new_balance') ?? 0);

        return [
            'success' => true,
            'payment' => [
                'reference' => $reference,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'credit_id' => $credit?->id,
                'service_charge_debit_id' => $chargeDebit?->id,
            ],
            'service_charge' => $serviceCharge,
            'total_paid' => round($amount + $serviceCharge, 2),
            'financial' => [
                'current_balance' => $currentBalance,
            ],
        ];
    }

    protected function syncPayerBalance(ThirdPartyPayer $payer): void
    {
        $latest = ThirdPartyPayerBalanceHistory::where('third_party_payer_id', $payer->id)
            ->orderByDesc('id')
            ->value('new_balance');

        if ($latest !== null) {
            $payer->update(['current_balance' => $latest]);
        }
    }
}
