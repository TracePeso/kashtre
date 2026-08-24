<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\InventoryModuleConfig;
use App\Services\Inventory\InventoryForensicAuditService;
use Illuminate\Console\Command;

class VerifyInventoryForensicAuditCommand extends Command
{
    protected $signature = 'inventory:verify-forensic-audit {business_id?} {--all : Verify every business with an active inventory module}';

    protected $description = 'Verify SHA-256 hash chain on inventory forensic audit logs (SRD A-03)';

    public function handle(InventoryForensicAuditService $audit): int
    {
        $ids = $this->businessIds();

        if ($ids === []) {
            $this->error('Provide business_id or pass --all.');

            return self::FAILURE;
        }

        $failed = 0;

        foreach ($ids as $businessId) {
            $result = $audit->verifyChain($businessId);

            if ($result['ok']) {
                $this->info("Business {$businessId}: OK — checked {$result['checked']} row(s).");

                continue;
            }

            $failed++;
            $this->error("Business {$businessId}: chain break at id {$result['first_break_id']}");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function businessIds(): array
    {
        if ($this->option('all')) {
            return InventoryModuleConfig::query()
                ->active()
                ->pluck('business_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();
        }

        $arg = $this->argument('business_id');
        if ($arg === null || $arg === '') {
            return [];
        }

        $businessId = (int) $arg;
        if ($businessId < 1 || ! Business::query()->whereKey($businessId)->exists()) {
            $this->error("Business {$businessId} not found.");

            return [];
        }

        return [$businessId];
    }
}
