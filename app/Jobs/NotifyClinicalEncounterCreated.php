<?php

namespace App\Jobs;

use App\Models\Client;
use App\Services\Clinical\Api\ClinicalApiClient;
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

    public int $tries = 8;

    /** @var array<int, int> */
    public array $backoff = [5, 30, 120, 300, 900, 1800, 3600];

    public function __construct(
        public readonly int $clientId,
        public readonly string $visitId,
        public readonly string $visitDate,
        public readonly string $operationId,
        public readonly ?string $previousVisitId = null,
    ) {
    }

    public function handle(ClinicalApiClient $clinical): void
    {
        $client = Client::query()->find($this->clientId);

        if (! $client || $this->visitId === '' || (string) $client->visit_id !== $this->visitId) {
            return;
        }

        $clinical->post('replacement/patients/provision', [
            'main_client_ref' => (string) $client->uuid,
            'operation_id' => $this->operationId,
            'visit_date' => $this->visitDate,
            'visit_id' => $this->visitId,
        ], [
            'business_id' => (int) $client->business_id,
            'idempotency_key' => $this->operationId,
            'with_identity' => false,
        ]);
    }
}