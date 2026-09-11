<?php

namespace App\Services;

use App\Models\BalanceHistory;
use App\Models\Business;
use App\Models\Client;
use App\Models\ContractorBalanceHistory;
use App\Models\ContractorProfile;
use App\Models\ThirdPartyPayer;
use App\Models\ThirdPartyPayerBalanceHistory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Standard balance definitions used across account statements:
 * - Available balance = total credits − total debits (ledger rows that affect the wallet)
 * - Total balance = available balance + funds held in suspense
 */
class AccountBalanceSummaryService
{
    /**
     * @return array{
     *     available_balance: float,
     *     suspense_balance: float,
     *     total_balance: float,
     *     total_credits: float,
     *     total_debits: float,
     *     ledger_balance: float
     * }
     */
    public function forClient(Client $client): array
    {
        $credits = (float) BalanceHistory::query()
            ->where('client_id', $client->id)
            ->where('transaction_type', 'credit')
            ->affectingClientBalance()
            ->sum('change_amount');

        $debits = abs((float) BalanceHistory::query()
            ->where('client_id', $client->id)
            ->where('transaction_type', 'debit')
            ->affectingClientBalance()
            ->sum('change_amount'));

        $availableBalance = $credits - $debits;
        $suspenseBalance = (float) ($client->suspense_balance ?? 0);
        $totalBalance = $availableBalance + $suspenseBalance;

        $ledgerBalance = (float) (BalanceHistory::query()
            ->where('client_id', $client->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('new_balance') ?? $client->balance ?? 0);

        return $this->package($availableBalance, $suspenseBalance, $totalBalance, $credits, $debits, $ledgerBalance);
    }

    /**
     * @return array{
     *     available_balance: float,
     *     suspense_balance: float,
     *     total_balance: float,
     *     total_credits: float,
     *     total_debits: float,
     *     ledger_balance: float
     * }
     */
    public function forThirdPartyPayer(ThirdPartyPayer $payer): array
    {
        $credits = (float) ThirdPartyPayerBalanceHistory::query()
            ->where('third_party_payer_id', $payer->id)
            ->where('transaction_type', 'credit')
            ->sum('change_amount');

        $debits = abs((float) ThirdPartyPayerBalanceHistory::query()
            ->where('third_party_payer_id', $payer->id)
            ->where('transaction_type', 'debit')
            ->sum('change_amount'));

        $availableBalance = $credits - $debits;
        // Insurer/vendor payer ledger has no separate suspense wallet; all movements hit new_balance immediately.
        $suspenseBalance = 0.0;
        $totalBalance = $availableBalance + $suspenseBalance;

        $ledgerBalance = (float) (ThirdPartyPayerBalanceHistory::query()
            ->where('third_party_payer_id', $payer->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('new_balance') ?? $payer->current_balance ?? 0);

        return $this->package($availableBalance, $suspenseBalance, $totalBalance, $credits, $debits, $ledgerBalance);
    }

    /**
     * @return array{
     *     available_balance: float,
     *     suspense_balance: float,
     *     total_balance: float,
     *     total_credits: float,
     *     total_debits: float,
     *     ledger_balance: float
     * }
     */
    public function forContractor(ContractorProfile $contractor): array
    {
        $credits = (float) ContractorBalanceHistory::query()
            ->where('contractor_profile_id', $contractor->id)
            ->whereIn('type', ['credit', 'package'])
            ->sum('amount');

        $debits = (float) ContractorBalanceHistory::query()
            ->where('contractor_profile_id', $contractor->id)
            ->where('type', 'debit')
            ->sum('amount');

        $availableBalance = $credits - $debits;
        $suspenseBalance = 0.0;
        $totalBalance = $availableBalance + $suspenseBalance;

        $ledgerBalance = (float) (ContractorBalanceHistory::query()
            ->where('contractor_profile_id', $contractor->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('new_balance') ?? $contractor->account_balance ?? 0);

        return $this->package($availableBalance, $suspenseBalance, $totalBalance, $credits, $debits, $ledgerBalance);
    }

    /**
     * @return array{
     *     available_balance: float,
     *     withdrawal_suspense_balance: float,
     *     total_balance: float,
     *     total_credits: float,
     *     total_debits: float
     * }
     */
    public function forBusiness(Business $business): array
    {
        $calculated = app(MoneyTrackingService::class)->calculateBusinessAvailableBalance($business);

        return [
            'available_balance' => (float) $calculated['availableBalance'],
            'withdrawal_suspense_balance' => (float) $calculated['withdrawalSuspenseBalance'],
            'suspense_balance' => (float) $calculated['withdrawalSuspenseBalance'],
            'total_balance' => (float) $calculated['totalBalance'],
            'total_credits' => (float) $calculated['totalCredits'],
            'total_debits' => (float) $calculated['totalDebits'],
        ];
    }

    public function enrichClientBalanceHistories(LengthAwarePaginator|Collection $histories, Client $client): LengthAwarePaginator|Collection
    {
        $suspenseBalance = (float) ($client->suspense_balance ?? 0);

        $items = $histories instanceof LengthAwarePaginator
            ? $histories->getCollection()
            : $histories;

        $items->transform(function (BalanceHistory $history) use ($suspenseBalance) {
            $availableAfter = (float) ($history->new_balance ?? 0);
            $history->setAttribute('available_balance_after', $availableAfter);
            $history->setAttribute('total_balance_after', $availableAfter + $suspenseBalance);

            return $history;
        });

        return $histories;
    }

    public function enrichThirdPartyPayerHistories(LengthAwarePaginator|Collection $histories): LengthAwarePaginator|Collection
    {
        $items = $histories instanceof LengthAwarePaginator
            ? $histories->getCollection()
            : $histories;

        $items->transform(function (ThirdPartyPayerBalanceHistory $history) {
            $availableAfter = (float) ($history->new_balance ?? 0);
            $history->setAttribute('available_balance_after', $availableAfter);
            $history->setAttribute('total_balance_after', $availableAfter);

            return $history;
        });

        return $histories;
    }

    /**
     * @return array{
     *     available_balance: float,
     *     suspense_balance: float,
     *     total_balance: float,
     *     total_credits: float,
     *     total_debits: float,
     *     ledger_balance: float
     * }
     */
    protected function package(
        float $availableBalance,
        float $suspenseBalance,
        float $totalBalance,
        float $totalCredits,
        float $totalDebits,
        float $ledgerBalance
    ): array {
        return [
            'available_balance' => round($availableBalance, 2),
            'suspense_balance' => round($suspenseBalance, 2),
            'total_balance' => round($totalBalance, 2),
            'total_credits' => round($totalCredits, 2),
            'total_debits' => round($totalDebits, 2),
            'ledger_balance' => round($ledgerBalance, 2),
        ];
    }
}
