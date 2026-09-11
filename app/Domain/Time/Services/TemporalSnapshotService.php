<?php

namespace App\Domain\Time\Services;

use App\Domain\Time\Enums\PolicyPurpose;
use App\Domain\Time\Enums\TimeConfidence;
use App\Domain\Time\Enums\TimePrecision;
use App\Domain\Time\Enums\TimeSource;
use App\Domain\Time\ValueObjects\InstantProvenance;
use App\Domain\Time\ValueObjects\TimeContext;
use App\Domain\Time\ValueObjects\UtcInstant;

/**
 * Atomic temporal snapshot payload for consumers to persist (TIME-EVT-003).
 */
final class TemporalSnapshotService
{
    public function __construct(
        private readonly \App\Domain\Time\Contracts\Clock $clock,
        private readonly TimeZoneResolutionService $resolution,
        private readonly CivilTimeService $civil,
        private readonly BusinessDateService $businessDates,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function capture(
        ?string $tenantId = null,
        ?string $branchId = null,
        PolicyPurpose $purpose = PolicyPurpose::OPERATIONAL,
        ?UtcInstant $instant = null,
        TimeSource $source = TimeSource::SERVER,
        ?string $displayIana = null,
    ): array {
        $at = $instant ?? $this->clock->now();
        $resolved = $this->resolution->resolve(new TimeContext(
            tenantId: $tenantId,
            branchId: $branchId,
            purpose: $purpose,
            asOf: $at,
        ), $tenantId ?? 'SYSTEM');

        $tzSnapshot = $this->resolution->snapshot($resolved->ianaId, $at);
        $local = $this->civil->utcToLocal($at, $resolved->ianaId);
        $businessDate = $this->businessDates->businessDateFor(
            $at,
            new TimeContext(tenantId: $tenantId, branchId: $branchId, purpose: $purpose),
            $tenantId,
        );

        $prov = new InstantProvenance(
            instant: $at,
            source: $source,
            confidence: TimeConfidence::EXACT,
            precision: TimePrecision::MICROSECOND,
            sourceTimezone: $resolved->ianaId,
            sourceLocal: $local,
        );

        $payload = [
            'apiVersion' => config('time.api_version'),
            'utc' => $at->toIso8601(),
            'localDateTime' => $local->toString(),
            'businessDate' => $businessDate->toString(),
            'ianaId' => $resolved->ianaId->value(),
            'utcOffsetSeconds' => $tzSnapshot->utcOffsetSeconds,
            'isDst' => $tzSnapshot->isDst,
            'tzdbRelease' => $tzSnapshot->tzdbVersion,
            'policyId' => $resolved->policyId,
            'policyScope' => $resolved->scopeType->value,
            'resolutionPath' => $resolved->resolutionPath,
            'usedFallback' => $resolved->usedFallback,
            'provenance' => $prov->toArray(),
            'timezoneSnapshot' => $tzSnapshot->toArray(),
        ];

        if ($displayIana) {
            $payload['presentation'] = $this->resolution->present($at, $displayIana);
        }

        return $payload;
    }
}
