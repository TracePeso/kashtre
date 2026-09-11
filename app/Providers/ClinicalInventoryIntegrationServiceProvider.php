<?php

namespace App\Providers;

use App\Services\Clinical\Dispatch\LocalFactReceiverRegistry;
use App\Services\Clinical\Integration\InventoryConsumptionReceiver;
use Illuminate\Support\ServiceProvider;

/**
 * Clinical Module Chunk 4: registers the Clinical -> Inventory
 * consumption-emit receiver against the 'local' dispatch driver.
 */
class ClinicalInventoryIntegrationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $registry = $this->app->make(LocalFactReceiverRegistry::class);

        $registry->register('inventory', 'consumption-emit', [
            $this->app->make(InventoryConsumptionReceiver::class), 'handle',
        ]);
    }
}
