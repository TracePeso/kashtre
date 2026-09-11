<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\EntitlementBalance;

/**
 * Prepaid care-package balances — SRD v6.0 §6.3.
 *
 * Registration (Main → Clinical when a package is sold) and consumption
 * (automatic, inside Clinical's own order-placement pipeline) already exist
 * and need no caller-side wiring — see PackageTrackingService::store()
 * (via ClinicalModuleIntegrationService::notifyEntitlementsGranted()) and
 * EntitlementConsumptionEngine::consume(), which OrderPlacementService
 * already calls on every order. What was missing is purely this: a way for
 * a clinician to *see* what a patient still has left before ordering, which
 * is all this gateway is for.
 */
interface EntitlementGateway
{
    /**
     * Every package-service balance line this patient currently has,
     * spent or not — a clinician reading "0 remaining" is as informative
     * as "3 remaining".
     *
     * @return array<int, EntitlementBalance>
     */
    public function balancesFor(ClinicalActor $actor, string $patientId): array;
}
