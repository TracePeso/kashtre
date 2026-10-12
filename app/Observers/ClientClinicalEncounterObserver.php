<?php

namespace App\Observers;

use App\Jobs\NotifyClinicalEncounterCreated;
use App\Models\Client;
use App\Support\SharedTime;
use Illuminate\Support\Str;

class ClientClinicalEncounterObserver
{
    public function created(Client $client): void
    {
        if (! filled($client->visit_id)) {
            return;
        }

        $this->dispatch($client, null);
    }

    public function updated(Client $client): void
    {
        if (! $client->wasChanged('visit_id') || ! filled($client->visit_id)) {
            return;
        }

        $previous = $client->getOriginal('visit_id');
        $this->dispatch($client, $previous ? (string) $previous : null);
    }

    private function dispatch(Client $client, ?string $previousVisitId): void
    {
        NotifyClinicalEncounterCreated::dispatch(
            $client->id,
            (string) $client->visit_id,
            SharedTime::businessToday((string) $client->business_id, (string) $client->branch_id),
            (string) Str::uuid(),
            $previousVisitId,
        )->afterResponse();
    }
}