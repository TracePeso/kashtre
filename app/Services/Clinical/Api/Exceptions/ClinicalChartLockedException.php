<?php

namespace App\Services\Clinical\Api\Exceptions;

/**
 * 409, covering the three conflict conditions in API Integration Guide §6.
 *
 *   CHART_LOCKED                    — the patient is deceased and the chart is
 *                                     frozen for mortality audit. Reads still
 *                                     work; no write will ever succeed. Do not
 *                                     retry, and do not present this as an error
 *                                     the clinician can resolve.
 *   IDEMPOTENCY_KEY_REUSED          — the same key was sent with a different
 *                                     body. This is a bug in our client, not a
 *                                     transient fault. Fix the key derivation.
 *   IDEMPOTENCY_REQUEST_IN_FLIGHT   — the original request is still running.
 *                                     This one *is* worth retrying shortly.
 */
class ClinicalChartLockedException extends ClinicalApiException
{
    public function isChartLocked(): bool
    {
        return $this->errorCode() === 'CHART_LOCKED';
    }

    public function isIdempotencyConflict(): bool
    {
        return in_array(
            $this->errorCode(),
            ['IDEMPOTENCY_KEY_REUSED', 'IDEMPOTENCY_REQUEST_IN_FLIGHT'],
            true
        );
    }

    public function isRetryable(): bool
    {
        return $this->errorCode() === 'IDEMPOTENCY_REQUEST_IN_FLIGHT';
    }
}
