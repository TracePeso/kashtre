<?php

namespace App\Services\Clinical;

use App\Models\ClinicalEntitlement;

/**
 * SRD §6.3 Entitlement Consumption Engine. The package itself (allocated
 * quantities) is created and sold in the Main Module — out of scope here;
 * this only consumes an existing token. No package-purchase UI exists in
 * this chunk.
 */
class EntitlementService
{
    /**
     * @return array{consumed_from_entitlement: bool, billing_required: bool, entitlement_id: ?int}
     */
    public function consume(int $businessId, string $clientId, string $serviceCode, int $qty = 1): array
    {
        $entitlement = ClinicalEntitlement::where('business_id', $businessId)
            ->where('client_id', $clientId)
            ->where('service_code', $serviceCode)
            ->where('remaining_qty', '>=', $qty)
            ->first();

        if (! $entitlement) {
            return ['consumed_from_entitlement' => false, 'billing_required' => true, 'entitlement_id' => null];
        }

        $entitlement->increment('used_qty', $qty);

        return ['consumed_from_entitlement' => true, 'billing_required' => false, 'entitlement_id' => $entitlement->id];
    }
}
