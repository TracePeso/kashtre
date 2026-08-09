<?php

namespace App\Console\Commands;

use App\Services\Inventory\InventoryForensicAuditService;
use Illuminate\Console\Command;

class VerifyInventoryForensicAuditCommand extends Command
{
    protected $signature = 'inventory:verify-forensic-audit {business_id}';

    protected $description = 'Verify SHA-256 hash chain on inventory forensic audit logs (SRD A-03)';

    public function handle(InventoryForensicAuditService $audit): int
    {
        $businessId = (int) $this->argument('business_id');
        $result = $audit->verifyChain($businessId);

        if ($result['ok']) {
            $this->info('OK — checked '.$result['checked'].' row(s).');

            return self::SUCCESS;
        }

        $this->error('Chain break at id '.$result['first_break_id']);

        return self::FAILURE;
    }
}
