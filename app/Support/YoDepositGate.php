<?php

namespace App\Support;

/**
 * Local stays simulated; non-local environments always use live Yo deposits.
 */
final class YoDepositGate
{
    public static function useLiveYoDeposits(): bool
    {
        return !app()->environment('local');
    }
}
