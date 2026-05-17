<?php

namespace App\Console\Commands;

use App\Services\MoneyTrackingService;
use Illuminate\Console\Command;

class ReleaseMaturedServiceCharges extends Command
{
    protected $signature = 'service-charge:release-matured {--business_id= : Limit release to one Kashtre business id}';

    protected $description = 'Release service charge holds from entity accounts to Kashtre when maturation period has elapsed';

    public function handle(MoneyTrackingService $moneyTrackingService): int
    {
        $businessId = $this->option('business_id');
        $businessId = $businessId !== null && $businessId !== '' ? (int) $businessId : null;

        $count = $moneyTrackingService->releaseMaturedServiceChargeHolds($businessId);

        $this->info("Released {$count} matured service charge hold(s).");

        return self::SUCCESS;
    }
}
