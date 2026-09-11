<?php

namespace App\Jobs;

use App\Models\InventoryHandoffToken;
use App\Services\ClinicalModuleIntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyClinicalToteStaged implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly int $handoffTokenId,
    ) {
    }

    public function handle(ClinicalModuleIntegrationService $clinical): void
    {
        $token = InventoryHandoffToken::query()->find($this->handoffTokenId);

        if (! $token || $token->used_at !== null) {
            return;
        }

        $clinical->notifyToteStaged($token);
    }
}
