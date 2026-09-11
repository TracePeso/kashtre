<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\ProvisioningGateway;
use App\Support\Clinical\ClinicalActor;

/**
 * CLINICAL_DRIVER=local: the local driver's dictionaries live in this
 * database's own migrations/seeders, not a runtime package a facility admin
 * triggers — there is nothing to provision here, and nothing missing
 * either, so this reports "already provisioned" rather than running a
 * no-op that would mislead an operator into thinking a real package ran.
 */
class LocalProvisioningGateway implements ProvisioningGateway
{
    public function status(ClinicalActor $actor, string $tenantId): array
    {
        return [
            'tenant_id' => $tenantId,
            'status' => 'PROVISIONED',
            'is_provisioned' => true,
            'dictionary_count' => 0,
            'populated_dictionaries' => 0,
            'total_rows' => 0,
            'empty_dictionaries' => [],
            'counts' => [],
        ];
    }

    public function provision(ClinicalActor $actor, string $tenantId, bool $resync = false): array
    {
        return [
            'status' => 'PROVISIONED',
            'mode' => $resync ? 'RESYNC' : 'PROVISION',
            'rows_created' => 0,
            'total_rows' => 0,
            'empty_dictionaries' => [],
            'counts' => [],
            'message' => 'Nothing to provision under the local clinical driver — its dictionaries come from this app\'s own migrations.',
        ];
    }
}
