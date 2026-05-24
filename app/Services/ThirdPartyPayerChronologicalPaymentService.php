<?php

namespace App\Services;

use App\Models\ThirdPartyPayer;
use App\Models\ThirdPartyPayerBalanceHistory;
use Illuminate\Support\Collection;

/**
 * Enforces FIFO settlement of insurer → provider ledger debits (oldest outstanding first).
 */
class ThirdPartyPayerChronologicalPaymentService
{
    /**
     * Outstanding debits ordered for payment: service charges first, then oldest to newest.
     *
     * @return Collection<int, ThirdPartyPayerBalanceHistory>
     */
    public function outstandingDebits(ThirdPartyPayer $payer): Collection
    {
        return ThirdPartyPayerBalanceHistory::query()
            ->where('third_party_payer_id', $payer->id)
            ->where('transaction_type', 'debit')
            ->where('payment_status', 'pending_payment')
            ->where('change_amount', '!=', 0)
            ->with(['invoice', 'client'])
            ->get()
            ->sortBy(fn (ThirdPartyPayerBalanceHistory $history) => [
                $this->isServiceChargeDebit($history) ? 0 : 1,
                $history->created_at?->timestamp ?? 0,
                $history->id,
            ])
            ->values();
    }

    public function totalOutstanding(ThirdPartyPayer $payer): float
    {
        return round(
            $this->outstandingDebits($payer)->sum(fn (ThirdPartyPayerBalanceHistory $h) => $this->debitAmount($h)),
            2
        );
    }

    /**
     * @return array{
     *     entries: array<int, array<string, mixed>>,
     *     total_outstanding: float,
     *     allocations: array<int, array<string, mixed>>,
     *     amount_applied: float,
     *     amount_unapplied: float
     * }
     */
    public function previewAllocation(ThirdPartyPayer $payer, float $paymentAmount): array
    {
        $paymentAmount = round(max(0, $paymentAmount), 2);
        $entries = $this->serializeOutstanding($payer);
        $allocations = $this->buildAllocations($payer, $paymentAmount);
        $amountApplied = round(collect($allocations)->sum('amount_applied'), 2);

        return [
            'entries' => $entries,
            'total_outstanding' => $this->totalOutstanding($payer),
            'allocations' => $allocations,
            'amount_applied' => $amountApplied,
            'amount_unapplied' => round(max(0, $paymentAmount - $amountApplied), 2),
        ];
    }

    /**
     * @return array{success: true, allocations: array, amount_applied: float}|array{success: false, message: string}
     */
    public function applyAllocation(
        ThirdPartyPayer $payer,
        float $paymentAmount,
        string $paymentMethod,
        string $paymentReference,
        array $historyIds = []
    ): array {
        $paymentAmount = round(max(0, $paymentAmount), 2);

        if ($paymentAmount <= 0) {
            return ['success' => false, 'message' => 'Payment amount must be greater than zero.'];
        }

        $allocations = $this->buildAllocations($payer, $paymentAmount);

        if ($allocations === []) {
            $outstanding = $this->totalOutstanding($payer);

            if ($outstanding > 0) {
                return [
                    'success' => false,
                    'message' => 'Payment amount is too small to cover the selected items.',
                ];
            }

            return [
                'success' => true,
                'allocations' => [],
                'amount_applied' => 0.0,
            ];
        }

        $allowedIds = $historyIds !== []
            ? array_flip(array_map('intval', $historyIds))
            : null;

        $settledHistoryIds = [];

        foreach ($allocations as $allocation) {
            if (! ($allocation['fully_settled'] ?? false)) {
                continue;
            }

            $historyId = (int) ($allocation['history_id'] ?? 0);
            if ($allowedIds !== null && ! isset($allowedIds[$historyId])) {
                continue;
            }

            $history = ThirdPartyPayerBalanceHistory::query()
                ->where('third_party_payer_id', $payer->id)
                ->whereKey($historyId)
                ->first();

            if (! $history || $history->payment_status !== 'pending_payment') {
                continue;
            }

            $history->update([
                'payment_status' => 'paid',
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
            ]);

            $settledHistoryIds[] = $historyId;
        }

        $amountApplied = round(collect($allocations)->sum('amount_applied'), 2);

        return [
            'success' => true,
            'allocations' => $allocations,
            'amount_applied' => $amountApplied,
            'settled_history_ids' => $settledHistoryIds,
        ];
    }

