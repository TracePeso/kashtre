<?php

namespace App\Services\Clinical\Api\Exceptions;

/**
 * 422 for every refusal that is not a CDSS hard block (API Integration Guide
 * §6). The guide is emphatic that 422 carries real meaning here: a validation
 * failure, an implausible observation value, a missing reason code and a
 * malformed field all arrive with this status, distinguished by error_code.
 *
 * Two codes have specific recoveries:
 *
 *   EXTERNAL_FULFILMENT_REQUIRED  — the item is not in the catalogue. Resend
 *                                   with confirm_external_fulfilment: true to
 *                                   generate an external referral instead of
 *                                   blocking the clinician.
 *   PHYSIOLOGICAL_RANGE_EXCEEDED  — the value is outside what is compatible
 *                                   with life. Almost always a unit error;
 *                                   re-prompt rather than offering an override.
 *   PROCESS_STEP_BLOCKED          — a transition step's rules were not met
 *                                   (mandatory skip, wrong role, an unmet
 *                                   completion_rule). Resend with
 *                                   override_reason_code (category
 *                                   PROCESS_OVERRIDE) + override_note to
 *                                   proceed anyway; both are written to the
 *                                   audit trail. blockedBy() names why.
 */
class ClinicalRuleRefusedException extends ClinicalApiException
{
    public function requiresExternalFulfilment(): bool
    {
        return $this->errorCode() === 'EXTERNAL_FULFILMENT_REQUIRED';
    }

    public function isPhysiologicallyImplausible(): bool
    {
        return $this->errorCode() === 'PHYSIOLOGICAL_RANGE_EXCEEDED';
    }

    public function isProcessStepBlocked(): bool
    {
        return $this->errorCode() === 'PROCESS_STEP_BLOCKED';
    }

    /**
     * Human-readable reasons the step was blocked, present on
     * PROCESS_STEP_BLOCKED.
     *
     * @return array<int, string>
     */
    public function blockedBy(): array
    {
        return $this->errors()['blocked_by'] ?? [];
    }

    /**
     * Items the catalogue could not resolve, present on
     * EXTERNAL_FULFILMENT_REQUIRED.
     *
     * @return array<int, mixed>
     */
    public function unmatched(): array
    {
        return $this->errors()['unmatched'] ?? [];
    }

    /**
     * Laravel-style field errors, when the refusal was ordinary validation.
     *
     * @return array<string, mixed>
     */
    public function fieldErrors(): array
    {
        $errors = $this->errors();
        unset($errors['error_code']);

        return $errors;
    }
}
