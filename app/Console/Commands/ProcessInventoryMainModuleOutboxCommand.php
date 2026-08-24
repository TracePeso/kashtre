<?php

namespace App\Console\Commands;

use App\Jobs\ProcessInventoryMainModuleOutbox;
use App\Models\InventoryMainModuleOutbox;
use Illuminate\Console\Command;

class ProcessInventoryMainModuleOutboxCommand extends Command
{
    protected $signature = 'inventory:process-main-outbox {--limit=50}';

    protected $description = 'Process pending/failed Inventory → Main Module outbox rows (SRD §8.3)';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $rows = InventoryMainModuleOutbox::query()
            ->whereIn('status', [
                InventoryMainModuleOutbox::STATUS_PENDING,
                InventoryMainModuleOutbox::STATUS_FAILED,
            ])
            ->where(function ($q) {
                $q->whereNull('available_at')->orWhere('available_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            ProcessInventoryMainModuleOutbox::dispatchSync($row->id);
        }

        $this->info('Processed '.$rows->count().' outbox row(s).');

        return self::SUCCESS;
    }
}
