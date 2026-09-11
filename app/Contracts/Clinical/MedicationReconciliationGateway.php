<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * Medication reconciliation at admission/transfer/discharge — SRD v6.1
 * Phase 6, API_GUIDE_V6.1 §Phase 6. The existing eMAR pipeline
 * (MedicationOrdersGateway/MarGateway) is unaffected — this is a separate
 * accountable event: what the patient was actually taking (source list),
 * decided item by item against the current chart, before the reconciliation
 * can be marked complete.
 *
 * Raw arrays, not DTOs — same reasoning as AiUseCaseGateway: a moderate-
 * traffic clinical workflow screen, not a hot per-request path, where
 * chasing field-name drift in a DTO buys nothing over Clinical's own
 * already-well-structured JSON.
 */
interface MedicationReconciliationGateway
{
    public const TYPE_ADMISSION = 'ADMISSION';

    public const TYPE_TRANSFER = 'TRANSFER';

    public const TYPE_DISCHARGE = 'DISCHARGE';

    public const DECISION_CONTINUE = 'CONTINUE';

    public const DECISION_MODIFY = 'MODIFY';

    public const DECISION_HOLD = 'HOLD';

    public const DECISION_STOP = 'STOP';

    public const DECISION_SUBSTITUTE = 'SUBSTITUTE';

    public const DECISION_DEFER_REVIEW = 'DEFER_REVIEW';

    public const DECISION_NOT_CURRENT = 'NOT_CURRENT';

    /**
     * @param  array{reconciliation_type: string, performed_by_user_id: int}  $payload
     * @return array<string, mixed>
     */
    public function start(ClinicalActor $actor, string $patientId, ?string $visitId, array $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function show(ClinicalActor $actor, string $reconciliationId): array;

    /**
     * @param  array{source: string, medication_name: string, dose?: ?string}  $item
     * @return array<string, mixed>
     */
    public function addItem(ClinicalActor $actor, string $reconciliationId, array $item): array;

    /**
     * @return array<string, mixed>
     *
     * @throws \App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException on the API driver
     */
    public function decideItem(ClinicalActor $actor, string $itemId, string $decision, int $decidedByUserId): array;

    /**
     * Refused (RECONCILIATION_ITEMS_UNDECIDED) while any item still has a
     * null decision — every item must be actioned before this can close.
     *
     * @return array<string, mixed>
     *
     * @throws \App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException on the API driver
     */
    public function complete(ClinicalActor $actor, string $reconciliationId): array;
}
