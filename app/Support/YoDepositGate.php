<?php

namespace App\Support;

/**
 * When false, ac_deposit_funds (mobile money prompts) is not called; flows simulate success instead.
 * Local is always treated as non-live. Set YO_LIVE_DEPOSITS_ENABLED=true on the server for real Yo prompts.
 */
final class YoDepositGate
{
    public static function useLiveYoDeposits(): bool
    {
        if (app()->environment('local')) {
            return false;
        }

        return filter_var(config('payments.yo_live_deposits_enabled'), FILTER_VALIDATE_BOOL);
    }
}
