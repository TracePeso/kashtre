<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\MarDoseRecord;

/**
 * Medication Administration Record — API Integration Guide §10.4.
 *
 * Every administration and every wastage emits a consumption fact to
 * Inventory (§12). Under CLINICAL_DRIVER=api that emission is Clinical's job,
 * not ours — this module must stop decrementing stock itself, or every dose
 * is counted twice.
 *
 * All mutations here are barred off-premises, and all of them take an
 * idempotency key. A tablet that loses signal mid-administration and retries
 * without one administers the dose twice, decrements stock twice, and leaves
 * two entries on the MAR.
 */
interface MarGateway
{
    /**
     * @param  string|null  $state  DUE, ADMINISTERED, HELD, REFUSED; null for all
     * @return array<int, MarDoseRecord>
     */
    public function dosesForPatient(ClinicalActor $actor, string $patientId, ?string $state = 'DUE'): array;

    /**
     * Records an administration after the Five Rights barcode check.
     *
     * A barcode mismatch is refused, naming which right failed. A *late* dose
     * is recorded and flagged, never refused.
     *
     * @param  array{patient_barcode?: ?string, drug_barcode?: ?string, dose_administered?: ?float, route_code?: ?string, batch_lot?: ?string, witnessed_by_user_id?: ?int, sub_store_id?: ?string}  $verification
     * @return array<string, mixed> the resulting consumption/administration outcome
     */
    public function administer(
        ClinicalActor $actor,
        int|string $doseId,
        array $verification = [],
    ): array;

    public function hold(
        ClinicalActor $actor,
        int|string $doseId,
        string $reasonCode,
        ?string $note = null,
    ): void;

    public function refuse(
        ClinicalActor $actor,
        int|string $doseId,
        string $reasonCode,
        ?string $note = null,
    ): void;

    /**
     * Records drug wastage — a dropped, cracked or refused dose. Emits
     * MEDICATION_WASTED, and requires a witness for controlled drugs.
     */
    public function waste(
        ClinicalActor $actor,
        int|string $orderItemId,
        float $wastedQuantity,
        string $reasonCode,
        ?int $witnessedByUserId = null,
    ): void;
}
