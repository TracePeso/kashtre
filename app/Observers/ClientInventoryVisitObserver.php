<?php

namespace App\Observers;

use App\Models\Client;
use App\Services\Inventory\InventoryVisitReactivationService;

class ClientInventoryVisitObserver
{
    public function updated(Client $client): void
    {
        if (! $client->wasChanged('visit_id') || ! filled($client->visit_id)) {
            return;
        }

        app(InventoryVisitReactivationService::class)->reactivateForClient(
            $client,
            $client->getOriginal('visit_id') ? (string) $client->getOriginal('visit_id') : null
        );
    }
}
