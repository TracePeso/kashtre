<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\MaternityGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Log;

/**
 * CLINICAL_DRIVER=api: `clinical/maternity/*` (confirmed live 2026-08-22 —
 * store/forPatient/show all verified; recordApgar carries a known
 * server-side issue, see the interface doc).
 */
class ApiMaternityGateway implements MaternityGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function options(ClinicalActor $actor): array
    {
        try {
            return $this->client->get('clinical/maternity/options', [], ['business_id' => $actor->businessId]);
        } catch (ClinicalApiException $e) {
            Log::warning('Could not load maternity options.', $e->context());

            return ['DELIVERY_MODE' => [], 'PRESENTATION' => [], 'MATERNAL_OUTCOME' => []];
        }
    }

    public function forPatient(ClinicalActor $actor, string $motherPatientId): array
    {
        try {
            $data = $this->client->get(
                "clinical/patients/{$motherPatientId}/birth-events",
                [],
                ['business_id' => $actor->businessId],
            );
        } catch (ClinicalApiException $e) {
            Log::warning('Could not load this patient\'s birth events.', $e->context());

            return [];
        }

        $rows = array_is_list($data) ? $data : ($data['items'] ?? $data['data'] ?? []);

        return array_values(array_filter($rows, 'is_array'));
    }

    public function recordBirth(
        ClinicalActor $actor,
        string $motherPatientId,
        string $motherVisitId,
        string $deliveryAt,
        string $deliveryModeCode,
        array $infants,
        ?float $gestationWeeks = null,
        ?string $presentationCode = null,
        ?string $maternalOutcomeCode = null,
        ?string $deliveryNotes = null,
    ): array {
        return $this->client->post(
            'clinical/maternity/birth-events',
            array_filter([
                'mother_patient_id' => $motherPatientId,
                'mother_visit_id' => $motherVisitId,
                'delivery_at' => $deliveryAt,
                'delivery_mode_code' => $deliveryModeCode,
                'gestation_weeks' => $gestationWeeks,
                'presentation_code' => $presentationCode,
                'maternal_outcome_code' => $maternalOutcomeCode,
                'delivery_notes' => $deliveryNotes,
                'infants' => $infants,
            ], fn ($v) => $v !== null),
            [
                'business_id' => $actor->businessId,
                'idempotency_key' => 'birth-event-'.$motherPatientId.'-'.$motherVisitId.'-'.$deliveryAt,
            ],
        );
    }

    public function recordApgar(ClinicalActor $actor, int|string $birthRecordId, int $timepointMinutes, array $components): array
    {
        return $this->client->post(
            "clinical/maternity/birth-records/{$birthRecordId}/apgar",
            [
                'timepoint_minutes' => $timepointMinutes,
                'components' => $components,
            ],
            [
                'business_id' => $actor->businessId,
                'idempotency_key' => 'apgar-'.$birthRecordId.'-'.$timepointMinutes,
            ],
        );
    }
}
