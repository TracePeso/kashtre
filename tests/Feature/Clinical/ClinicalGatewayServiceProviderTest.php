<?php

namespace Tests\Feature\Clinical;

use App\Contracts\Clinical\CareAccessGateway;
use App\Contracts\Clinical\ClinicalDictionaryGateway;
use App\Contracts\Clinical\MarGateway;
use App\Contracts\Clinical\MedicationOrdersGateway;
use App\Contracts\Clinical\ObservationsGateway;
use App\Contracts\Clinical\ScratchpadGateway;
use App\Providers\ClinicalGatewayServiceProvider;
use App\Services\Clinical\Gateways\Api\ApiMarGateway;
use App\Services\Clinical\Gateways\Api\ApiMedicationOrdersGateway;
use App\Services\Clinical\Gateways\Api\ApiObservationsGateway;
use App\Services\Clinical\Gateways\Local\LocalMarGateway;
use App\Services\Clinical\Gateways\Local\LocalObservationsGateway;
use Tests\TestCase;

/**
 * The strangler switch itself. Getting this wrong is the one failure that
 * silently sends clinical writes to the wrong place, so it is worth asserting
 * directly rather than inferring from behaviour.
 */
class ClinicalGatewayServiceProviderTest extends TestCase
{
    private function reregister(): void
    {
        (new ClinicalGatewayServiceProvider($this->app))->register();
    }

    public function test_the_local_driver_binds_the_in_process_engines(): void
    {
        config(['services.clinical.driver' => 'local']);
        $this->reregister();

        $this->assertInstanceOf(LocalObservationsGateway::class, app(ObservationsGateway::class));
        $this->assertInstanceOf(LocalMarGateway::class, app(MarGateway::class));
    }

    public function test_the_api_driver_binds_the_http_implementations(): void
    {
        config([
            'services.clinical.driver' => 'api',
            'services.clinical.url' => 'https://clinical.kashtre.test',
        ]);
        $this->reregister();

        $this->assertInstanceOf(ApiObservationsGateway::class, app(ObservationsGateway::class));
        $this->assertInstanceOf(ApiMarGateway::class, app(MarGateway::class));
        $this->assertInstanceOf(ApiMedicationOrdersGateway::class, app(MedicationOrdersGateway::class));
    }

    public function test_every_gateway_contract_is_bound_under_both_drivers(): void
    {
        $contracts = [
            ObservationsGateway::class,
            CareAccessGateway::class,
            MedicationOrdersGateway::class,
            MarGateway::class,
            ClinicalDictionaryGateway::class,
            ScratchpadGateway::class,
        ];

        foreach (['local', 'api'] as $driver) {
            config([
                'services.clinical.driver' => $driver,
                'services.clinical.url' => 'https://clinical.kashtre.test',
            ]);
            $this->reregister();

            foreach ($contracts as $contract) {
                // A contract that resolves under one driver but not the other
                // is a half-finished migration that only fails in production.
                $this->assertTrue(
                    $this->app->bound($contract),
                    "{$contract} is not bound under the {$driver} driver."
                );
            }
        }
    }

    public function test_selecting_the_api_driver_without_a_url_falls_back_to_local(): void
    {
        config([
            'services.clinical.driver' => 'api',
            'services.clinical.url' => null,
        ]);
        $this->reregister();

        // A half-configured deploy should degrade to the behaviour that still
        // works, not to a clinical module that 503s every action.
        $this->assertInstanceOf(LocalObservationsGateway::class, app(ObservationsGateway::class));
    }

    public function test_an_unrecognised_driver_falls_back_to_local(): void
    {
        config(['services.clinical.driver' => 'nonsense']);
        $this->reregister();

        $this->assertInstanceOf(LocalObservationsGateway::class, app(ObservationsGateway::class));
    }
}
