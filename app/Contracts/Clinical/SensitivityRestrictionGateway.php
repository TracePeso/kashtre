<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * SRD v6.1 Phase 1 §12 (Consent, Confidentiality and Sensitive Records).
 * API_GUIDE_V6.1 §Phase 1: "live as a primitive — not yet consulted by
 * search, alerts or export endpoints". Ordinary title, permission and
 * client-space assignment do NOT automatically override a sensitive-record
 * restriction (CLN-CONS-003) — restricted content must never leak through
 * counts, snippets, alerts, search or exports (CLN-CONS-004).
 */
interface SensitivityRestrictionGateway
{
    public const LEVEL_CHART = 'CHART';

    public const LEVEL_ENCOUNTER = 'ENCOUNTER';

    public const LEVEL_DOCUMENT = 'DOCUMENT';

    public const LEVEL_SECTION = 'SECTION';

    public const LEVEL_DATA_ELEMENT = 'DATA_ELEMENT';

    /** @return array<int, array<string, mixed>> */
    public function forPatient(ClinicalActor $actor, string $patientId): array;

    /**
     * @param  array{level: string, label: string, reason: string, resource_reference?: ?string}  $payload
     * @return array<string, mixed>
     */
    public function restrict(ClinicalActor $actor, string $patientId, array $payload): array;

    /** CLN-CONS-006: effective-dated and auditable — lifting one never rewrites prior lawful access decisions. */
    public function lift(ClinicalActor $actor, string $restrictionId, string $reason): array;
}
