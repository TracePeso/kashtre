<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\ProvisioningGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

/**
 * CLINICAL_DRIVER=api: `settings/provisioning/tenants/*` (confirmed live
 * 2026-08-19). provision() does not catch its own exceptions — a 409
 * ALREADY_PROVISIONED or a 403 PROVISIONING_ACCESS_DENIED both carry
 * specific, actionable detail (current counts; accepted vs held
 * roles/permissions) that the caller needs to read via
 * ClinicalApiException::errorCode()/errors(), not have swallowed here.
 */
class ApiProvisioningGateway implements ProvisioningGateway
{
    /** The guide's own number, not a guess — a measured run took 47s. */
    private const PROVISION_TIMEOUT_SECONDS = 180;

    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function status(ClinicalActor $actor, string $tenantId): array
    {
        return $this->client->get(
            "settings/provisioning/tenants/{$tenantId}",
            [],
            ['business_id' => $actor->businessId],
        );
    }

    public function provision(ClinicalActor $actor, string $tenantId, bool $resync = false): array
    {
        return $this->client->post(
            'settings/provisioning/tenants',
            array_filter([
                'tenant_id' => $tenantId,
                'mode' => $resync ? 'RESYNC' : null,
            ], fn ($v) => $v !== null),
            [
                'business_id' => $actor->businessId,
                'timeout' => self::PROVISION_TIMEOUT_SECONDS,
                // A re-tap while a 47-second run is in flight is the same
                // request, not two — and a genuine second provisioning
                // attempt later is a RESYNC with a different mode value
                // anyway, so it is never mistaken for this one.
                'idempotency_key' => 'provision-'.$tenantId.'-'.($resync ? 'resync' : 'initial'),
            ],
        );
    }
}
