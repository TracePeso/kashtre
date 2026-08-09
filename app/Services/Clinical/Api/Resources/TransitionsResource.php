<?php

namespace App\Services\Clinical\Api\Resources;

/**
 * Major clinical transitions — API Integration Guide §10.6.
 *
 * Admission, transfer, discharge, external referral and death certification
 * are modelled as ordered, role-owned step machines rather than status flags,
 * because the steps carry real effects: allocating a bed, halting all active
 * orders, locking the chart, generating an IPS export.
 *
 * Two behaviours worth knowing before you build a UI on this:
 *
 *  - Discharge is **blocked while a critical result is unacknowledged**. That
 *    block is overridable, but the override is audited and needs a reason code.
 *  - Death certification locks the chart. Every subsequent write anywhere in
 *    the clinical API returns 409 CHART_LOCKED. Reads keep working.
 */
class TransitionsResource extends ClinicalResource
{
    public const ADMISSION = 'ADMISSION';

    public const TRANSFER = 'TRANSFER';

    public const DISCHARGE = 'DISCHARGE';

    public const EXTERNAL_REFERRAL = 'EXTERNAL_REFERRAL';

    public const DEATH_CERTIFICATION = 'DEATH_CERTIFICATION';

    /**
     * Opens a transition and returns the first step, with the role that owns
     * it. Steps are ordered — the caller does not get to choose which is next.
     *
     * @return array<string, mixed>
     */
    public function start(string $processCode, array $payload, array $options = []): array
    {
        return $this->client->post(
            "clinical/transitions/{$processCode}/start",
            $this->filled($payload),
            $options,
        );
    }

    /**
     * Completes the current step. `override_reason_code` is required when the
     * step is blocked — an unacknowledged critical result at discharge being
     * the case you will actually hit.
     */
    public function execute(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/transitions/execute', $this->filled($payload), $options);
    }

    /**
     * Starts an admission and reserves a bed in one call, for the common
     * A&E "decision to admit" moment where the bed matters more than the
     * paperwork.
     */
    public function decisionToAdmit(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/transitions/decision-to-admit', $this->filled($payload), $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int|string $transitionId, array $options = []): array
    {
        return $this->client->get("clinical/transitions/{$transitionId}", [], $options);
    }

    /**
     * Abandons an in-flight transition. Effects already applied by completed
     * steps — a bed allocated, orders halted — are not automatically undone,
     * so check the response rather than assuming a clean rollback.
     */
    public function abandon(int|string $transitionId, array $payload = [], array $options = []): array
    {
        return $this->client->post(
            "clinical/transitions/{$transitionId}/abandon",
            $this->filled($payload),
            $this->idempotent("transition-{$transitionId}-abandon", $options),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forPatient(string $patientId, array $options = []): array
    {
        return $this->rows(
            $this->client->get("clinical/patients/{$patientId}/transitions", [], $options),
            'transitions'
        );
    }
}
