<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * SRD v6.1 Phase 1 §4 (Tenancy, Facility and Client-Space Context),
 * API_GUIDE_V6.1 §Phase 1 — who may work in a ward. Live, but
 * REQUIRE_CLIENT_SPACE_ASSIGNMENT defaults to false on Clinical's side
 * (§4 itself), so no request is refused for lacking one yet — this is a
 * primitive being staged in, not yet an enforced gate.
 *
 * CLN-CTX-010's own field list is what the request/response shapes below
 * are built from — the API guide doesn't give this endpoint family a
 * literal JSON contract the way it does for e.g. verbal orders, so field
 * names follow the SRD's own vocabulary in snake_case, matching every
 * other endpoint in this API. Raw arrays, not DTOs — an admin/governance
 * screen, same reasoning as AiUseCaseGateway.
 */
interface ClientSpaceAssignmentGateway
{
    public const TYPE_PERMANENT = 'PERMANENT';

    public const TYPE_ROSTER_DRIVEN = 'ROSTER_DRIVEN';

    public const TYPE_TEMPORARY = 'TEMPORARY';

    public const TYPE_ROTATIONAL = 'ROTATIONAL';

    public const TYPE_RELIEF = 'RELIEF';

    public const TYPE_CROSS_COVER = 'CROSS_COVER';

    public const TYPE_REMOTE_SERVICE = 'REMOTE_SERVICE';

    public const TYPE_EMERGENCY = 'EMERGENCY';

    /** @return array<int, array<string, mixed>> every assignment for one user, current and historical. */
    public function forUser(ClinicalActor $actor, int $userId): array;

    /**
     * CLN-CTX-010: user, tenant, facility, client space, assignment type,
     * permitted operational function, effective start/end, approving
     * authority, source reference.
     *
     * @param  array{user_id: int, client_space_id: int, assignment_type: string, effective_start: string, effective_end?: ?string, permitted_operational_function?: ?string, approving_authority?: ?string, source_reference?: ?string}  $payload
     * @return array<string, mixed>
     */
    public function assign(ClinicalActor $actor, array $payload): array;

    /**
     * CLN-CTX-011: expiry removes ordinary authority without deleting the
     * historical row — this ends it, never deletes it.
     *
     * @return array<string, mixed>
     */
    public function end(ClinicalActor $actor, string $assignmentId, ?string $reason = null): array;
}
