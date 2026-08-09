<?php

namespace App\Services\Clinical\Api\Resources;

/**
 * The patient chart — API Integration Guide §10.2 and §10.8, plus the
 * patient-scoped reads in §16's "Clinical — patient chart" block.
 *
 * Every route here is patient-gated: the care-relationship gate and the chart
 * lock both apply, so expect 403 REBAC_ACCESS_DENIED and 409 CHART_LOCKED.
 * Reads still work on a locked chart; writes never will.
 */
class ChartResource extends ClinicalResource
{
    // ---------------------------------------------------------------- observations

    /**
     * Values are normalised to the CDE's base unit on write. One outside what
     * is compatible with life is refused 422 — that guard exists to catch unit
     * confusion, the commonest cause of a dangerous number in a chart.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createObservation(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/observations', $this->filled($payload), $options);
    }

    /**
     * `display_uom_id` converts the values *and* re-scales the alert
     * boundaries to match, so a clinician reading mg/dL sees mg/dL thresholds.
     *
     * @return array<int, array<string, mixed>>
     */
    public function observations(string $patientId, array $query = [], array $options = []): array
    {
        return $this->rows(
            $this->client->get("clinical/patients/{$patientId}/observations", $this->filled($query), $options),
            'observations'
        );
    }

    /**
     * The observation compliance state machine — whether scheduled rounds are
     * being met. Advanced by Clinical's scheduler, not by reads.
     *
     * @return array<string, mixed>
     */
    public function observationCompliance(string $patientId, array $options = []): array
    {
        return $this->client->get("clinical/patients/{$patientId}/observation-compliance", [], $options);
    }

    public function refreshObservationCompliance(string $patientId, array $options = []): array
    {
        return $this->client->post("clinical/patients/{$patientId}/observation-compliance/refresh", [], $options);
    }

    /**
     * Tenant-wide compliance recalculation, not scoped to one patient.
     */
    public function refreshAllObservationCompliance(array $options = []): array
    {
        return $this->client->post('clinical/observation-compliance/refresh', [], $options);
    }

    public function skipScheduledObservation(int|string $scheduledObservationId, array $payload = [], array $options = []): array
    {
        return $this->client->post(
            "clinical/scheduled-observations/{$scheduledObservationId}/skip",
            $this->filled($payload),
            $this->idempotent("scheduled-obs-skip-{$scheduledObservationId}", $options),
        );
    }

    // ---------------------------------------------------------------- care team

    /**
     * @return array<int, array<string, mixed>>
     */
    public function careTeam(string $patientId, array $options = []): array
    {
        return $this->rows($this->client->get("clinical/patients/{$patientId}/care-team", [], $options), 'members');
    }

    // ---------------------------------------------------------------- allergies

    /**
     * Allergies feed the CDSS shield's DRUG_ALLERGY hard block, so an
     * incomplete list here is a silently weaker safety check at prescribing
     * time — not merely missing reference data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allergies(string $patientId, array $options = []): array
    {
        return $this->rows($this->client->get("clinical/patients/{$patientId}/allergies", [], $options), 'allergies');
    }

    public function recordAllergy(string $patientId, array $payload, array $options = []): array
    {
        return $this->client->post("clinical/patients/{$patientId}/allergies", $this->filled($payload), $options);
    }

    public function updateAllergy(string $patientId, int|string $allergyId, array $payload, array $options = []): array
    {
        return $this->client->patch(
            "clinical/patients/{$patientId}/allergies/{$allergyId}",
            $this->filled($payload),
            $options,
        );
    }

    // ---------------------------------------------------------------- diagnoses

    /**
     * @return array<int, array<string, mixed>>
     */
    public function diagnoses(string $patientId, array $options = []): array
    {
        return $this->rows($this->client->get("clinical/patients/{$patientId}/diagnoses", [], $options), 'diagnoses');
    }

    /**
     * `diagnosis_type` is PRIMARY / SECONDARY / DIFFERENTIAL; `icd11_code`
     * comes from the coder or from ai()->suggestIcd11(), never invented.
     */
    public function recordDiagnosis(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/diagnoses', $this->filled($payload), $options);
    }

    public function updateDiagnosis(int|string $diagnosisId, array $payload, array $options = []): array
    {
        return $this->client->patch("clinical/diagnoses/{$diagnosisId}", $this->filled($payload), $options);
    }

    // ---------------------------------------------------------------- immunizations

    /**
     * @return array<int, array<string, mixed>>
     */
    public function immunizations(string $patientId, array $options = []): array
    {
        return $this->rows($this->client->get("clinical/patients/{$patientId}/immunizations", [], $options), 'immunizations');
    }

    public function recordImmunization(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/immunizations', $this->filled($payload), $options);
    }

    // ---------------------------------------------------------------- entitlements & work

    /**
     * What a purchased package still covers. Clinical intercepts consumption
     * beyond this rather than letting it silently become a billable extra.
     *
     * @return array<int, array<string, mixed>>
     */
    public function entitlements(string $patientId, array $options = []): array
    {
        return $this->rows($this->client->get("clinical/patients/{$patientId}/entitlements", [], $options), 'entitlements');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function workOrders(string $patientId, array $options = []): array
    {
        return $this->rows($this->client->get("clinical/patients/{$patientId}/work-orders", [], $options), 'work_orders');
    }

    public function startCarePathway(string $patientId, array $payload, array $options = []): array
    {
        return $this->client->post("clinical/patients/{$patientId}/care-pathways", $this->filled($payload), $options);
    }
}
