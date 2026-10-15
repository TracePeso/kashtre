<?php

namespace App\Services;

use App\Http\Controllers\InvoiceController;
use App\Models\AccountsReceivable;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

/**
 * Applies a later approve/reject from the insurer's manual clearance queue
 * onto an invoice that was saved as pending_review.
 */
class InsuranceAuthorizationDecisionService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function apply(Invoice $invoice, array $payload): void
    {
        $snapshot = is_array($invoice->insurance_authorization_snapshot)
            ? $invoice->insurance_authorization_snapshot
            : [];

        $decision = (string) ($payload['decision'] ?? '');
        $decidedAt = $payload['decided_at'] ?? now()->toIso8601String();
        $reason = $payload['rejection_reason'] ?? 'Rejected by insurer';

        if (! empty($snapshot['multi_vendor']) && ! empty($snapshot['vendors']) && is_array($snapshot['vendors'])) {
            $snapshot = $this->applyToVendor($snapshot, $payload, $decision, (string) $reason, (string) $decidedAt);
        } else {
            $snapshot = $this->applyToSingle($invoice, $snapshot, $payload, $decision, (string) $reason, (string) $decidedAt);
        }

        $snapshot['decided_at'] = $decidedAt;
        $status = (string) ($snapshot['authorization_status'] ?? '');

        $invoice->update([
            'insurance_authorization_reference' => $payload['authorization_reference'] ?? $invoice->insurance_authorization_reference,
            'insurance_client_total' => $snapshot['client_total'] ?? $invoice->insurance_client_total,
            'insurance_insurance_total' => $snapshot['insurance_total'] ?? $invoice->insurance_insurance_total,
            'insurance_authorized_at' => $decision === 'approved' ? now() : $invoice->insurance_authorized_at,
            'insurance_authorization_snapshot' => $snapshot,
        ]);

        if (in_array($status, ['rejected', 'auto_rejected'], true)) {
            $this->dropInsuranceAsPayer($invoice->fresh() ?? $invoice);
        }

        $invoice->refresh();
        $freshSnapshot = is_array($invoice->insurance_authorization_snapshot)
            ? $invoice->insurance_authorization_snapshot
            : [];
        $stillPending = ($freshSnapshot['authorization_status'] ?? '') === 'pending_review';
        $insuranceLeft = (float) ($freshSnapshot['insurance_total'] ?? 0);

        if (! $stillPending && $insuranceLeft > 0) {
            $invoice->loadMissing('client');
            $client = $invoice->client;
            $business = $client ? \App\Models\Business::query()->find($invoice->business_id) : null;
            if ($client && $business) {
                app(InvoiceController::class)->postImmediateInsuranceGuaranteeDebits(
                    $invoice,
                    $client,
                    $business,
                    $freshSnapshot
                );
            }
        }

        Log::info('[Kashtre] Manual authorization decision applied', [
            'invoice_id' => $invoice->id,
            'decision' => $decision,
            'authorization_status' => $freshSnapshot['authorization_status'] ?? $status,
            'insurance_total' => $freshSnapshot['insurance_total'] ?? null,
            'client_total' => $freshSnapshot['client_total'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyToSingle(Invoice $invoice, array $snapshot, array $payload, string $decision, string $reason, string $decidedAt): array
    {
        $snapshot['decided_at'] = $decidedAt;

        if ($decision === 'rejected') {
            $fullAmount = (float) $invoice->total_amount;
            $snapshot['authorization_status'] = 'rejected';
            $snapshot['rejection_reason'] = $reason;
            $snapshot['client_total'] = $fullAmount;
            $snapshot['insurance_total'] = 0.0;
            $snapshot['breakdown'] = is_array($snapshot['breakdown'] ?? null) ? $snapshot['breakdown'] : [];
            $snapshot['breakdown']['deductible'] = 0.0;
            $snapshot['breakdown']['copay'] = 0.0;
            $snapshot['breakdown']['coinsurance'] = 0.0;

            return $snapshot;
        }

        $previousInsurance = (float) ($snapshot['insurance_total'] ?? 0);
        $newInsurance = array_key_exists('insurance_total', $payload)
            ? (float) $payload['insurance_total']
            : (array_key_exists('approved_amount', $payload) ? (float) $payload['approved_amount'] : $previousInsurance);
        $diff = round($previousInsurance - $newInsurance, 2);

        $snapshot['authorization_status'] = 'approved';
        $snapshot['insurance_total'] = $newInsurance;
        $snapshot['client_total'] = round((float) ($snapshot['client_total'] ?? 0) + max(0, $diff), 2);
        if (isset($payload['breakdown']) && is_array($payload['breakdown'])) {
            $snapshot['breakdown'] = $payload['breakdown'];
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyToVendor(array $snapshot, array $payload, string $decision, string $reason, string $decidedAt): array
    {
        $reference = trim((string) ($payload['authorization_reference'] ?? ''));
        $index = $this->matchingVendorIndex($snapshot['vendors'], $reference);

        if ($index === null) {
            Log::warning('[Kashtre] Manual authorization decision: vendor row not matched', [
                'authorization_reference' => $reference,
            ]);
            $snapshot['authorization_status'] = $decision === 'approved' ? 'approved' : 'rejected';
            $snapshot['decided_at'] = $decidedAt;

            return $snapshot;
        }

        $vendor = $snapshot['vendors'][$index];
        $previousInsurance = (float) ($vendor['insurance_total'] ?? 0);

        if ($decision === 'rejected') {
            $vendor['authorization_status'] = 'rejected';
            $vendor['rejection_reason'] = $reason;
            $vendor['insurance_total'] = 0.0;
            $vendor['client_total'] = round((float) ($vendor['client_total'] ?? 0) + $previousInsurance, 2);
            $diff = $previousInsurance;
        } else {
            $newInsurance = array_key_exists('insurance_total', $payload)
                ? (float) $payload['insurance_total']
                : (array_key_exists('approved_amount', $payload) ? (float) $payload['approved_amount'] : $previousInsurance);
            $diff = round($previousInsurance - $newInsurance, 2);
            $vendor['authorization_status'] = 'approved';
            $vendor['insurance_total'] = $newInsurance;
            $vendor['client_total'] = round((float) ($vendor['client_total'] ?? 0) + max(0, $diff), 2);
        }

        $vendor['decided_at'] = $decidedAt;
        $snapshot['vendors'][$index] = $vendor;
        $snapshot['insurance_total'] = round(max(0, (float) ($snapshot['insurance_total'] ?? 0) - max(0, $diff)), 2);
        $snapshot['client_total'] = round((float) ($snapshot['client_total'] ?? 0) + max(0, $diff), 2);
        $snapshot['authorization_status'] = $this->rollUp(array_values($snapshot['vendors']));
        $snapshot['decided_at'] = $decidedAt;

        if (in_array($snapshot['authorization_status'], ['rejected', 'auto_rejected'], true)) {
            $snapshot['rejection_reason'] = $reason;
        }

        return $snapshot;
    }

    /**
     * @param  array<int, mixed>  $vendors
     */
    private function matchingVendorIndex(array $vendors, string $reference): ?int
    {
        $pending = [];
        foreach ($vendors as $index => $vendor) {
            if (! is_array($vendor)) {
                continue;
            }
            $rowReference = trim((string) ($vendor['authorization_reference'] ?? ''));
            if ($reference !== '' && $rowReference !== '' && $rowReference === $reference) {
                return $index;
            }
            if (($vendor['authorization_status'] ?? '') === 'pending_review') {
                $pending[] = $index;
            }
        }

        return count($pending) === 1 ? $pending[0] : null;
    }

    /**
     * @param  list<array<string, mixed>>  $vendors
     */
    private function rollUp(array $vendors): string
    {
        $statuses = collect($vendors)->map(fn ($vendor) => (string) ($vendor['authorization_status'] ?? ''));

        if ($statuses->contains('pending_review')) {
            return 'pending_review';
        }

        $stillCovered = collect($vendors)->contains(function ($vendor) {
            $status = (string) ($vendor['authorization_status'] ?? '');
            if (in_array($status, ['rejected', 'auto_rejected', 'failed', 'skipped', 'pending_review'], true)) {
                return false;
            }

            return (float) ($vendor['insurance_total'] ?? 0) > 0;
        });

        if ($stillCovered) {
            return 'approved';
        }

        if ($statuses->contains('rejected') || $statuses->contains('auto_rejected')) {
            return 'rejected';
        }

        return 'approved';
    }

    private function dropInsuranceAsPayer(Invoice $invoice): void
    {
        $methods = $invoice->payment_methods ?? [];
        if (is_array($methods)) {
            $methods = array_values(array_filter($methods, fn ($method) => $method !== 'insurance'));
        } else {
            $methods = [];
        }
        if ($methods === []) {
            $methods = ['cash'];
        }

        $invoice->update([
            'insurance_client_total' => (float) $invoice->total_amount,
            'insurance_insurance_total' => 0.0,
            'payment_methods' => $methods,
        ]);

        try {
            AccountsReceivable::query()
                ->where('invoice_id', $invoice->id)
                ->whereNotNull('third_party_payer_id')
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('[Kashtre] Failed to remove insurer AR after manual rejection', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
