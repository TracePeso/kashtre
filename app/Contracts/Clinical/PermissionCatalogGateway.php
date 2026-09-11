<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * SRD v6.1 Phase 1 §6 (Atomic Permissions, Bundles and Official Titles) —
 * the published 163-code atomic permission catalogue, API_GUIDE_V6.1
 * §Phase 1. This is the atomic-code catalogue only — Clinical does not
 * assign these codes to users; Main registers and assigns them on its own
 * side (CLN-OWN-011's split). resource_family/action are always derived
 * server-side from the code, never accepted as input.
 *
 * A real gap the guide flags: most of these 163 codes aren't enforced
 * anywhere yet on several endpoints that clearly should check one — don't
 * assume a 403 for "wrong permission" there today.
 */
interface PermissionCatalogGateway
{
    /**
     * @param  array{resource_family?: ?string, risk_tier?: ?string, search?: ?string}  $filters
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function list(ClinicalActor $actor, array $filters = []): array;

    /**
     * @param  array{code: string, description: string, risk_tier: string, default_scope?: ?string, requires_credential?: bool, break_glass_eligible?: bool, audit_level?: ?string}  $payload
     * @return array<string, mixed>
     */
    public function register(ClinicalActor $actor, array $payload): array;

    /** Retires the code — CLN-PERM-005: it stays resolvable for historical audit but is never newly assigned again. */
    public function deactivate(ClinicalActor $actor, string $permissionId): void;
}