    /**
     * @param  array<int, int|string>  $historyIds
     * @return array{
     *     valid: bool,
     *     message?: string,
     *     history_ids?: array<int, int>,
     *     selected_total?: float
     * }
     */
    public function validateSelectedEntries(ThirdPartyPayer $payer, array $historyIds): array
    {
        $orderedIds = $this->outstandingDebits($payer)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($orderedIds === []) {
            return [
                'valid' => true,
                'history_ids' => [],
                'selected_total' => 0.0,
            ];
        }

        $selected = collect($historyIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($selected === []) {
            return [
                'valid' => false,
                'message' => 'Select at least one item to clear before you continue.',
            ];
        }

        $expectedPrefix = array_slice($orderedIds, 0, count($selected));

        if ($selected !== $expectedPrefix) {
            return [
                'valid' => false,
                'message' => 'You can only clear items in list order. Select the items above the one you want to pay.',
            ];
        }

        $selectedTotal = round(
            $this->outstandingDebits($payer)
                ->whereIn('id', $selected)
                ->sum(fn (ThirdPartyPayerBalanceHistory $h) => $this->debitAmount($h)),
            2
        );

        return [
            'valid' => true,
            'history_ids' => $selected,
            'selected_total' => $selectedTotal,
        ];
    }

    /**
     * @param  array<int, int|string>  $historyIds
     * @return array{valid: bool, message?: string}
     */
    public function validateSelectionAndAmount(ThirdPartyPayer $payer, array $historyIds, float $paymentAmount): array
    {
        $paymentAmount = round(max(0, $paymentAmount), 2);

        if ($paymentAmount <= 0) {
            return ['valid' => false, 'message' => 'Payment amount must be greater than zero.'];
        }

        $selection = $this->validateSelectedEntries($payer, $historyIds);
        if (! ($selection['valid'] ?? false)) {
            return ['valid' => false, 'message' => $selection['message'] ?? 'Invalid item selection.'];
        }

        $selectedIds = $selection['history_ids'] ?? [];
        $selectedTotal = (float) ($selection['selected_total'] ?? 0);

        if ($selectedTotal <= 0) {
            return ['valid' => true];
        }

        if ($paymentAmount > $selectedTotal + 0.02) {
            return [
                'valid' => false,
                'message' => 'Payment amount cannot exceed the total of the items you selected (UGX '.number_format($selectedTotal, 2).').',
            ];
        }

        $allocations = $this->buildAllocations($payer, $paymentAmount);

        if ($allocations === []) {
            return [
                'valid' => false,
                'message' => 'Increase the payment amount to cover at least the first selected item.',
            ];
        }

        $allocationById = collect($allocations)->keyBy('history_id');

        foreach ($selectedIds as $index => $historyId) {
            $allocation = $allocationById->get($historyId);
            $isLastSelected = $index === count($selectedIds) - 1;

            if (! $allocation) {
                return [
                    'valid' => false,
                    'message' => 'Increase the payment amount to cover the selected items.',
                ];
            }

            if (! $isLastSelected && ! ($allocation['fully_settled'] ?? false)) {
                return [
                    'valid' => false,
                    'message' => 'Increase the payment amount to fully clear each selected item.',
                ];
            }
        }

        foreach ($allocations as $allocation) {
            $historyId = (int) ($allocation['history_id'] ?? 0);
            if (($allocation['fully_settled'] ?? false) && ! in_array($historyId, $selectedIds, true)) {
                return [
                    'valid' => false,
                    'message' => 'This payment would clear items you did not select. Adjust your selection or payment amount.',
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * @param  array<int, int|string>  $historyIds
     * @return array{
     *     entries: array<int, array<string, mixed>>,
     *     total_outstanding: float,
     *     selected_total: float,
     *     selected_history_ids: array<int, int>
     * }
     */
    public function selectionSummary(ThirdPartyPayer $payer, array $historyIds): array
    {
        $selection = $this->validateSelectedEntries($payer, $historyIds);

        return [
            'entries' => $this->serializeOutstanding($payer),
            'total_outstanding' => $this->totalOutstanding($payer),
            'selected_total' => (float) ($selection['selected_total'] ?? 0),
            'selected_history_ids' => $selection['history_ids'] ?? [],
            'selection_valid' => (bool) ($selection['valid'] ?? false),
            'selection_message' => $selection['message'] ?? null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildAllocations(ThirdPartyPayer $payer, float $paymentAmount): array
    {
        $remaining = round($paymentAmount, 2);
        $allocations = [];

        foreach ($this->outstandingDebits($payer) as $history) {
            if ($remaining <= 0) {
                break;
            }

            $owed = $this->debitAmount($history);
            if ($owed <= 0) {
                continue;
            }

            $applied = round(min($remaining, $owed), 2);
            $fullySettled = $applied + 0.009 >= $owed;

            $allocations[] = [
                'history_id' => $history->id,
                'invoice_id' => $history->invoice_id,
                'invoice_number' => $history->invoice?->invoice_number,
                'description' => $history->description,
                'date' => $history->created_at?->format('Y-m-d H:i'),
                'amount_owed' => $owed,
                'amount_applied' => $applied,
                'fully_settled' => $fullySettled,
                'is_service_charge' => $this->isServiceChargeDebit($history),
            ];

            if (! $fullySettled) {
                break;
            }

            $remaining = round($remaining - $applied, 2);
        }

        return $allocations;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function serializeOutstanding(ThirdPartyPayer $payer): array
    {
        return $this->outstandingDebits($payer)->map(function (ThirdPartyPayerBalanceHistory $history) {
            return [
                'id' => $history->id,
                'description' => $history->description,
                'amount' => $this->debitAmount($history),
                'date' => $history->created_at?->format('Y-m-d H:i'),
                'invoice_id' => $history->invoice_id,
                'invoice_number' => $history->invoice?->invoice_number,
                'client_name' => $history->client?->name,
                'is_service_charge' => $this->isServiceChargeDebit($history),
                'payment_status' => $history->payment_status,
            ];
        })->values()->all();
    }

    protected function debitAmount(ThirdPartyPayerBalanceHistory $history): float
    {
        return round(abs((float) $history->change_amount), 2);
    }

    protected function isServiceChargeDebit(ThirdPartyPayerBalanceHistory $history): bool
    {
        $description = strtolower((string) $history->description);

        return str_contains($description, 'service charge')
            || str_contains($description, 'service fee')
            || str_contains($description, 'vendor service charge');
    }
}
