<?php

namespace App\Jobs;

use App\Models\Client;
use App\Services\ClinicalModuleIntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyClinicalEncounterCreated implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $clientId,
        public readonly string $visitId,
        public readonly ?string $previousVisitId = null,
    ) {
    }

    public function handle(ClinicalModuleIntegrationService $clinical): void
    {
        $client = Client::query()->find($this->clientId);

        if (! $client || $this->visitId === '') {
            return;
        }

        $clinical->notifyEncounterCreated($client, $this->visitId, $this->previousVisitId);
    }
}
