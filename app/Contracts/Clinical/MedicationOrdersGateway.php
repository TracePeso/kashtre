<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\MedicationOrderRecord;
use App\Support\Clinical\SafetyEvaluation;

/**
 * Prescribing — API Integration Guide §10.3.
 *
 * Two refusals every caller must handle, both arriving as HTTP 422 over the
 * API and as typed exceptions here:
 *
 *   CDSS_HARD_BLOCK               the safety shield said no. Recover by
 *                                 collecting an override reason code from a
 *                                 clinician holding a senior role and calling
 *                                 prescribe() again with it set.
 *   EXTERNAL_FULFILMENT_REQUIRED  nothing in the catalogue matched. Recover by
 *                                 calling again with confirmExternalFulfilment,
 *                                 which generates a referral rather than
 *                                 leaving the clinician with no way to
 *                                 prescribe.
 *
 * Neither is an error to swallow — both are clinical conversations the UI has
 * to have with the prescriber.
 */
interface MedicationOrdersGateway
{
    /**
     * @return array<int, MedicationOrderRecord>
     */
    public function activeOrders(ClinicalActor $actor, string $patientId): array;

    /**
     * Dry-run the safety shield without placing an order, so the UI can warn
     * before the clinician commits. Cheap to call; do it on form review.
     *
     * @param  array{requested_term: string, strength_descriptor?: ?string, dose_amount: float, route_code: string, frequency_code: string, is_nephrotoxic?: bool, max_mg_per_kg?: ?float, age_years?: ?int}  $draft
     */
    public function evaluateSafety(ClinicalActor $actor, string $patientId, array $draft): SafetyEvaluation;

    /**
     * Places a prescription.
     *
     * @param  array{requested_term: string, strength_descriptor?: ?string, dose_amount: float, route_code: string, frequency_code: string, clinical_indication?: ?string, duration_days?: ?int, urgency?: string, is_nephrotoxic?: bool, max_mg_per_kg?: ?float, age_years?: ?int}  $draft
     * @param  string|null  $overrideReasonCode  supplied on the retry after a CDSS block
     * @param  bool  $confirmExternalFulfilment  supplied on the retry after an unmatched item
     * @param  string|null  $idempotencyKey  one key per *logical prescription*, held
     *                                       stable across retries of the same attempt —
     *                                       including the CDSS-override and
     *                                       external-fulfilment retries above, which
     *                                       are continuations of one clinical decision,
     *                                       not new ones. A key generated per HTTP
     *                                       attempt provides no protection at all.
     *
     * @throws \App\Services\Clinical\Api\Exceptions\ClinicalSafetyBlockException
     * @throws \App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException
     */
    public function prescribe(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        array $draft,
        ?string $overrideReasonCode = null,
        ?string $overrideNote = null,
        bool $confirmExternalFulfilment = false,
        ?string $idempotencyKey = null,
    ): MedicationOrderRecord;

    public function cancel(
        ClinicalActor $actor,
        int|string $orderId,
        string $cancellationReasonCode,
    ): void;
}
