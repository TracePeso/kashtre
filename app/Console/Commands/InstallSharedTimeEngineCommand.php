<?php

namespace App\Console\Commands;

use Database\Seeders\Time\CoreTimeEngineSeeder;
use Illuminate\Console\Command;

class InstallSharedTimeEngineCommand extends Command
{
    protected $signature = 'time:install
        {--migrate : Run pending migrations before seeding}';

    protected $description = 'Install Shared Time Engine catalogue, platform fallback policy, and clock health seed';

    public function handle(): int
    {
        if ($this->option('migrate')) {
            $this->info('Running migrations…');
            $this->call('migrate', ['--force' => true]);
        }

        $this->info('Seeding Shared Time Engine…');
        $this->call('db:seed', ['--class' => CoreTimeEngineSeeder::class, '--force' => true]);

        $this->info('Done. Set SHARED_TIME_ENABLED=true to activate consumer gateways.');
        $this->line('Cutover check: php artisan time:cutover --check');
        $this->line('Consumers: App\\Support\\SharedTime::now() / businessToday() / captureSnapshot()');

        return self::SUCCESS;
    }
}
