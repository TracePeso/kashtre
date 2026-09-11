<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\ConsumptionRecord;

/**
 * Point-of-care recording that a stocked item was used on a patient — the
 * "non-approved floor stock" scenario (an item taken from ward stock rather
 * than dispensed against an order). Named orders (medication, lab,
 * diagnostic) each go through their own gateway; this is for everything
 * else a nurse charts by hand.
 *
 * Under the local driver this is also the Inventory decrement. Under the
 * API driver it is not — Clinical records the fact and queues it for
 * Inventory, and nothing bills it automatically from there (see
 * ApiConsumptionGateway). By design, per the Inventory module's own
 * INTEGRATION_README.md: dispense/usage/billing stays a human action on
 * Kashtre's existing Record Usage screen, never a REST call Clinical
 * triggers directly. pendingFloorStockForBusiness() only makes those
 * chart-recorded lines visible to that human — it does not bill anything.
 * record() throws on failure either way; the caller decides what to show,
 * same posture as every other write gateway.
 */
interface ConsumptionGateway
{
    /**
     * @return array<int, ConsumptionRecord>
     */
    public function forPatient(ClinicalActor $actor, string $patientId, int $limit = 10): array;

    public function record(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        string $itemCode,
        float $quantity,
        string $factToken,
        string $usageContext,
        ?string $justificationNote = null,
    ): ConsumptionRecord;

    /**
     * Floor-stock lines recorded from the Clinical chart, facility-wide —
     * a checklist for whoever runs Record Usage, not a billing feed. Each
     * row carries `reviewed` from Main's own bookkeeping
     * (ClinicalFloorStockReview), which this gateway does not write to;
     * that happens at the point the UI marks one reviewed.
     *
     * @return array<int, array{event_id: string, patient_id: string, visit_id: ?string, item_code: string, quantity: float, recorded_by_user_id: ?int, recorded_at: ?string, reviewed: bool}>
     */
    public function pendingFloorStockForBusiness(ClinicalActor $actor, int $limit = 50): array;
}
