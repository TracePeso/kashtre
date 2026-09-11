<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\FhirExportGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Log;

/**
 * CLINICAL_DRIVER=api: `GET fhir/Patient/{id}/$everything` (confirmed live
 * 2026-08-19). Uses getRaw(), not get() — a FHIR Bundle is
 * {resourceType, entry, ...} at the top level, not this client's usual
 * {data, meta} envelope, and get()/post() unwrap `data` unconditionally.
 * Calling this with the ordinary accessor silently returns an empty array,
 * which looks exactly like "nothing to export" instead of "wrong method".
 */
class ApiFhirExportGateway implements FhirExportGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function patientEverything(ClinicalActor $actor, string $patientId): array
    {
        try {
            return $this->client->getRaw(
                "fhir/Patient/{$patientId}/\$everything",
                [],
                ['business_id' => $actor->businessId],
            );
        } catch (ClinicalApiException $e) {
            Log::warning('Could not export the FHIR bundle.', $e->context());

            return ['resourceType' => 'Bundle', 'type' => 'searchset', 'total' => 0, 'entry' => []];
        }
    }
}
