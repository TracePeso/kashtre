<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\DiagnosticReportStatusGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

/**
 * CLINICAL_DRIVER=api: diagnostic report status lifecycle over HTTP —
 * SRD v6.1 Phase 8, API_GUIDE_V6.1 §Phase 8.
 */
class ApiDiagnosticReportStatusGateway implements DiagnosticReportStatusGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function correct(ClinicalActor $actor, string $reportId, string $reason): array
    {
        return $this->client->post(
            "clinical/diagnostic-reports/{$reportId}/correct",
            ['reason' => $reason],
            ['business_id' => $actor->businessId],
        );
    }

    public function markEnteredInError(ClinicalActor $actor, string $reportId, string $reason): array
    {
        return $this->client->post(
            "clinical/diagnostic-reports/{$reportId}/entered-in-error",
            ['reason' => $reason],
            ['business_id' => $actor->businessId],
        );
    }

    public function cancel(ClinicalActor $actor, string $reportId, string $reason): array
    {
        return $this->client->post(
            "clinical/diagnostic-reports/{$reportId}/cancel",
            ['reason' => $reason],
            ['business_id' => $actor->businessId],
        );
    }
}
