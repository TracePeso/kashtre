<?php

namespace App\Providers;

use App\Contracts\Clinical\CareAccessGateway;
use App\Contracts\Clinical\ClinicalDictionaryGateway;
use App\Contracts\Clinical\MarGateway;
use App\Contracts\Clinical\MedicationOrdersGateway;
use App\Contracts\Clinical\ObservationsGateway;
use App\Contracts\Clinical\ScratchpadGateway;
use App\Services\Clinical\Gateways\Api\ApiCareAccessGateway;
use App\Services\Clinical\Gateways\Api\ApiDictionaryGateway;
use App\Services\Clinical\Gateways\Api\ApiMarGateway;
use App\Services\Clinical\Gateways\Api\ApiMedicationOrdersGateway;
use App\Services\Clinical\Gateways\Api\ApiObservationsGateway;
use App\Services\Clinical\Gateways\Api\ApiScratchpadGateway;
use App\Services\Clinical\Gateways\Local\LocalCareAccessGateway;
use App\Services\Clinical\Gateways\Local\LocalDictionaryGateway;
use App\Services\Clinical\Gateways\Local\LocalMarGateway;
use App\Services\Clinical\Gateways\Local\LocalMedicationOrdersGateway;
use App\Services\Clinical\Gateways\Local\LocalObservationsGateway;
use App\Services\Clinical\Gateways\Local\LocalScratchpadGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * The strangler switch for the Clinical Module split.
 *
 * CLINICAL_DRIVER=local  → in-process engines against the clinical_* tables
 * CLINICAL_DRIVER=api    → HTTP calls to CLINICAL_ORCHESTRATOR
 *
 * Every consumer depends on the interface, so flipping this env var moves the
 * whole clinical surface between implementations without touching a caller.
 * That is what makes the cutover reversible: if the remote service misbehaves
 * in production, setting the driver back to `local` restores the previous
 * behaviour in one deploy rather than one revert.
 *
 * The two are not perfectly equivalent, and the differences are documented on
 * the individual gateways — chiefly that the API driver lets Clinical own
 * consumption-fact emission, ReBAC enforcement and break-glass windows, while
 * the local driver performs all three itself.
 */
class ClinicalGatewayServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array{local: class-string, api: class-string}>
     */
    private const GATEWAYS = [
        ObservationsGateway::class => [
            'local' => LocalObservationsGateway::class,
            'api' => ApiObservationsGateway::class,
        ],
        CareAccessGateway::class => [
            'local' => LocalCareAccessGateway::class,
            'api' => ApiCareAccessGateway::class,
        ],
        MedicationOrdersGateway::class => [
            'local' => LocalMedicationOrdersGateway::class,
            'api' => ApiMedicationOrdersGateway::class,
        ],
        MarGateway::class => [
            'local' => LocalMarGateway::class,
            'api' => ApiMarGateway::class,
        ],
        ClinicalDictionaryGateway::class => [
            'local' => LocalDictionaryGateway::class,
            'api' => ApiDictionaryGateway::class,
        ],
        ScratchpadGateway::class => [
            'local' => LocalScratchpadGateway::class,
            'api' => ApiScratchpadGateway::class,
        ],
    ];

    public function register(): void
    {
        $driver = $this->driver();

        foreach (self::GATEWAYS as $contract => $implementations) {
            $this->app->bind($contract, $implementations[$driver]);
        }
    }

    /**
     * Selecting `api` without a URL would fail every clinical action at
     * runtime with a 503 that looks like an outage rather than a
     * misconfiguration. Falling back to `local` keeps the system working and
     * says loudly why — a half-configured deploy should degrade to the
     * behaviour that still works, not to a dead clinical module.
     */
    private function driver(): string
    {
        $driver = (string) config('services.clinical.driver', 'local');

        if (! array_key_exists($driver, ['local' => true, 'api' => true])) {
            Log::warning("Unknown CLINICAL_DRIVER [{$driver}]; falling back to local.");

            return 'local';
        }

        if ($driver === 'api' && empty(config('services.clinical.url'))) {
            Log::error('CLINICAL_DRIVER=api but CLINICAL_MODULE_URL is empty; falling back to local.');

            return 'local';
        }

        return $driver;
    }
}
