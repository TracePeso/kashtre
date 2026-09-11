<?php

namespace App\Support\Clinical;

use App\Models\Business;

/**
 * Maps Clinical's `tenant_id` back onto our business — the reverse of
 * ClinicalRequestContext::tenantId(). Shared by every inbound Clinical
 * receiver (ClinicalEventsController, InventoryConsumptionEmitController)
 * so this logic exists once, not once per controller.
 */
class ClinicalTenantResolver
{
    public static function resolveBusinessId(?string $tenantId): ?int
    {
        if (! $tenantId) {
            return null;
        }

        // The tenant is the business id. The other two forms are only kept so
        // a delivery queued before the change still lands.
        if (ctype_digit($tenantId)) {
            return (int) $tenantId;
        }

        if (preg_match('/^TENANT-(\d+)$/', $tenantId, $matches)) {
            return (int) $matches[1];
        }

        return Business::whereRaw('UPPER(entity_code) = ?', [strtoupper($tenantId)])->value('id');
    }
}
