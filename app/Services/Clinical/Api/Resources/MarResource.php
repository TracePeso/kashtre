<?php

namespace App\Services\Clinical\Api\Resources;

/**
 * Medication Administration Record and point-of-care consumption —
 * API Integration Guide §10.4 and §12.
 *
 * Two things to hold on to:
 *
 *  - **All mar/* mutations are barred off-premises.** Expect
 *    403 ZTNA_OFFSITE_MUTATION_RESTRICTED outside the hospital subnets.
 *  - **Every administration and wastage emits a consumption fact to
 *    Inventory.** Clinical emits it, not us. Main must not also fire
 *    ConsumptionEventBroker for the same event, or stock decrements twice.
 *
 * Every mutation here carries an idempotency key derived from the dose. This
 * is the scenario §7 exists for: a ward tablet loses signal mid-request, the
 * client retries, and the patient receives the dose twice.
 */
class MarResource extends ClinicalResource
{
    /**
     * @param  string|null  $state  DUE, ADMINISTERED, HELD, REFUSED; null for all
     * @return array<int, array<string, mixed>>
     */
    public function forPatient(string $patientId, ?string $state = 'DUE', array $options = []): array
    {
        return $this->rows(
            $this->client->get("clinical/patients/{$patientId}/mar", $this->filled(['state' => $state]), $options),
            'doses'
        );
    }

    /**
     * Five-Rights verification happens on Clinical's side. A barcode mismatch
     * is refused 422 naming which right failed; a *late* dose is recorded and
     * flagged, never refused — refusing it would push the nurse to chart
     * nothing at all.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function administer(int|string $doseId, array $payload, array $options = []): array
    {
        return $this->client->post(
            "clinical/mar/doses/{$doseId}/administer",
            $this->filled($payload),
            $this->idempotent("mar-dose-{$doseId}-administer", $options),
        );
    }

    /** The patient declined the dose. */
    public function refuse(int|string $doseId, string $reasonCode, ?string $note = null, array $options = []): array
    {
        return $this->client->post(
            "clinical/mar/doses/{$doseId}/refuse",
            $this->filled(['reason_code' => $reasonCode, 'reason_note' => $note]),
            $this->idempotent("mar-dose-{$doseId}-refuse", $options),
        );
    }

    /** A clinician withheld the dose — clinically distinct from a refusal. */
    public function hold(int|string $doseId, string $reasonCode, ?string $note = null, array $options = []): array
    {
        return $this->client->post(
            "clinical/mar/doses/{$doseId}/hold",
            $this->filled(['reason_code' => $reasonCode, 'reason_note' => $note]),
            $this->idempotent("mar-dose-{$doseId}-hold", $options),
        );
    }

    /**
     * PRN ("as needed") doses have no schedule, so they are administered
     * against the order item rather than a pre-generated dose row.
     */
    public function administerPrn(int|string $orderItemId, array $payload, array $options = []): array
    {
        return $this->client->post(
            "clinical/mar/items/{$orderItemId}/administer-prn",
            $this->filled($payload),
            $options,
        );
    }

    /**
     * Drug wastage — dropped, cracked or partially used. Emits
     * MEDICATION_WASTED and requires a witness for controlled drugs.
     */
    public function waste(int|string $orderItemId, array $payload, array $options = []): array
    {
        return $this->client->post(
            "clinical/mar/items/{$orderItemId}/waste",
            $this->filled($payload),
            $options,
        );
    }

    // ---------------------------------------------------------------- consumption

    /**
     * Post-stabilisation reconciliation: what the crash cart actually used,
     * recorded after the emergency rather than during it.
     */
    public function crashCartConsumption(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/consumption/crash-cart', $this->filled($payload), $options);
    }

    /**
     * Un-prescribed floor stock. Emits NON_APPROVED_FLOOR_STOCK_USAGE, which
     * Inventory treats as an exception rather than routine depletion.
     */
    public function floorStockConsumption(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/consumption/floor-stock', $this->filled($payload), $options);
    }

    /**
     * The transactional outbox behind §12. Query `status=FAILED` when a
     * downstream module claims it never received a consumption fact — this is
     * where you find out whether Clinical sent it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function consumptionOutbox(array $query = [], array $options = []): array
    {
        return $this->rows(
            $this->client->get('clinical/consumption/outbox', $this->filled($query), $options),
            'events'
        );
    }

    // ---------------------------------------------------------------- ward tote handshake

    /**
     * Issues the five-digit code a ward nurse quotes back to pharmacy when
     * accepting a tote (§9.2).
     */
    public function issueHandshakeToken(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/handshake/issue-token', $this->filled($payload), $options);
    }

    /**
     * Verifies that code. Returns any SKUs the nurse flagged as discrepant —
     * rolling those lines back is Inventory's ledger work, not Clinical's.
     */
    public function validateHandshakeToken(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/handshake/validate-token', $this->filled($payload), $options);
    }

    // ---------------------------------------------------------------- entitlements

    /**
     * Registers a purchased package so Clinical can track consumption against
     * it and intercept excess (§9.1).
     */
    public function grantEntitlement(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/entitlements', $this->filled($payload), $options);
    }

    /**
     * What *would* be consumed, without consuming it — for showing a patient
     * their remaining cover before a service is delivered.
     */
    public function previewEntitlement(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/entitlements/preview', $this->filled($payload), $options);
    }
}
