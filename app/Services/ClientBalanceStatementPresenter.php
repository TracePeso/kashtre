<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ClientBalanceStatementPresenter
{
    public static function summaryBalances(Client $client): array
    {
        return app(AccountBalanceSummaryService::class)->forClient($client);
    }

    public static function enrichHistories(LengthAwarePaginator|Collection $histories, Client $client): LengthAwarePaginator|Collection
    {
        return app(AccountBalanceSummaryService::class)->enrichClientBalanceHistories($histories, $client);
    }
}
