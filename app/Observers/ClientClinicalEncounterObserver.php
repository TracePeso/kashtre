<?php

namespace App\Observers;

use App\Jobs\NotifyClinicalEncounterCreated;
use App\Models\Client;

class ClientClinicalEncounterObserver
{
    public function created(Client $client): void
    {
        if (! filled($client->visit_id)) {
            return;
        }

        NotifyClinicalEncounterCreated::dispatch(
            $client->id,
            (string) $client->visit_id,
            null
        )->afterResponse();
    }

    public function updated(Client $client): void
    {
        if (! $client->wasChanged('visit_id') || ! filled($client->visit_id)) {
            return;
        }

        $previous = $client->getOriginal('visit_id');

        NotifyClinicalEncounterCreated::dispatch(
            $client->id,
            (string) $client->visit_id,
            $previous ? (string) $previous : null
        )->afterResponse();
    }
}
