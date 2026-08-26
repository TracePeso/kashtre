<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\HrModuleSyncService;
use Illuminate\Console\Command;

class PushStaffToHrModule extends Command
{
    protected $signature   = 'hr:push-staff {--business= : Limit to a specific business_id} {--chunk=50 : Batch size}';
    protected $description = 'One-time bulk push of all existing Kashtre staff to the HR module.';

    public function handle(HrModuleSyncService $hrSync): int
    {
        $businessId = $this->option('business') ? (int) $this->option('business') : null;
        $chunkSize  = (int) $this->option('chunk');

        $query = User::query()
            ->whereNotNull('business_id')
            ->where('business_id', '!=', 1); // skip Kashtre super-admin

        if ($businessId) {
            $query->where('business_id', $businessId);
        }

        $total = $query->count();
        $this->info("Pushing {$total} staff members to HR module...");

        $pushed  = 0;
        $failed  = 0;

        $query->chunk($chunkSize, function ($users) use ($hrSync, &$pushed, &$failed) {
            try {
                $hrSync->pushUsers($users);
                $pushed += $users->count();
                $this->line("  ✓ Pushed batch of {$users->count()} (total so far: {$pushed})");
            } catch (\Throwable $e) {
                $failed += $users->count();
                $this->error("  ✗ Batch failed: {$e->getMessage()}");
            }
        });

        $this->info("Done. Pushed: {$pushed} | Failed: {$failed}");

        if ($failed > 0) {
            $this->warn('Make sure the HR module is running at ' . config('services.hr_module.url'));
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
