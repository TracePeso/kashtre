<?php

namespace App\Jobs;

use App\Models\InventoryMainModuleOutbox;
use App\Services\Inventory\InventoryMainModuleSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInventoryMainModuleOutbox implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 8;

    public function __construct(
        public readonly int $outboxId,
    ) {
    }

    public function handle(InventoryMainModuleSyncService $sync): void
    {
        $row = InventoryMainModuleOutbox::query()->find($this->outboxId);

        if (! $row || $row->status === InventoryMainModuleOutbox::STATUS_SENT) {
            return;
        }

        if ($row->available_at && $row->available_at->isFuture()) {
            $this->release(max(1, now()->diffInSeconds($row->available_at)));

            return;
        }

        $sync->processOutboxRow($row);
    }
}
