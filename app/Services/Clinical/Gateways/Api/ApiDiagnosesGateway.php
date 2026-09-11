<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\DiagnosesGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\ClinicalDiagnosis;
use Illuminate\Support\Facades\Log;

/**
 * CLINICAL_DRIVER=api: the problem list from the Clinical Module.
 *
 * Reads fail *soft*: a chart that cannot reach the diagnosis list still shows
 * observations, the MAR and the rest, and an empty problem list with the panel
 * present is less dangerous than a 500 that hides the whole chart. Writes fail
 * *hard* — a clinician who records a diagnosis must be told if it did not save.
 */
class ApiDiagnosesGateway implements DiagnosesGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function forPatient(ClinicalActor $actor, string $patientId): array
    {
        try {
            $data = $this->client->get(
                "clinical/patients/{$patientId}/diagnoses",
                [],
                ['business_id' => $actor->businessId],
            );
        } catch (ClinicalApiException $e) {
            Log::warning('Could not load the patient problem list.', $e->context());

            return [];
        }

        $rows = array_is_list($data) ? $data : ($data['items'] ?? []);

        return array_map(
            fn (array $row) => ClinicalDiagnosis::fromApi($row),
            array_values(array_filter($rows, 'is_array')),
        );
    }

    public function record(
        ClinicalActor $actor,
        string $patientId,
        string $description,
        ?string $icd11Code = null,
        ?string $visitId = null,
    ): void {
        $this->client->post('clinical/diagnoses', [
            'patient_id' => $patientId,
            'visit_id' => $visitId,
            'icd11_code' => $icd11Code,
            'display_label' => $description,
        ], [
            'business_id' => $actor->businessId,
            // Same diagnosis, same visit, recorded twice by a double-tap is one
            // clinical act — but recording it again tomorrow is not, so the key
            // is scoped to this attempt rather than to the content alone.
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ]);
    }

    /**
     * Clinical requires icd11_code. Letting the panel know up front turns a
     * server-side 422 into a required field.
     */
    public function allowsUncodedDiagnosis(): bool
    {
        return false;
    }
}
