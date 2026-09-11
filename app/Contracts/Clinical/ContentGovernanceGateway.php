<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * Content Governance — v6.1 Volume 14. Clinical's own shape is already
 * well-structured for these three calls; a DTO would mean chasing field-name
 * drift for no behavioural gain (same reasoning as CareAccessGateway::teamFor()).
 */
interface ContentGovernanceGateway
{
    /** @return array<int, array<string, mixed>> */
    public function list(ClinicalActor $actor, ?string $contentType = null): array;

    /** @return array<string, mixed> */
    public function show(ClinicalActor $actor, string $versionId): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createVersion(ClinicalActor $actor, array $payload): array;

    /** @return array<string, mixed> */
    public function validateVersion(ClinicalActor $actor, string $versionId): array;
}
