<?php

namespace App\Domain\Time\ValueObjects;

use App\Domain\Time\Enums\PolicyPurpose;
use App\Domain\Time\Enums\PolicyScopeType;

/**
 * Immutable resolution context for timezone policy lookup.
 */
final class TimeContext
{
    public function __construct(
        public readonly ?string $tenantId = null,
        public readonly ?string $branchId = null,
        public readonly ?string $facilityId = null,
        public readonly ?string $clientSpaceId = null,
        public readonly ?string $userId = null,
        public readonly ?string $deviceId = null,
        public readonly PolicyPurpose $purpose = PolicyPurpose::OPERATIONAL,
        public readonly ?UtcInstant $asOf = null,
    ) {}

    public function scopeCandidates(): array
    {
        $candidates = [];
        if ($this->deviceId) {
            $candidates[] = [PolicyScopeType::DEVICE, $this->deviceId];
        }
        if ($this->userId) {
            $candidates[] = [PolicyScopeType::USER, $this->userId];
        }
        if ($this->clientSpaceId) {
            $candidates[] = [PolicyScopeType::CLIENT_SPACE, $this->clientSpaceId];
        }
        if ($this->facilityId) {
            $candidates[] = [PolicyScopeType::FACILITY, $this->facilityId];
        }
        if ($this->branchId) {
            $candidates[] = [PolicyScopeType::BRANCH, $this->branchId];
        }
        if ($this->tenantId) {
            $candidates[] = [PolicyScopeType::TENANT, $this->tenantId];
        }
        $candidates[] = [PolicyScopeType::PLATFORM, null];

        return $candidates;
    }
}
