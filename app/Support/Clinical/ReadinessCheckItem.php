<?php

namespace App\Support\Clinical;

/**
 * One line of a care transition's readiness check — API_GUIDE_V6.1 §1
 * (Care Transitions, Volume 8).
 *
 * There are exactly five `check_code` values, from four contributors — the
 * guide is explicit that this is exhaustive, not illustrative, as of this
 * build. Only `UNACKNOWLEDGED_CRITICAL_RESULT` is a hard `BLOCK`; the other
 * four are `WARNING` and do not hold the transition at `READINESS_CHECK`.
 * A check with nothing to report is simply absent — there is no "PASS with
 * evidence" row.
 */
class ReadinessCheckItem
{
    public const LABELS = [
        'UNACKNOWLEDGED_CRITICAL_RESULT' => 'Unacknowledged critical result',
        'OBSERVATION_PLAN_OVERDUE' => 'Overdue scheduled observation',
        'MEDICATION_RECONCILIATION' => 'Open MAR dose',
        'ACTIVE_ALERTS' => 'Outstanding clinical alert',
        'UNRESOLVED_TASKS' => 'Open ward task',
    ];

    public function __construct(
        public readonly string $checkCode,
        public readonly string $outcome,
        public readonly bool $isBlocking,
        /** @var array<string, mixed> */
        public readonly array $evidence = [],
    ) {
    }

    public function label(): string
    {
        return self::LABELS[$this->checkCode] ?? $this->checkCode;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        return new self(
            checkCode: (string) ($payload['check_code'] ?? ''),
            outcome: (string) ($payload['outcome'] ?? 'WARNING'),
            isBlocking: (bool) ($payload['is_blocking'] ?? false),
            evidence: (array) ($payload['evidence'] ?? []),
        );
    }
}
