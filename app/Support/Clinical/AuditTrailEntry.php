<?php

namespace App\Support\Clinical;

/**
 * One entry in the compliance-sensitive event stream — break-glass grants,
 * CDSS overrides, major-transition step completions, and whatever else
 * Clinical decides is audit-worthy. Clinical's own trail
 * (`GET clinical/audit-trail`, confirmed live 2026-08-18) is a single
 * hash-chained stream covering all of these; the local driver has no
 * equivalent table, so this DTO merges its two separate ledgers
 * (`clinical_break_glass_logs`, `clinical_process_step_executions`) into the
 * same shape a panel can render either way.
 */
class AuditTrailEntry
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $actorRoles
     */
    public function __construct(
        public readonly int|string $id,
        public readonly string $action,
        public readonly ?string $patientId,
        public readonly ?int $actorUserId,
        public readonly ?string $actorName,
        public readonly array $actorRoles,
        public readonly array $context,
        public readonly ?string $createdAt,
        public readonly ?bool $isOnPremises = null,
        public readonly ?string $entryHash = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        return new self(
            id: $payload['id'] ?? 0,
            action: (string) ($payload['action'] ?? ''),
            patientId: $payload['patient_id'] ?? null,
            actorUserId: isset($payload['actor_user_id']) ? (int) $payload['actor_user_id'] : null,
            actorName: $payload['actor_name'] ?? null,
            actorRoles: $payload['actor_roles'] ?? [],
            context: $payload['context'] ?? [],
            createdAt: $payload['created_at'] ?? null,
            isOnPremises: isset($payload['is_on_premises']) ? (bool) $payload['is_on_premises'] : null,
            entryHash: $payload['entry_hash'] ?? null,
        );
    }
}
