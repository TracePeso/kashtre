<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\TriageGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

/**
 * CLINICAL_DRIVER=api: `POST clinical/patients/{id}/triage` (confirmed live
 * 2026-08-18). A write that computes and returns a score — unlike every
 * other write gateway this session, there is nothing useful to do with the
 * response but hand it back whole, so this does not catch
 * ClinicalApiException: a triage assessment that silently failed to score
 * is worse than one the clinician is told did not go through.
 */
class ApiTriageGateway implements TriageGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function assess(ClinicalActor $actor, string $patientId, string $visitId, bool $announce = true): array
    {
        return $this->client->post(
            "clinical/patients/{$patientId}/triage",
            ['visit_id' => $visitId, 'announce' => $announce],
            ['business_id' => $actor->businessId],
        );
    }
}
