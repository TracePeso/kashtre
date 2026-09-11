<?php

namespace App\Console\Commands;

use App\Services\HrModuleApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RefreshHrNavigation extends Command
{
    protected $signature = 'hr:refresh-navigation';
    protected $description = 'Fetch the HR module navigation menu and store it in cache';

    public function handle(HrModuleApiClient $hr): int
    {
        try {
            $nav = $hr->navigation();
            Cache::put('hr.navigation', $nav, 300);
            $this->info('HR navigation cached (' . count($nav) . ' items).');
            return 0;
        } catch (\Throwable $e) {
            $this->error('Failed to fetch HR navigation: ' . $e->getMessage());
            return 1;
        }
    }
}
