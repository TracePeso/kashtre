<?php

namespace App\Services\Clinical\Api\Exceptions;

/**
 * 401 — the service key, the HMAC signature or the identity token was
 * rejected (API Integration Guide §6). The guide's instruction is blunt and
 * worth honouring: do not retry the same credentials.
 *
 * IDENTITY_TOKEN_REQUIRED is the one to watch during the cutover. It means
 * Clinical has set IDENTITY_JWT_REQUIRED=true and is now *refusing* the
 * interim X-User-* headers rather than ignoring them. The fix is to set
 * CLINICAL_IDENTITY_TRANSPORT=jwt here, not to retry.
 */
class ClinicalAuthException extends ClinicalApiException
{
    public function requiresIdentityToken(): bool
    {
        return $this->errorCode() === 'IDENTITY_TOKEN_REQUIRED';
    }

    public function isRetryable(): bool
    {
        return false;
    }
}
