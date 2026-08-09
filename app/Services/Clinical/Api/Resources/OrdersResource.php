<?php

namespace App\Services\Clinical\Api\Resources;

use Illuminate\Support\Str;

/**
 * Orders, prescriptions and the CDSS shield — API Integration Guide §10.3.
 *
 * All four order types share one refusal vocabulary, both arriving as 422:
 *
 *   CDSS_HARD_BLOCK               resend with cdss_override_reason_code, from a
 *                                 clinician holding a senior role
 *   EXTERNAL_FULFILMENT_REQUIRED  resend with confirm_external_fulfilment: true
 *
 * Nothing here can succeed until Main's catalogue lookup is reachable (§14) —
 * Clinical resolves a generic term into a SKU by calling back into it, and
 * without that every order answers 503.
 */
class OrdersResource extends ClinicalResource
{
    /**
     * Pass `idempotency_key` in $options and hold it stable across the
     * override and external-fulfilment retries — those continue one clinical
     * decision rather than starting new ones.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function medications(array $payload, array $options = []): array
    {
        return $this->place('medications', $payload, $options);
    }

    public function laboratory(array $payload, array $options = []): array
    {
        return $this->place('laboratory', $payload, $options);
    }

    /**
     * Add `imaging_modality`: CT, MR, XA, US, DX or MG.
     */
    public function imaging(array $payload, array $options = []): array
    {
        return $this->place('imaging', $payload, $options);
    }

    public function procedures(array $payload, array $options = []): array
    {
        return $this->place('procedures', $payload, $options);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function place(string $type, array $payload, array $options): array
    {
        return $this->client->post(
            "clinical/orders/{$type}",
            $this->filled($payload),
            // A uuid fallback is honest about giving no protection, rather
            // than a derived key that could collide with a genuine repeat
            // prescription of the same drug for the same patient.
            $options + ['idempotency_key' => $options['idempotency_key'] ?? (string) Str::uuid()],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forPatient(string $patientId, array $query = [], array $options = []): array
    {
        return $this->rows(
            $this->client->get("clinical/patients/{$patientId}/orders", $this->filled($query), $options),
            'orders'
        );
    }

    /**
     * Dry-run catalogue resolution — what would this term resolve to? Useful
     * for a type-ahead, and for confirming the §14 catalogue lookup is wired
     * before attempting a real order.
     */
    public function translate(string $requestedTerm, ?string $strengthDescriptor = null, array $options = []): array
    {
        return $this->client->post('clinical/orders/translate', $this->filled([
            'requested_term' => $requestedTerm,
            'strength_descriptor' => $strengthDescriptor,
        ]), $options);
    }

    public function cancel(int|string $orderId, string $cancellationReasonCode, array $options = []): array
    {
        return $this->client->post(
            "clinical/orders/{$orderId}/cancel",
            ['cancellation_reason_code' => $cancellationReasonCode],
            $this->idempotent("order-cancel-{$orderId}", $options),
        );
    }

    /**
     * Re-sends an order to its fulfilling module after a delivery failure —
     * the manual repair for an order LIMS or RIS never received.
     */
    public function redispatch(int|string $orderId, array $options = []): array
    {
        return $this->client->post(
            "clinical/orders/{$orderId}/redispatch",
            [],
            $this->idempotent("order-redispatch-{$orderId}", $options),
        );
    }

    // ---------------------------------------------------------------- CDSS

    /**
     * Dry-run the safety shield. Returns 200 with a verdict rather than
     * refusing, which is what lets a UI warn before the clinician commits.
     */
    public function evaluateCdss(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/cdss/evaluate', $this->filled($payload), $options);
    }

    /**
     * Overrides already exercised — the review feed for whoever audits how
     * often the shield is being talked past.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cdssOverrides(array $query = [], array $options = []): array
    {
        return $this->rows($this->client->get('clinical/cdss/overrides', $this->filled($query), $options), 'overrides');
    }

    // ---------------------------------------------------------------- order sets

    /**
     * @return array<int, array<string, mixed>>
     */
    public function orderSets(array $query = [], array $options = []): array
    {
        return $this->rows($this->client->get('clinical/order-sets', $this->filled($query), $options), 'order_sets');
    }

    /**
     * Applies a protocol bundle. Each generated order still runs the CDSS
     * shield individually, so applying a set can partially block.
     */
    public function applyOrderSet(int|string $orderSetId, array $payload, array $options = []): array
    {
        return $this->client->post(
            "clinical/order-sets/{$orderSetId}/apply",
            $this->filled($payload),
            $options + ['idempotency_key' => $options['idempotency_key'] ?? (string) Str::uuid()],
        );
    }
}
