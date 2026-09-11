<?php

namespace App\Console\Commands;

use App\Domain\Time\Models\TimeAudit;
use App\Domain\Time\Services\SharedTimeGateway;
use App\Domain\Time\ValueObjects\LocalDate;
use App\Models\Business;
use Illuminate\Console\Command;

class RunTimeDayBoundaryCommand extends Command
{
    protected $signature = 'time:day-boundary
        {--tenant= : Optional tenant_key / business id}
        {--date= : Local business date YYYY-MM-DD (defaults to yesterday in tenant TZ)}';

    protected $description = 'Execute recoverable day-boundary work for Shared Time Engine tenants';

    public function handle(SharedTimeGateway $time): int
    {
        $tenants = $this->option('tenant')
            ? collect([(string) $this->option('tenant')])
            : Business::query()->pluck('id')->map(fn ($id) => (string) $id)->push('SYSTEM');

        foreach ($tenants as $tenantKey) {
            $resolved = $time->resolveTimezone($tenantKey === 'SYSTEM' ? null : $tenantKey);
            $today = $time->businessDate($tenantKey === 'SYSTEM' ? null : $tenantKey);
            $target = $this->option('date')
                ? LocalDate::parse($this->option('date'))
                : $today->addDays(-1);

            $window = $time->businessDates()->localDayWindow(
                $target,
                $resolved->ianaId,
                $time->businessDates()->rolloverOffsetMinutes($tenantKey === 'SYSTEM' ? null : $tenantKey),
            );

            TimeAudit::query()->create([
                'tenant_key' => $tenantKey,
                'action' => 'DAY_BOUNDARY_RUN',
                'object_type' => 'business_date',
                'object_public_id' => $target->toString(),
                'after' => [
                    'localDate' => $target->toString(),
                    'ianaId' => $resolved->ianaId->value(),
                    'startUtc' => $window['start']->toIso8601(),
                    'endUtc' => $window['end']->toIso8601(),
                ],
                'reason' => 'Scheduled day-boundary execution',
                'correlation_id' => (string) \Illuminate\Support\Str::ulid(),
            ]);

            $this->info("Boundary {$tenantKey} {$target->toString()} [{$window['start']->toIso8601()}, {$window['end']->toIso8601()})");
        }

        return self::SUCCESS;
    }
}
