<?php

namespace App\Console\Commands;

use App\Domain\Time\Models\ScheduleDefinition;
use App\Domain\Time\Services\SharedTimeGateway;
use App\Domain\Time\ValueObjects\LocalDate;
use Illuminate\Console\Command;

class MaterializeTimeSchedulesCommand extends Command
{
    protected $signature = 'time:materialize-schedules
        {--days=7 : How many days ahead to materialize}
        {--tenant= : Limit to one tenant}';

    protected $description = 'Materialize upcoming Shared Time Engine schedule occurrences';

    public function handle(SharedTimeGateway $time): int
    {
        $days = max(1, (int) $this->option('days'));
        $query = ScheduleDefinition::query()->where('status', 'ACTIVE');
        if ($this->option('tenant')) {
            $query->where('tenant_key', (string) $this->option('tenant'));
        }

        $total = 0;
        foreach ($query->get() as $def) {
            $from = LocalDate::parse(now($def->iana_id)->format('Y-m-d'));
            $to = $from->addDays($days);
            $created = $time->schedules()->materialize($def, $from, $to);
            $total += count($created);
            $this->line("{$def->code}: ".count($created).' occurrence(s)');
        }

        $this->info("Materialized {$total} occurrence(s).");

        return self::SUCCESS;
    }
}
