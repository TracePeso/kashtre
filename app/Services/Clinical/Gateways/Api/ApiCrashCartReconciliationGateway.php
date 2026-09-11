<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\CrashCartReconciliationGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\CrashCartReconciliationRecord;

/**
 * CLINICAL_DRIVER=api: SRD v6.0 §13.2 over HTTP —
 * EmergencyConsumptionService::reconcileCrashCart() on Clinical's side.
 * That single call is what charts the items against the patient, decrements
 * the cart's stock and bills the account in one place — Clinical emits
 * CRASH_CART_CONSUMPTION onto its outbox afterward, which Main's own
 * ConsumptionEventBroker::emitConsumptionFact() (already built, already
 * wired for this exact fact_token — confirmed against its own
 * determineScenario() 2026-08-26) receives and acts on. Nothing here has to
 * touch Inventory directly.
 */
class ApiCrashCartReconciliationGateway implements CrashCartReconciliationGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function reconcile(
        ClinicalActor $actor,
        string $resuscitationEventId,
        string $patientId,
        ?string $visitId,
        string $crashCartStoreId,
        array $items,
        ?string $narrative = null,
    ): CrashCartReconciliationRecord {
        $data = $this->client->post('clinical/consumption/crash-cart', array_filter([
            'resuscitation_event_id' => $resuscitationEventId,
            'patient_id' => $patientId,
            'visit_id' => $visitId,
            'crash_cart_store_id' => $crashCartStoreId,
            'narrative' => $narrative,
            'recorded_by_user_id' => $actor->userId,
            'items' => $items,
        ], fn ($value) => $value !== null), [
            'business_id' => $actor->businessId,
            // One resuscitation's reconciliation is one logical action — a
            // retried submission after a dropped connection should not chart
            // the same crash cart usage twice against the same event.
            'idempotency_key' => "crash-cart-{$resuscitationEventId}",
        ]);

        return CrashCartReconciliationRecord::fromApi($data);
    }
}
