<?php

namespace App\Services\Clinical\Api\Resources;

/**
 * Diagnostics, critical alerts, device telemetry and clinical scores —
 * §16's "Clinical — diagnostics, telemetry, scores" block.
 */
class DiagnosticsResource extends ClinicalResource
{
    /**
     * Lab and imaging orders with their current workflow state, as relayed
     * from LIMS and RIS.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forPatient(string $patientId, array $query = [], array $options = []): array
    {
        return $this->rows(
            $this->client->get("clinical/patients/{$patientId}/diagnostics", $this->filled($query), $options),
            'diagnostics'
        );
    }

    /**
     * Panic values and critical findings awaiting acknowledgement.
     *
     * These are not merely notifications: an unacknowledged critical result
     * **blocks discharge** (§10.6). A UI that hides them will produce
     * discharges that mysteriously refuse to complete.
     *
     * @return array<int, array<string, mixed>>
     */
    public function criticalAlerts(string $patientId, array $options = []): array
    {
        return $this->rows(
            $this->client->get("clinical/patients/{$patientId}/critical-alerts", [], $options),
            'alerts'
        );
    }

    public function acknowledgeCriticalAlert(int|string $alertId, array $payload = [], array $options = []): array
    {
        return $this->client->post(
            "clinical/critical-alerts/{$alertId}/acknowledge",
            $this->filled($payload),
            $this->idempotent("critical-alert-{$alertId}-ack", $options),
        );
    }

    // ---------------------------------------------------------------- encounters

    /**
     * Tells Clinical a visit has opened (§9.1). `previous_visit_id` carries a
     * returning outpatient's pending lab and imaging orders onto the new
     * visit, so already-printed barcodes stay valid.
     */
    public function encounterCreated(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/encounters/created', $this->filled($payload), $options);
    }

    // ---------------------------------------------------------------- device telemetry

    /**
     * Ingests a reading from a bedside monitor. Readings land as *pending*,
     * not as chart observations — a machine-sourced number still needs a
     * clinician to validate it before it counts as charted.
     */
    public function submitTelemetry(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/device-telemetry', $this->filled($payload), $options);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pendingTelemetry(array $query = [], array $options = []): array
    {
        return $this->rows(
            $this->client->get('clinical/device-telemetry/pending', $this->filled($query), $options),
            'readings'
        );
    }

    /** Promotes a pending reading to a charted observation. */
    public function validateTelemetry(int|string $deviceReadingId, array $payload = [], array $options = []): array
    {
        return $this->client->post(
            "clinical/device-telemetry/{$deviceReadingId}/validate",
            $this->filled($payload),
            $this->idempotent("telemetry-{$deviceReadingId}-validate", $options),
        );
    }

    /** Discards it — an artefact, a disconnected probe, the wrong patient. */
    public function rejectTelemetry(int|string $deviceReadingId, array $payload = [], array $options = []): array
    {
        return $this->client->post(
            "clinical/device-telemetry/{$deviceReadingId}/reject",
            $this->filled($payload),
            $this->idempotent("telemetry-{$deviceReadingId}-reject", $options),
        );
    }

    // ---------------------------------------------------------------- scores

    /**
     * Calculates a configured clinical score — NEWS2, SATS, APGAR, GCS,
     * EGFR_CKD_EPI, BMI.
     *
     * The scoring matrices are tenant-configurable (§10.9), so never
     * reimplement one of these locally: a locally computed NEWS2 that
     * disagrees with the configured escalation thresholds will escalate the
     * wrong patients.
     *
     * @param  array<string, mixed>  $inputs  CDE code => value
     * @return array<string, mixed>
     */
    public function calculateScore(string $scoreCode, array $inputs, array $options = []): array
    {
        return $this->client->post(
            "clinical/scores/{$scoreCode}/calculate",
            ['inputs' => $inputs],
            $options,
        );
    }
}
