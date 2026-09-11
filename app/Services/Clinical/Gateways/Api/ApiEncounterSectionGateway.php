<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\EncounterSectionGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Log;

/**
 * CLINICAL_DRIVER=api: `clinical/patients/{id}/encounter-sections/*`
 * (confirmed live 2026-08-19).
 */
class ApiEncounterSectionGateway implements EncounterSectionGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function forPatient(ClinicalActor $actor, string $patientId, ?string $visitId = null, bool $includeWithdrawn = false): array
    {
        try {
            $data = $this->client->get(
                "clinical/patients/{$patientId}/encounter-sections",
                array_filter([
                    'visit_id' => $visitId,
                    'include_withdrawn' => $includeWithdrawn ? 1 : null,
                ]),
                ['business_id' => $actor->businessId],
            );
        } catch (ClinicalApiException $e) {
            Log::warning('Could not load encounter-section signatures.', $e->context());

            return [];
        }

        $rows = array_is_list($data) ? $data : ($data['items'] ?? $data['data'] ?? []);

        return array_values(array_filter($rows, 'is_array'));
    }

    public function sign(
        ClinicalActor $actor,
        string $patientId,
        string $visitId,
        string $sectionCode,
        string $sectionName,
        ?string $attestationNote = null,
    ): array {
        return $this->client->post(
            "clinical/patients/{$patientId}/encounter-sections/sign",
            array_filter([
                'visit_id' => $visitId,
                'section_code' => $sectionCode,
                'section_name' => $sectionName,
                'attestation_note' => $attestationNote,
            ], fn ($v) => $v !== null),
            [
                'business_id' => $actor->businessId,
                // Re-signing the same section updates in place on Clinical's
                // side already; a stable key per patient+section (not per
                // attempt) means a retried tap replays rather than risking a
                // duplicate attestation record.
                'idempotency_key' => 'encounter-section-sign-'.$patientId.'-'.$visitId.'-'.$sectionCode,
            ],
        );
    }

    public function withdraw(ClinicalActor $actor, string $patientId, int|string $signatureId, string $reason): void
    {
        $this->client->post(
            "clinical/patients/{$patientId}/encounter-sections/{$signatureId}/withdraw",
            ['reason' => $reason],
            [
                'business_id' => $actor->businessId,
                'idempotency_key' => 'encounter-section-withdraw-'.$signatureId,
            ],
        );
    }
}
