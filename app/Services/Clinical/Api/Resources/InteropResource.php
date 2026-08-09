<?php

namespace App\Services\Clinical\Api\Resources;

/**
 * Document exchange and the FHIR interface — API Integration Guide §13.
 *
 * Two deliberate constraints, both worth respecting rather than working
 * around:
 *
 *  - **FHIR is read-only.** A FHIR POST would be a route past the CDSS shield,
 *    the physiological guard and the reason-code dictionaries in one request.
 *    Clinical writes go through the clinical API.
 *  - **An inbound summary is staged, never auto-charted.** Only allergies,
 *    conditions and immunisations can be merged; a foreign medication list is
 *    not a prescription written here.
 *
 * Also note codes currently export under Clinical's **local** code system, not
 * LOINC (OPEN-24). Exporting an unmapped code dressed as LOINC would file
 * readings against the wrong concept in a receiving registry, so do not assume
 * LOINC until terminology sign-off lands.
 */
class InteropResource extends ClinicalResource
{
    // ---------------------------------------------------------------- IPS export

    /**
     * @param  array{patient_id: string, format?: string}  $payload
     */
    public function createExchangeDocument(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/exchange-documents', $this->filled($payload), $options);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function exchangeDocumentsForPatient(string $patientId, array $options = []): array
    {
        return $this->rows(
            $this->client->get("clinical/patients/{$patientId}/exchange-documents", [], $options),
            'documents'
        );
    }

    /** Returns the raw document body, not an envelope. */
    public function downloadExchangeDocument(int|string $documentId, array $options = []): string
    {
        return $this->client->download("clinical/exchange-documents/{$documentId}/download", [], $options);
    }

    // ---------------------------------------------------------------- IPS import

    /**
     * Stages an inbound FHIR Bundle for review. Charts nothing on its own.
     *
     * @param  array{patient_id: string, document: string|array}  $payload
     */
    public function importDocument(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/document-imports', $this->filled($payload), $options);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function documentImportsForPatient(string $patientId, array $options = []): array
    {
        return $this->rows(
            $this->client->get("clinical/patients/{$patientId}/document-imports", [], $options),
            'imports'
        );
    }

    public function showDocumentImport(int|string $importId, array $options = []): array
    {
        return $this->client->get("clinical/document-imports/{$importId}", [], $options);
    }

    /**
     * Merges one staged item onto the chart. Allergies, conditions and
     * immunisations only.
     */
    public function mergeImportItem(int|string $itemId, array $payload = [], array $options = []): array
    {
        return $this->client->post(
            "clinical/document-imports/items/{$itemId}/merge",
            $this->filled($payload),
            $this->idempotent("import-item-{$itemId}-merge", $options),
        );
    }

    public function rejectImportItem(int|string $itemId, array $payload = [], array $options = []): array
    {
        return $this->client->post(
            "clinical/document-imports/items/{$itemId}/reject",
            $this->filled($payload),
            $this->idempotent("import-item-{$itemId}-reject", $options),
        );
    }

    // ---------------------------------------------------------------- FHIR R4 (read-only)

    /**
     * CapabilityStatement — what this FHIR server actually supports. Read it
     * before assuming a search parameter exists.
     *
     * @return array<string, mixed>
     */
    public function fhirMetadata(array $options = []): array
    {
        return $this->client->getRaw('fhir/metadata', [], $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function fhirPatient(string $patientId, array $options = []): array
    {
        return $this->client->getRaw("fhir/Patient/{$patientId}", [], $options);
    }

    /**
     * The whole record as one Bundle. Expensive — prefer a targeted search
     * when you know which resource you need.
     *
     * @return array<string, mixed>
     */
    public function fhirPatientEverything(string $patientId, array $options = []): array
    {
        return $this->client->getRaw("fhir/Patient/{$patientId}/\$everything", [], $options);
    }

    /**
     * Searches return a `searchset` Bundle; failures return an
     * OperationOutcome rather than our usual error envelope. FHIR reads pass
     * through the same care-relationship gate as everything else.
     *
     * @param  string  $resourceType  Encounter | Observation | Condition | MedicationRequest
     *                                | ServiceRequest | DiagnosticReport
     *                                | AllergyIntolerance | Immunization
     * @return array<string, mixed>
     */
    public function fhirSearch(string $resourceType, array $query = [], array $options = []): array
    {
        return $this->client->getRaw("fhir/{$resourceType}", $this->filled($query), $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function fhirObservations(string $patientId, array $query = [], array $options = []): array
    {
        return $this->fhirSearch('Observation', $query + ['patient' => $patientId], $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function fhirConditions(string $patientId, array $query = [], array $options = []): array
    {
        return $this->fhirSearch('Condition', $query + ['patient' => $patientId], $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function fhirMedicationRequests(string $patientId, array $query = [], array $options = []): array
    {
        return $this->fhirSearch('MedicationRequest', $query + ['patient' => $patientId], $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function fhirServiceRequests(string $patientId, array $query = [], array $options = []): array
    {
        return $this->fhirSearch('ServiceRequest', $query + ['patient' => $patientId], $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function fhirDiagnosticReports(string $patientId, array $query = [], array $options = []): array
    {
        return $this->fhirSearch('DiagnosticReport', $query + ['patient' => $patientId], $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function fhirAllergies(string $patientId, array $query = [], array $options = []): array
    {
        return $this->fhirSearch('AllergyIntolerance', $query + ['patient' => $patientId], $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function fhirImmunizations(string $patientId, array $query = [], array $options = []): array
    {
        return $this->fhirSearch('Immunization', $query + ['patient' => $patientId], $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function fhirEncounters(string $patientId, array $query = [], array $options = []): array
    {
        return $this->fhirSearch('Encounter', $query + ['patient' => $patientId], $options);
    }
}
