<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * The facility-wide dashboard feed — API Integration Guide §10.5b. Distinct
 * from the per-patient read already used elsewhere in this module: that one
 * answers "what is outstanding on this chart", this answers "is anything
 * waiting for me right now", which cannot be asked one chart at a time.
 *
 * Polling only — Clinical has no push channel for this yet (guide's own
 * §10.5b warning). A badge built on this should poll, not hold a
 * long-lived connection open.
 *
 * No local equivalent: this reads Clinical's diagnostic/lab critical-value
 * pipeline, which the local driver never had (lab/imaging are out of scope
 * for this module's local tables). LocalCriticalAlertsGateway returns an
 * empty feed rather than inventing one.
 */
interface CriticalAlertsGateway
{
    public const SCOPE_MY_PATIENTS = 'MY_PATIENTS';

    public const SCOPE_WARD = 'WARD';

    public const SCOPE_ALL = 'ALL';

    /**
     * @return array{alerts: array<int, array<string, mixed>>, count: int, by_severity: array<string, int>, oldest_unacknowledged_at: ?string}
     */
    public function feed(
        ClinicalActor $actor,
        string $scope = self::SCOPE_MY_PATIENTS,
        ?string $wardCode = null,
        ?string $severityTier = null,
        bool $includeAcknowledged = false,
        int $limit = 100,
    ): array;

    /**
     * An alert is only closed when a clinician says they have seen it — and
     * a discharge is blocked while one is outstanding, so this is not
     * cosmetic.
     */
    public function acknowledge(ClinicalActor $actor, int|string $alertId): void;

    /**
     * SRD v6.1 Phase 8 — the closed-loop follow-up chain: acknowledge (above,
     * pre-existing) → review → action → close, each a distinct, separately
     * recorded state rather than one flag. review() refuses
     * (ALERT_NOT_ACKNOWLEDGED) before acknowledgement — technical receipt is
     * not clinical review.
     *
     * @return array<string, mixed>
     *
     * @throws \App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException on the API driver
     */
    public function review(ClinicalActor $actor, int|string $alertId, string $reviewNotes): array;

    /** @return array<string, mixed> */
    public function action(ClinicalActor $actor, int|string $alertId, string $actionTaken): array;

    /**
     * Refuses (ALERT_NOT_REVIEWED) before review() — closure always needs a
     * documented rationale, not just an action.
     *
     * @return array<string, mixed>
     *
     * @throws \App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException on the API driver
     */
    public function close(ClinicalActor $actor, int|string $alertId, string $closureReason): array;
}
