<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\MarGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\MarDoseRecord;

/**
 * CLINICAL_DRIVER=api: the MAR over HTTP — API Integration Guide §10.4.
 *
 * Two things change materially versus the local driver:
 *
 * 1. Clinical emits the consumption fact to Inventory, not us. Nothing here
 *    touches ConsumptionEventBroker. If both modules emitted, every dose would
 *    decrement stock twice.
 *
 * 2. Every mutation carries an idempotency key derived from the dose, because
 *    this is the exact scenario §7 describes: a ward tablet loses signal
 *    mid-request, the client retries, and without a key the patient is
 *    administered the dose twice.
 *
 * All of these are also barred off-premises and will return
 * 403 ZTNA_OFFSITE_MUTATION_RESTRICTED from outside the hospital subnets.
 */
class ApiMarGateway implements MarGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function dosesForPatient(ClinicalActor $actor, string $patientId, ?string $state = 'DUE'): array
    {
        $data = $this->client->get(
            "clinical/patients/{$patientId}/mar",
            array_filter(['state' => $state]),
            ['business_id' => $actor->businessId],
        );

        $rows = array_is_list($data) ? $data : ($data['items'] ?? $data['doses'] ?? []);

        return array_map(
            fn (array $dose) => MarDoseRecord::fromApi($dose),
            array_values(array_filter($rows, 'is_array')),
        );
    }

    public function administer(ClinicalActor $actor, int|string $doseId, array $verification = []): array
    {
        // The Five Rights barcode check happens on Clinical's side. A mismatch
        // comes back 422 naming which right failed — patient, drug, dose,
        // route or time — and that message is written for the nurse to read.
        return $this->client->post(
            "clinical/mar/doses/{$doseId}/administer",
            array_filter([
                'patient_barcode' => $verification['patient_barcode'] ?? null,
                'drug_barcode' => $verification['drug_barcode'] ?? null,
                'dose_administered' => $verification['dose_administered'] ?? null,
                'route_code' => $verification['route_code'] ?? null,
                'batch_lot' => $verification['batch_lot'] ?? null,
                'witnessed_by_user_id' => $verification['witnessed_by_user_id'] ?? null,
                'sub_store_id' => $verification['sub_store_id'] ?? null,
            ], fn ($value) => $value !== null),
            [
                'business_id' => $actor->businessId,
                // One dose is one logical administration. Deriving the key
                // from the dose id — not from the attempt — is what makes a
                // retry after a timeout replay the original instead of
                // administering again.
                'idempotency_key' => "mar-dose-{$doseId}-administer",
            ],
        );
    }

    public function hold(ClinicalActor $actor, int|string $doseId, string $reasonCode, ?string $note = null): void
    {
        $this->client->post(
            "clinical/mar/doses/{$doseId}/hold",
            array_filter(['reason_code' => $reasonCode, 'reason_note' => $note], fn ($v) => $v !== null),
            [
                'business_id' => $actor->businessId,
                'idempotency_key' => "mar-dose-{$doseId}-hold",
            ],
        );
    }

    public function refuse(ClinicalActor $actor, int|string $doseId, string $reasonCode, ?string $note = null): void
    {
        // Distinct from hold over the API: a patient declining a dose and a
        // clinician withholding it are different clinical events, even though
        // the local schema collapses both into HELD.
        $this->client->post(
            "clinical/mar/doses/{$doseId}/refuse",
            array_filter(['reason_code' => $reasonCode, 'reason_note' => $note], fn ($v) => $v !== null),
            [
                'business_id' => $actor->businessId,
                'idempotency_key' => "mar-dose-{$doseId}-refuse",
            ],
        );
    }

    public function waste(
        ClinicalActor $actor,
        int|string $orderItemId,
        float $wastedQuantity,
        string $reasonCode,
        ?int $witnessedByUserId = null,
    ): void {
        $this->client->post(
            "clinical/mar/items/{$orderItemId}/waste",
            array_filter([
                'wasted_quantity' => $wastedQuantity,
                'reason_code' => $reasonCode,
                'witnessed_by_user_id' => $witnessedByUserId,
            ], fn ($value) => $value !== null),
            [
                'business_id' => $actor->businessId,
                // Wastage is not naturally unique per order item — two vials
                // can be dropped from the same order — so the quantity and
                // reason participate in the key. Two genuinely separate
                // wastage events of the same amount and reason within the
                // 24-hour retention window would collapse into one; that is
                // the safer failure, since the alternative double-counts a
                // controlled-drug write-off.
                'idempotency_key' => sprintf(
                    'mar-waste-%s-%s-%s',
                    $orderItemId,
                    $wastedQuantity,
                    $reasonCode,
                ),
            ],
        );
    }
}
