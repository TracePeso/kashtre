<?php

namespace App\Providers;

use App\Services\Clinical\Dispatch\LocalFactReceiverRegistry;
use App\Services\Clinical\Integration\ImagingOrderReceiver;
use App\Services\Clinical\Integration\RisIntegrationProxyService;
use Illuminate\Support\ServiceProvider;

/**
 * Clinical Module Chunk 3: registers both directions of the
 * Clinical<->Imaging integration against the 'local' dispatch driver's
 * receiver registry. See LocalFactReceiverRegistry — this is the
 * "Imaging receiver registration" ModuleDispatcherServiceProvider's own
 * doc comment anticipates.
 */
class ClinicalRisIntegrationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $registry = $this->app->make(LocalFactReceiverRegistry::class);

        // Clinical -> Imaging: place a diagnostic order.
        $registry->register('imaging', 'diagnostic-order-placed', [
            $this->app->make(ImagingOrderReceiver::class), 'handle',
        ]);

        // Imaging -> Clinical: report verified / critical finding.
        $proxy = $this->app->make(RisIntegrationProxyService::class);
        $registry->register('clinical', 'report-validated', [$proxy, 'handleReportValidated']);
        $registry->register('clinical', 'critical-finding', [$proxy, 'handleCriticalFinding']);
    }
}
