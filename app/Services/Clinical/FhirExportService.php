<?php

namespace App\Services\Clinical;

use App\Models\CdeObservation;
use App\Models\Client;
use App\Models\ClinicalCondition;
use App\Models\ClinicalMedicationOrder;

/**
 * SRD §8 HL7 FHIR R4 export. Builds a plain FHIR Bundle (JSON) — no FHIR
 * library dependency, since the mapping is small and explicit enough
 * that pulling in a full FHIR SDK would be more ceremony than value.
 * IPS/C-CDA export (also named in the plan) is not built — R4 JSON is
 * the substantially higher-value target (every downstream consumer this
 * app is likely to integrate with speaks FHIR JSON), and IPS is itself a
 * defined FHIR profile on top of the same resources, not a separate
 * mapping effort worth duplicating speculatively.
 */
class FhirExportService
{
    public function exportPatientBundle(int $businessId, string $clientId): array
    {
        $client = Client::where('business_id', $businessId)->where('client_id', $clientId)->firstOrFail();

        $resources = [$this->patientResource($client)];

        if ($client->visit_id) {
            $resources[] = $this->encounterResource($client);
        }

        foreach (CdeObservation::where('business_id', $businessId)->where('client_id', $clientId)->orderByDesc('captured_at')->limit(100)->get() as $observation) {
            $resources[] = $this->observationResource($observation, $client);
        }

        foreach (ClinicalCondition::where('business_id', $businessId)->where('client_id', $clientId)->get() as $condition) {
            $resources[] = $this->conditionResource($condition, $client);
        }

        foreach (ClinicalMedicationOrder::where('business_id', $businessId)->where('client_id', $clientId)->get() as $order) {
            $resources[] = $this->medicationRequestResource($order, $client);
        }

        return [
            'resourceType' => 'Bundle',
            'type' => 'collection',
            'timestamp' => now()->toIso8601String(),
            'entry' => array_map(fn (array $resource) => ['resource' => $resource], $resources),
        ];
    }

    private function patientResource(Client $client): array
    {
        $name = trim(($client->first_name ?? '').' '.($client->surname ?? '')) ?: $client->name;

        return [
            'resourceType' => 'Patient',
            'id' => $client->client_id,
            'identifier' => [['system' => 'urn:kashtre:client-id', 'value' => $client->client_id]],
            'name' => [['text' => $name]],
            'gender' => $this->mapGender($client->sex),
            'birthDate' => optional($client->date_of_birth)->toDateString(),
        ];
    }

    private function encounterResource(Client $client): array
    {
        return [
            'resourceType' => 'Encounter',
            'id' => $client->visit_id,
            'identifier' => [['system' => 'urn:kashtre:visit-id', 'value' => $client->visit_id]],
            'status' => 'in-progress',
            'subject' => ['reference' => 'Patient/'.$client->client_id],
        ];
    }

    private function observationResource(CdeObservation $observation, Client $client): array
    {
        $resource = [
            'resourceType' => 'Observation',
            'id' => (string) $observation->id,
            'status' => $observation->validation_status === 'VALIDATED' ? 'final' : 'preliminary',
            'code' => [
                'coding' => [['system' => 'urn:kashtre:cde-code', 'code' => $observation->cde_code]],
                'text' => $observation->cde_code,
            ],
            'subject' => ['reference' => 'Patient/'.$client->client_id],
            'effectiveDateTime' => optional($observation->captured_at)->toIso8601String(),
        ];

        if ($observation->visit_id) {
            $resource['encounter'] = ['reference' => 'Encounter/'.$observation->visit_id];
        }

        if ($observation->base_value_numeric !== null) {
            $resource['valueQuantity'] = [
                'value' => (float) $observation->base_value_numeric,
                'unit' => $observation->baseUnit?->unit_label,
            ];
        } elseif ($observation->captured_value_text) {
            $resource['valueString'] = $observation->captured_value_text;
        }

        return $resource;
    }

    private function conditionResource(ClinicalCondition $condition, Client $client): array
    {
        return [
            'resourceType' => 'Condition',
            'id' => (string) $condition->id,
            'clinicalStatus' => ['coding' => [['code' => strtolower($condition->clinical_status)]]],
            'code' => [
                'coding' => $condition->icd11_code
                    ? [['system' => 'http://id.who.int/icd/release/11/mms', 'code' => $condition->icd11_code]]
                    : [],
                'text' => $condition->description,
            ],
            'subject' => ['reference' => 'Patient/'.$client->client_id],
            'recordedDate' => optional($condition->recorded_at)->toIso8601String(),
        ];
    }

    private function medicationRequestResource(ClinicalMedicationOrder $order, Client $client): array
    {
        $statusMap = [
            ClinicalMedicationOrder::STATUS_ACTIVE => 'active',
            ClinicalMedicationOrder::STATUS_DISCONTINUED => 'stopped',
            ClinicalMedicationOrder::STATUS_COMPLETED => 'completed',
        ];

        return [
            'resourceType' => 'MedicationRequest',
            'id' => (string) $order->id,
            'status' => $statusMap[$order->status] ?? 'unknown',
            'intent' => 'order',
            'medicationCodeableConcept' => ['text' => $order->drug_display_name],
            'subject' => ['reference' => 'Patient/'.$client->client_id],
            'authoredOn' => optional($order->created_at)->toIso8601String(),
            'dosageInstruction' => [[
                'text' => "{$order->dose_amount} via {$order->route_code}, {$order->frequency_code}",
                'route' => ['text' => $order->route_code],
            ]],
        ];
    }

    private function mapGender(?string $sex): string
    {
        return match (strtolower((string) $sex)) {
            'male', 'm' => 'male',
            'female', 'f' => 'female',
            default => 'unknown',
        };
    }
}
