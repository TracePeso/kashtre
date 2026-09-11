<?php

namespace App\Console\Commands;

use App\Domain\Time\Services\SharedTimeGateway;
use Database\Seeders\Time\CoreTimeEngineSeeder;
use Illuminate\Console\Command;

class TimeEngineCutoverCommand extends Command
{
    protected $signature = 'time:cutover
        {--check : Only report readiness}
        {--seed : Seed catalogue and platform fallback}';

    protected $description = 'Shared Time Engine cutover readiness and activation helpers';

    public function handle(SharedTimeGateway $time): int
    {
        if ($this->option('seed')) {
            $this->call('db:seed', ['--class' => CoreTimeEngineSeeder::class, '--force' => true]);
        }

        $node = gethostname() ?: 'app';
        $time->tzdbHealth()->recordNodeRelease($node);
        $time->clockHealth()->recordCheck($node, 0, 'cutover');
        $scan = $time->tzdbHealth()->scan();

        $this->table(['Check', 'Status'], [
            ['SHARED_TIME_ENABLED', $time->enabled() ? 'yes' : 'NO — set SHARED_TIME_ENABLED=true'],
            ['Catalogue rows', (string) \App\Domain\Time\Models\CoreTimeZone::query()->count()],
            ['Platform policy', \App\Domain\Time\Models\TimeZonePolicy::query()->where('scope_type', 'PLATFORM')->where('status', 'ACTIVE')->exists() ? 'ACTIVE' : 'missing'],
            ['Clock health', optional($time->clockHealth()->forNode($node))->status ?? 'unknown'],
            ['TZDB release', $time->tzdbHealth()->localRelease()],
            ['TZDB mismatch', $scan['mismatch'] ? 'YES' : 'no'],
            ['Leap-second policy', (string) config('time.leap_second.policy')],
        ]);

        if ($this->option('check')) {
            return ($time->enabled() && ! $scan['mismatch']) ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Cutover helpers complete. Enable SHARED_TIME_ENABLED and wire consumers via App\\Support\\SharedTime.');

        return self::SUCCESS;
    }
}
