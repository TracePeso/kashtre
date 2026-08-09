<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\MedicationOrdersGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\MedicationOrderRecord;
use App\Support\Clinical\SafetyEvaluation;
use Illuminate\Support\Str;

/**
 * CLINICAL_DRIVER=api: prescribing over HTTP — API Integration Guide §10.3.
 *
 * The catalogue resolution that ClinicalTranslatorEngine does locally moves to
 * Clinical's side: we send `requested_term` ("Ceftriaxone") and Clinical
 * resolves the brand SKU, calling back into *our* catalogue lookup to do it.
 * That lookup is the §14 blocker — until Main exposes it, every call here
 * answers 503, because nothing can be prescribed without a catalogue.
 * See App\Http\Controllers\API\Clinical\CatalogueLookupController.
 */
class ApiMedicationOrdersGateway implements MedicationOrdersGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function activeOrders(ClinicalActor $actor, string $patientId): array
    {
        $data = $this->client->get("clinical/patients/{$patientId}/orders", [
            'status' => 'ACTIVE',
            'order_type' => 'MEDICATION',
        ], ['business_id' => $actor->businessId]);

        $rows = array_is_list($data) ? $data : ($data['items'] ?? []);

        return array_map(
            fn (array $order) => MedicationOrderRecord::fromApi($order),
            array_values(array_filter($rows, 'is_array')),
        );
    }

    public function evaluateSafety(ClinicalActor $actor, string $patientId, array $draft): SafetyEvaluation
    {
        // The dry run (§10.3) returns 200 with a verdict rather than refusing,
        // which is what lets the UI warn before the clinician commits.
        $data = $this->client->post('clinical/cdss/evaluate', [
            'patient_id' => $patientId,
            'items' => [$this->itemPayload($draft)],
            'age_years' => $draft['age_years'] ?? null,
        ], ['business_id' => $actor->businessId]);

        return SafetyEvaluation::fromArray($data);
    }

    public function prescribe(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        array $draft,
        ?string $overrideReasonCode = null,
        ?string $overrideNote = null,
        bool $confirmExternalFulfilment = false,
        ?string $idempotencyKey = null,
    ): MedicationOrderRecord {
        $payload = array_filter([
            'patient_id' => $patientId,
            'visit_id' => $visitId,
            'ordering_clinician_id' => $actor->userId,
            'urgency' => $draft['urgency'] ?? 'ROUTINE',
            'clinical_indication' => $draft['clinical_indication'] ?? null,
            // Enables the paediatric mg/kg checks on Clinical's side. Omitting
            // it does not fail the request — it silently skips a safety check,
            // which is worse, so send it whenever the age is known.
            'age_years' => $draft['age_years'] ?? null,
            'cdss_override_reason_code' => $overrideReasonCode,
            'cdss_override_note' => $overrideNote,
            'confirm_external_fulfilment' => $confirmExternalFulfilment ?: null,
            'items' => [$this->itemPayload($draft)],
        ], fn ($value) => $value !== null);

        $data = $this->client->post('clinical/orders/medications', $payload, [
            'business_id' => $actor->businessId,
            // The caller normally supplies this and holds it stable across the
            // override / external-fulfilment retries, so those continuations
            // do not each place a separate prescription. The fallback is a
            // fresh uuid, which is honest about providing no protection rather
            // than a derived key that might collide with a genuine repeat
            // prescription of the same drug.
            'idempotency_key' => $idempotencyKey ?: (string) Str::uuid(),
        ]);

        return MedicationOrderRecord::fromApi($data);
    }

    public function cancel(ClinicalActor $actor, int|string $orderId, string $cancellationReasonCode): void
    {
        $this->client->post("clinical/orders/{$orderId}/cancel", [
            'cancellation_reason_code' => $cancellationReasonCode,
        ], [
            'business_id' => $actor->businessId,
            'idempotency_key' => "order-cancel-{$orderId}",
        ]);
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function itemPayload(array $draft): array
    {
        return array_filter([
            // Generic term, not a SKU — Clinical resolves the brand itself.
            'requested_term' => $draft['requested_term'],
            'strength_descriptor' => $draft['strength_descriptor'] ?? null,
            'dose_quantity' => (float) $draft['dose_amount'],
            'dose_uom_id' => $draft['dose_uom_id'] ?? null,
            'route_code' => $draft['route_code'],
            'frequency_code' => $draft['frequency_code'],
            'duration_days' => $draft['duration_days'] ?? null,
            'quantity' => $draft['quantity'] ?? null,
        ], fn ($value) => $value !== null);
    }
}
