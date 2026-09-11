<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\AiUseCaseGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: AI use-case governance (v6.1 Volume 11) is
 * Clinical-owned, with no local table equivalent.
 */
class LocalAiUseCaseGateway implements AiUseCaseGateway
{
    public function list(ClinicalActor $actor): array
    {
        $this->refuse();
    }

    public function register(ClinicalActor $actor, array $payload): array
    {
        $this->refuse();
    }

    public function setStatus(ClinicalActor $actor, string $code, string $status, ?string $riskLevel = null): array
    {
        $this->refuse();
    }

    private function refuse(): void
    {
        throw new RuntimeException('AI use-case governance (v6.1 Volume 11) is only available under CLINICAL_DRIVER=api.');
    }
}
