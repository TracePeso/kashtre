<?php

namespace App\Providers;

use App\Contracts\LabResultsBroker;
use App\Services\Clinical\Dispatch\LocalFactReceiverRegistry;
use App\Services\Clinical\Integration\LimsIntegrationProxyService;
use App\Services\Clinical\Integration\StubLimsClient;
use Illuminate\Support\ServiceProvider;

/**
 * Clinical Module Chunk 7: binds LabResultsBroker to the stub (swap for
 * an HTTP-backed implementation once a real LIMS exists — same idiom as
 * DicomWorklistBroker) and registers both directions of the
 * Clinical<->LIMS integration against the 'local' dispatch driver's
 * receiver registry.
 *
 * Cancel-order isn't wired to a fact/receiver here — LabResultsBroker
 * still exposes cancelOrder() for a future real implementation, but
 * nothing in this chunk's UI/tests exercises it yet.
 */
class ClinicalLimsIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LabResultsBroker::class, StubLimsClient::class);
    }

    public function boot(): void
    {
        $registry = $this->app->make(LocalFactReceiverRegistry::class);

        // Clinical -> LIMS: place a lab order.
        $registry->register('lims', 'lab-order-placed', function (array $payload) {
            return $this->app->make(LabResultsBroker::class)->placeOrder($payload);
        });

        // LIMS -> Clinical: result validated / critical result / reagent consumption.
        $proxy = $this->app->make(LimsIntegrationProxyService::class);
        $registry->register('clinical', 'lab-result-validated', [$proxy, 'handleResultValidated']);
        $registry->register('clinical', 'lab-critical-result', [$proxy, 'handleCriticalResult']);
        $registry->register('clinical', 'lab-reagent-consumption', [$proxy, 'handleReagentConsumption']);
    }
}
