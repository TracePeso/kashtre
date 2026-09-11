<?php

namespace App\Support\Clinical;

/**
 * A Care Transition (v6.1 Volume 8) — the new governance layer sitting
 * alongside the older `/clinical/transitions/*` step-execution flow
 * (`ProcessInstance`), not replacing it. This record tracks readiness,
 * discharge attestation and observation-plan re-anchoring; the older flow
 * still does the actual bed allocation, order halting and chart locking.
 *
 * `readinessHistory` only comes back from `show()` — the `start()` response
 * carries `status` (READY/READINESS_CHECK) but not why, per the guide.
 */
class CareTransitionRecord
{
    public const TYPE_INTERNAL_TRANSFER = 'INTERNAL_TRANSFER';

    public const TYPE_INTERFACILITY_TRANSFER = 'INTERFACILITY_TRANSFER';

    public const TYPE_DISCHARGE = 'DISCHARGE';

    public const TYPE_REFERRAL = 'REFERRAL';

    public const TYPE_TEMPORARY_LEAVE = 'TEMPORARY_LEAVE';

    public const TYPE_DEATH = 'DEATH';

    /**
     * @param  array<int, ReadinessCheckItem>  $readinessHistory
     */
    public function __construct(
        public readonly string $publicId,
        public readonly ?string $patientPublicId,
        public readonly ?string $encounterPublicId,
        public readonly string $transitionType,
        public readonly string $status,
        public readonly ?string $plannedAt,
        public readonly ?string $effectiveAt,
        public readonly ?string $completedAt,
        public readonly int $recordVersion = 1,
        public readonly array $readinessHistory = [],
    ) {
    }

    public function isReady(): bool
    {
        return $this->status === 'READY';
    }

    public function isBlocked(): bool
    {
        return $this->status === 'READINESS_CHECK';
    }

    public function isInternalTransfer(): bool
    {
        return $this->transitionType === self::TYPE_INTERNAL_TRANSFER;
    }

    public function isDischarge(): bool
    {
        return $this->transitionType === self::TYPE_DISCHARGE;
    }

    /**
     * Flattened evidence from every readiness run this transition has had —
     * `show()` returns `readiness_history` as one entry per evaluation, but
     * there has only ever been one (evaluated once, at `POST`, per the
     * guide) so the latest run's items are what a panel actually wants.
     *
     * @return array<int, ReadinessCheckItem>
     */
    public function latestReadinessItems(): array
    {
        $latest = $this->readinessHistory[array_key_last($this->readinessHistory) ?? -1] ?? null;

        return $latest['items'] ?? [];
    }

    public function latestReadinessOutcome(): ?string
    {
        $latest = $this->readinessHistory[array_key_last($this->readinessHistory) ?? -1] ?? null;

        return $latest['outcome'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        $history = array_values(array_filter((array) ($payload['readiness_history'] ?? []), 'is_array'));

        return new self(
            publicId: (string) ($payload['public_id'] ?? ''),
            patientPublicId: $payload['patient_public_id'] ?? null,
            encounterPublicId: $payload['encounter_public_id'] ?? null,
            transitionType: (string) ($payload['transition_type'] ?? ''),
            status: (string) ($payload['status'] ?? ''),
            plannedAt: $payload['planned_at'] ?? null,
            effectiveAt: $payload['effective_at'] ?? null,
            completedAt: $payload['completed_at'] ?? null,
            recordVersion: (int) ($payload['record_version'] ?? 1),
            readinessHistory: array_map(fn (array $run) => [
                'outcome' => $run['outcome'] ?? null,
                'evaluated_at' => $run['evaluated_at'] ?? null,
                'items' => array_map(
                    fn (array $item) => ReadinessCheckItem::fromApi($item),
                    array_values(array_filter((array) ($run['items'] ?? []), 'is_array')),
                ),
            ], $history),
        );
    }
}
