<?php

namespace App\Services\Clinical\Api\Exceptions;

/**
 * 403 from one of the access gates in API Integration Guide §8 — the four
 * gates run in order (ZTNA, device/biometric, care relationship, chart lock)
 * and each returns a distinct error_code so the UI can say something useful
 * instead of "forbidden".
 *
 *   REBAC_ACCESS_DENIED                — no care relationship with this patient
 *   ZTNA_OFFSITE_MUTATION_RESTRICTED   — barred because the caller is off-premises
 *   DEVICE_SUSPENDED / DEVICE_REVOKED  — the Medical Director withdrew this device
 *   CHALLENGE_EXPIRED                  — biometric challenge used or >5 minutes old
 */
class ClinicalAccessDeniedException extends ClinicalApiException
{
    /**
     * True when Clinical is telling us a break-glass override would actually
     * help. Absent this flag, offering the clinician a break-glass button is
     * a dead end — the refusal came from a different gate.
     */
    public function requiresBreakGlass(): bool
    {
        return (bool) ($this->errors()['requires_break_glass'] ?? false);
    }

    public function isOffPremisesRestriction(): bool
    {
        return $this->errorCode() === 'ZTNA_OFFSITE_MUTATION_RESTRICTED';
    }

    /**
     * A revoked or suspended device is not a retry-later condition. The guide
     * is explicit: stop and telephone the Medical Director.
     */
    public function isDeviceWithdrawn(): bool
    {
        return in_array($this->errorCode(), ['DEVICE_SUSPENDED', 'DEVICE_REVOKED'], true);
    }
}
