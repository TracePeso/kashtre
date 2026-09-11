<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\CrashCartReconciliationRecord;

/**
 * Emergency Crash Cart Post-Consumption Reconciliation — SRD v6.0 §13.2.
 *
 * A nurse uses crash cart supplies immediately during a life-threatening
 * crisis, with no prior authorisation hold — this is what happens
 * afterward: one submission that charts what was used against the
 * patient, decrements the cart's stock, and bills the patient's account,
 * rather than three separate things someone has to remember to do.
 *
 * Distinct from ConsumptionGateway::record() (the general "non-approved
 * floor stock" scenario, one item at a time, no crash-cart-specific
 * identity) — that gateway explicitly does not cover this workflow; see its
 * own docblock.
 *
 * No recentForPatient() here: reconcile() emits the same
 * CRASH_CART_CONSUMPTION fact onto the exact outbox feed
 * ConsumptionGateway::forPatient() already reads (confirmed against
 * ConsumptionController::patientEvents() 2026-08-26 — it is not scoped to
 * any one fact_token). RecordConsumption's own flowsheet already shows a
 * crash-cart reconciliation the moment this commits, so a second read path
 * here would just be the same rows twice.
 */
interface CrashCartReconciliationGateway
{
    /**
     * @param  array<int, array{inventory_sku: string, quantity_used: float}>  $items
     */
    public function reconcile(
        ClinicalActor $actor,
        string $resuscitationEventId,
        string $patientId,
        ?string $visitId,
        string $crashCartStoreId,
        array $items,
        ?string $narrative = null,
    ): CrashCartReconciliationRecord;
}
