<?php

namespace App\Services\Clinical\Api\Exceptions;

/**
 * 422 CDSS_HARD_BLOCK (API Integration Guide §10.3) — the deterministic
 * safety shield refused the prescription.
 *
 * This is not bad input. The request was well formed and the clinician is
 * allowed to make it; a clinical rule said no. The recovery is to show the
 * blocks, collect an override reason code from a clinician holding a senior
 * role, and resend with `cdss_override_reason_code` + `cdss_override_note`.
 * The override lands in Clinical's immutable audit trail.
 *
 * Do not swallow this into a generic "something went wrong" — the messages
 * are written for a clinician to read and act on.
 */
class ClinicalSafetyBlockException extends ClinicalApiException
{
    /**
     * The blocking rules, each with at least a `type` and `detail`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function hardBlocks(): array
    {
        return $this->errors()['hard_blocks'] ?? [];
    }

    /**
     * Non-blocking advisories returned alongside the blocks, when present.
     *
     * @return array<int, array<string, mixed>>
     */
    public function warnings(): array
    {
        return $this->errors()['warnings'] ?? [];
    }
}
