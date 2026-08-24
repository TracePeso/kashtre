<?php

namespace App\Jobs;

use App\Models\InventoryUsageEvent;
use App\Services\Inventory\InventoryMainModuleSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BillInventoryUsageToMain implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly int $usageEventId,
    ) {
    }

    public function handle(InventoryMainModuleSyncService $sync): void
    {
        $event = InventoryUsageEvent::query()->find($this->usageEventId);

        if (! $event || ! $event->billed_main_module) {
            return;
        }

        if ($event->invoice_id && $event->main_billing_status === 'completed') {
            return;
        }

        try {
            $sync->billUsageEvent($event);
        } catch (\Throwable $e) {
            $event->forceFill([
                'main_billing_status' => 'failed',
                'main_billing_error' => $e->getMessage(),
            ])->save();

            Log::warning('BillInventoryUsageToMain failed', [
                'usage_event_id' => $this->usageEventId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
