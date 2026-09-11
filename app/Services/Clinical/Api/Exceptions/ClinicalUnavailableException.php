<?php

namespace App\Services\Clinical\Api\Exceptions;

/**
 * 503, or a transport failure that never reached Clinical at all.
 *
 * Per API Integration Guide §6 a 503 means one of Clinical's own dependencies
 * is degraded (the Main catalogue, the AI gateway, a diagnostic engine) and
 * the message says which. Retry with backoff.
 *
 * A 5xx is deliberately *not* remembered by the idempotency layer (§7), so a
 * retry after this exception does the work rather than replaying a failure.
 * That makes retrying safe precisely when it matters.
 */
class ClinicalUnavailableException extends ClinicalApiException
{
    public function isRetryable(): bool
    {
        return true;
    }
}
