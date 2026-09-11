<?php

namespace App\Services\Clinical\Api;

use App\Services\Clinical\Api\Resources\AiResource;
use App\Services\Clinical\Api\Resources\ChartResource;
use App\Services\Clinical\Api\Resources\DiagnosticsResource;
use App\Services\Clinical\Api\Resources\InteropResource;
use App\Services\Clinical\Api\Resources\MarResource;
use App\Services\Clinical\Api\Resources\MaternityResource;
use App\Services\Clinical\Api\Resources\OrdersResource;
use App\Services\Clinical\Api\Resources\SecurityResource;
use App\Services\Clinical\Api\Resources\SettingsResource;
use App\Services\Clinical\Api\Resources\TransitionsResource;
use App\Services\Clinical\Api\Resources\WardsResource;
use App\Services\Clinical\Api\Resources\WebhooksResource;

/**
 * The complete Clinical Module API surface, grouped by domain.
 *
 *   app(ClinicalApi::class)->chart()->observations('CL-00001234');
 *   app(ClinicalApi::class)->orders()->medications([...]);
 *   app(ClinicalApi::class)->settings()->cdeRegistry();
 *
 * ## When to use this rather than a gateway
 *
 * The gateways in `App\Contracts\Clinical\*` exist so a caller can work
 * against either the in-process engines or the remote service. They cover the
 * handful of capabilities that have a local equivalent.
 *
 * This covers **everything** — including the large majority of the API that
 * has no local counterpart at all (FHIR, maternity, transitions, device
 * enrollment, the surveillance feed, the settings dictionaries). Calls made
 * through here always go over HTTP and always require the Clinical Module to
 * be configured and reachable.
 *
 * If a capability has a gateway, prefer the gateway. If it does not, use this.
 *
 * ## Finding gaps
 *
 * `php artisan clinical:probe` walks the endpoint catalog and reports which
 * routes answer, which are missing and which are unimplemented on the far
 * side. That is the fastest way to see how much of this contract the deployed
 * service actually honours.
 */
class ClinicalApi
{
    /** @var array<string, object> */
    private array $resources = [];

    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    /** Direct access, for endpoints not yet wrapped or for ad-hoc calls. */
    public function client(): ClinicalApiClient
    {
        return $this->client;
    }

    /** §2 — liveness. The only unauthenticated endpoint. */
    public function health(): array
    {
        return $this->client->health();
    }

    /** §10.2, §10.8 — observations, allergies, diagnoses, immunizations. */
    public function chart(): ChartResource
    {
        return $this->resolve(ChartResource::class);
    }

    /** §10.3 — prescribing, the CDSS shield, order sets. */
    public function orders(): OrdersResource
    {
        return $this->resolve(OrdersResource::class);
    }

    /** §10.4 — the MAR, consumption facts, the ward tote handshake. */
    public function mar(): MarResource
    {
        return $this->resolve(MarResource::class);
    }

    /** §10.5 — beds, ward census, worklists. */
    public function wards(): WardsResource
    {
        return $this->resolve(WardsResource::class);
    }

    /** §10.6 — admission, transfer, discharge, referral, death certification. */
    public function transitions(): TransitionsResource
    {
        return $this->resolve(TransitionsResource::class);
    }

    /** Diagnostics, critical alerts, device telemetry, clinical scores. */
    public function diagnostics(): DiagnosticsResource
    {
        return $this->resolve(DiagnosticsResource::class);
    }

    /** §10.7 — scratchpad and AI assistance. Propose, never auto-chart. */
    public function ai(): AiResource
    {
        return $this->resolve(AiResource::class);
    }

    /** §13 — IPS documents and the read-only FHIR R4 interface. */
    public function interop(): InteropResource
    {
        return $this->resolve(InteropResource::class);
    }

    /** §10.8 — the maternity birth event and chronic recall. */
    public function maternity(): MaternityResource
    {
        return $this->resolve(MaternityResource::class);
    }

    /** §8, §10.1 — access gates, devices, surveillance, audit trail. */
    public function security(): SecurityResource
    {
        return $this->resolve(SecurityResource::class);
    }

    /** §10.9 — the tenant-configurable dictionaries. Never hardcode these. */
    public function settings(): SettingsResource
    {
        return $this->resolve(SettingsResource::class);
    }

    /**
     * §11 — the HMAC-signed LIMS/RIS callbacks.
     *
     * These belong to those modules, not to Main. Exposed for integration
     * testing and for standing in for a not-yet-deployed engine in staging.
     */
    public function webhooks(): WebhooksResource
    {
        return $this->resolve(WebhooksResource::class);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function resolve(string $class): object
    {
        // @phpstan-ignore-next-line — memoised so repeated calls in one request
        // do not allocate a new resource object each time.
        return $this->resources[$class] ??= new $class($this->client);
    }
}
