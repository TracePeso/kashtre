<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\EntitlementGateway;
use App\Models\ClinicalEntitlement;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\EntitlementBalance;

/**
 * CLINICAL_DRIVER=local: reads the local clinical_entitlements table —
 * the same rows EntitlementService::consume() decrements.
 */
class LocalEntitlementGateway implements EntitlementGateway
{
    public function balancesFor(ClinicalActor $actor, string $patientId): array
    {
        return ClinicalEntitlement::where('business_id', $actor->businessId)
            ->where('client_id', $patientId)
            ->orderBy('package_id')
            ->orderBy('service_code')
            ->get()
            ->map(fn (ClinicalEntitlement $entitlement) => EntitlementBalance::fromModel($entitlement))
            ->all();
    }
}
