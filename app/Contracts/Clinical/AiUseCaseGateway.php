<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * AI use-case governance administration — v6.1 Volume 11. API_GUIDE_V6.1 §3's
 * own documented gap: no exposed endpoint existed to register or
 * deactivate/prohibit a use case for a facility, even though
 * AiUseCaseGovernor already enforces exactly this on the 5 existing AI
 * endpoints.
 */
interface AiUseCaseGateway
{
    /** @return array<int, array<string, mixed>> */
    public function list(ClinicalActor $actor): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function register(ClinicalActor $actor, array $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function setStatus(ClinicalActor $actor, string $code, string $status, ?string $riskLevel = null): array;
}
