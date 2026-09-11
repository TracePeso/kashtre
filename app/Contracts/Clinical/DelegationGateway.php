<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * SRD v6.1 Phase 1 §10 (Delegation, Acting Roles and Cross-Cover).
 * API_GUIDE_V6.1 §Phase 1: live. Explicit, time-limited, scoped, auditable
 * (CLN-DEL-001) — a delegator cannot delegate authority they don't
 * possess (CLN-DEL-002), and delegated authority cannot outlive either
 * the delegator's own authority or the stated expiry (CLN-DEL-003).
 */
interface DelegationGateway
{
    /** @return array<int, array<string, mixed>> */
    public function forUser(ClinicalActor $actor, int $userId): array;

    /**
     * CLN-DEL-004: delegator, delegate, permissions/bundle, scope,
     * patient/client-space constraints, start, end, reason, approval.
     *
     * @param  array{delegator_user_id: int, delegate_user_id: int, permission_bundle: string, scope: string, start: string, end: string, reason: string, client_space_id?: ?int, patient_id?: ?string}  $payload
     * @return array<string, mixed>
     */
    public function delegate(ClinicalActor $actor, array $payload): array;

    /** Ends the delegation early — never deletes the historical grant. */
    public function revoke(ClinicalActor $actor, string $delegationId, string $reason): array;
}
