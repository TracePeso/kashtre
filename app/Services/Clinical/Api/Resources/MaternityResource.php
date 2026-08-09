<?php

namespace App\Services\Clinical\Api\Resources;

/**
 * The maternity birth event and chronic recall — API Integration Guide §10.8.
 *
 * The birth event is one of the few places where a single clinical act creates
 * a *new patient*, which is why it needs Main's cooperation: Clinical records
 * the birth and emits INFANT_REGISTRATION_REQUESTED, Main registers the infant,
 * then Main calls linkInfant() back. §14 records that Main does not yet receive
 * that event — until it does, a newborn's APGAR and birth weight have no chart
 * of their own to land on.
 */
class MaternityResource extends ClinicalResource
{
    /**
     * Records a delivery and its infants. `delivery_mode_code` comes from
     * options(), never hardcoded.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordBirthEvent(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/maternity/birth-events', $this->filled($payload), $options);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function birthEventsForPatient(string $patientId, array $options = []): array
    {
        return $this->rows(
            $this->client->get("clinical/patients/{$patientId}/birth-events", [], $options),
            'birth_events'
        );
    }

    public function showBirthEvent(int|string $birthEventId, array $options = []): array
    {
        return $this->client->get("clinical/maternity/birth-events/{$birthEventId}", [], $options);
    }

    /**
     * APGAR at a timepoint, scored from its five named components rather than
     * a total — the components are what a paediatrician reviews.
     */
    public function recordApgar(int|string $birthRecordId, array $payload, array $options = []): array
    {
        return $this->client->post(
            "clinical/maternity/birth-records/{$birthRecordId}/apgar",
            $this->filled($payload),
            $this->idempotent(
                "apgar-{$birthRecordId}-".($payload['timepoint_minutes'] ?? 'x'),
                $options,
            ),
        );
    }

    /**
     * Attaches a Main-registered infant to its birth record — the second half
     * of the INFANT_REGISTRATION_REQUESTED handshake (§9.1).
     */
    public function linkInfant(int|string $birthRecordId, array $payload, array $options = []): array
    {
        return $this->client->post(
            "clinical/maternity/birth-records/{$birthRecordId}/link-infant",
            $this->filled($payload),
            $this->idempotent("link-infant-{$birthRecordId}", $options),
        );
    }

    /**
     * Re-emits the registration request when Main never acted on the first —
     * the manual repair for a newborn stuck without a chart.
     */
    public function resendRegistration(int|string $birthRecordId, array $options = []): array
    {
        return $this->client->post(
            "clinical/maternity/birth-records/{$birthRecordId}/resend-registration",
            [],
            $options,
        );
    }

    /**
     * Delivery modes, birth outcomes and APGAR component values. Tenant
     * configurable — populate dropdowns from here, never from a PHP enum.
     *
     * @return array<string, mixed>
     */
    public function options(array $options = []): array
    {
        return $this->client->get('clinical/maternity/options', [], $options);
    }

    // ---------------------------------------------------------------- recall

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recallsForPatient(string $patientId, array $options = []): array
    {
        return $this->rows(
            $this->client->get("clinical/patients/{$patientId}/recalls", [], $options),
            'recalls'
        );
    }

    /**
     * Chronic recall worklist — who is due back. Populated by Clinical's
     * scheduler, so an empty list on a live system usually means
     * `schedule:run` is not running on their side rather than that nobody is due.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recallWorklist(array $query = [], array $options = []): array
    {
        return $this->rows(
            $this->client->get('clinical/recalls/worklist', $this->filled($query), $options),
            'recalls'
        );
    }

    public function completeRecall(int|string $recallId, array $payload = [], array $options = []): array
    {
        return $this->client->post(
            "clinical/recalls/{$recallId}/complete",
            $this->filled($payload),
            $this->idempotent("recall-{$recallId}-complete", $options),
        );
    }

    public function cancelRecall(int|string $recallId, array $payload = [], array $options = []): array
    {
        return $this->client->post(
            "clinical/recalls/{$recallId}/cancel",
            $this->filled($payload),
            $this->idempotent("recall-{$recallId}-cancel", $options),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recallRules(array $options = []): array
    {
        return $this->rows($this->client->get('clinical/recalls/rules', [], $options), 'rules');
    }

    public function refreshRecalls(array $options = []): array
    {
        return $this->client->post('clinical/recalls/refresh', [], $options);
    }
}
