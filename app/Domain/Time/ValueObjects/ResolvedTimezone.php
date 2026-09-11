<?php

namespace App\Domain\Time\ValueObjects;

use App\Domain\Time\Enums\PolicyPurpose;
use App\Domain\Time\Enums\PolicyScopeType;

final class ResolvedTimezone
{
    public function __construct(
        public readonly IanaTimezoneId $ianaId,
        public readonly ?string $policyId,
        public readonly PolicyScopeType $scopeType,
        public readonly ?string $scopeId,
        public readonly PolicyPurpose $purpose,
        public readonly string $resolutionPath,
        public readonly UtcInstant $resolvedAt,
        public readonly bool $usedFallback = false,
    ) {}

    public function toArray(): array
    {
        return [
            'ianaId' => $this->ianaId->value(),
            'policyId' => $this->policyId,
            'scopeType' => $this->scopeType->value,
            'scopeId' => $this->scopeId,
            'purpose' => $this->purpose->value,
            'resolutionPath' => $this->resolutionPath,
            'resolvedAt' => $this->resolvedAt->toIso8601(),
            'usedFallback' => $this->usedFallback,
        ];
    }
}
