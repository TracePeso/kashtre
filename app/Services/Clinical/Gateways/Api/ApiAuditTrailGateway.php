<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\AuditTrailGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\AuditTrailEntry;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Log;

/**
 * CLINICAL_DRIVER=api: `GET clinical/audit-trail` — confirmed live and
 * readable by an ordinary clinician identity 2026-08-18 (the panel's old
 * placeholder claimed this needs a Medical Director role; empirically it
 * does not — `X-User-Roles` empty still returned every entry for the
 * patient). It is a single hash-chained stream covering break-glass grants,
 * overrides and transition steps together, richer than the two separate
 * local ledgers this replaces.
 *
 * Read fails soft, same posture as DiagnosesPanel/ProcessExecutionGateway —
 * an unreadable audit trail should not hide the rest of the chart.
 */
class ApiAuditTrailGateway implements AuditTrailGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function forPatient(ClinicalActor $actor, string $patientId, int $limit = 20): array
    {
        try {
            $data = $this->client->get(
                'clinical/audit-trail',
                ['patient_id' => $patientId, 'limit' => $limit],
                ['business_id' => $actor->businessId],
            );
        } catch (ClinicalApiException $e) {
            Log::warning('Could not load the audit trail.', $e->context());

            return [];
        }

        $rows = array_is_list($data) ? $data : ($data['items'] ?? $data['data'] ?? []);

        return array_map(
            fn (array $row) => AuditTrailEntry::fromApi($row),
            array_values(array_filter($rows, 'is_array')),
        );
    }
}
