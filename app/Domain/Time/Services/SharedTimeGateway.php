<?php

namespace App\Domain\Time\Services;

use App\Domain\Time\Contracts\Clock;
use App\Domain\Time\Enums\PolicyPurpose;
use App\Domain\Time\Enums\PolicyScopeType;
use App\Domain\Time\Models\TimeTenantSetting;
use App\Domain\Time\ValueObjects\InstantProvenance;
use App\Domain\Time\ValueObjects\LocalDate;
use App\Domain\Time\ValueObjects\ResolvedTimezone;
use App\Domain\Time\ValueObjects\TimeContext;
use App\Domain\Time\ValueObjects\TimezoneSnapshot;
use App\Domain\Time\ValueObjects\UtcInstant;

/**
 * Phase 7 consumer facade — single entry for modules (integration / cutover).
 */
final class SharedTimeGateway
{
    public function __construct(
        private readonly Clock $clock,
        private readonly ClockHealthService $clockHealth,
        private readonly TimeZoneCatalogueService $catalogue,
        private readonly TimeZonePolicyService $policies,
        private readonly TimeZoneResolutionService $resolution,
        private readonly TimePolicyCacheService $policyCache,
        private readonly CivilTimeService $civil,
        private readonly ProvenanceService $provenance,
        private readonly BusinessDateService $businessDates,
        private readonly ScheduleService $schedules,
        private readonly CalendarService $calendars,
        private readonly FinancialPeriodService $periods,
        private readonly DeviceTimeService $devices,
        private readonly TemporalSnapshotService $snapshots,
        private readonly LeapSecondService $leapSeconds,
        private readonly TzdbHealthService $tzdbHealth,
        private readonly MonotonicClock $monotonic,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('time.enabled', false);
    }

    public function now(?string $nodeKey = null): UtcInstant
    {
        if ($nodeKey) {
            return $this->clockHealth->trustedNow($nodeKey);
        }

        return $this->clock->now();
    }

    public function resolveTimezone(
        ?string $tenantId = null,
        ?string $branchId = null,
        ?string $facilityId = null,
        PolicyPurpose $purpose = PolicyPurpose::OPERATIONAL,
        ?UtcInstant $asOf = null,
        bool $useCache = true,
    ): ResolvedTimezone {
        $context = new TimeContext(
            tenantId: $tenantId,
            branchId: $branchId,
            facilityId: $facilityId,
            purpose: $purpose,
            asOf: $asOf,
        );

        if ($useCache) {
            return $this->policyCache->resolve($context, $tenantId ?? 'SYSTEM');
        }

        return $this->resolution->resolve($context, $tenantId ?? 'SYSTEM');
    }

    public function snapshot(string $ianaId, ?UtcInstant $asOf = null): TimezoneSnapshot
    {
        return $this->resolution->snapshot($ianaId, $asOf);
    }

    public function businessDate(?string $tenantId = null, ?UtcInstant $at = null, ?string $branchId = null): LocalDate
    {
        return $this->businessDates->businessDateFor(
            $at,
            new TimeContext(tenantId: $tenantId, branchId: $branchId),
            $tenantId,
        );
    }

    public function serverProvenance(?UtcInstant $at = null): InstantProvenance
    {
        return $this->provenance->fromServer($at);
    }

    public function present(UtcInstant $instant, string $ianaId): array
    {
        return $this->resolution->present($instant, $ianaId);
    }

    public function tenantSettings(string $tenantKey): TimeTenantSetting
    {
        return TimeTenantSetting::forTenant($tenantKey);
    }

    public function updateTenantSettings(
        string $tenantKey,
        ?int $rolloverOffsetMinutes = null,
        ?bool $enforceFinancialPeriods = null,
        ?bool $allowUserPresentation = null,
    ): TimeTenantSetting {
        $row = TimeTenantSetting::forTenant($tenantKey);
        $row->update(array_filter([
            'day_rollover_offset_minutes' => $rolloverOffsetMinutes,
            'enforce_financial_periods' => $enforceFinancialPeriods,
            'allow_user_presentation_timezone' => $allowUserPresentation,
        ], fn ($v) => $v !== null));

        return $row->fresh();
    }

    public function setScopeTimezone(
        string $tenantKey,
        PolicyScopeType $scope,
        string $subjectPublicId,
        string $ianaId,
        PolicyPurpose $purpose = PolicyPurpose::OPERATIONAL,
        ?string $reason = null,
    ): \App\Domain\Time\Models\TimeZonePolicy {
        $draft = $this->policies->draft(
            $tenantKey,
            $scope,
            $subjectPublicId,
            $ianaId,
            $purpose,
            reason: $reason ?? 'Gateway scope timezone set',
        );

        return $this->policies->activate($draft, $reason ?? 'Gateway scope timezone set');
    }

    public function clock(): Clock
    {
        return $this->clock;
    }

    public function catalogue(): TimeZoneCatalogueService
    {
        return $this->catalogue;
    }

    public function policies(): TimeZonePolicyService
    {
        return $this->policies;
    }

    public function resolution(): TimeZoneResolutionService
    {
        return $this->resolution;
    }

    public function policyCache(): TimePolicyCacheService
    {
        return $this->policyCache;
    }

    public function civil(): CivilTimeService
    {
        return $this->civil;
    }

    public function provenance(): ProvenanceService
    {
        return $this->provenance;
    }

    public function businessDates(): BusinessDateService
    {
        return $this->businessDates;
    }

    public function schedules(): ScheduleService
    {
        return $this->schedules;
    }

    public function calendars(): CalendarService
    {
        return $this->calendars;
    }

    public function periods(): FinancialPeriodService
    {
        return $this->periods;
    }

    public function devices(): DeviceTimeService
    {
        return $this->devices;
    }

    public function snapshots(): TemporalSnapshotService
    {
        return $this->snapshots;
    }

    public function leapSeconds(): LeapSecondService
    {
        return $this->leapSeconds;
    }

    public function tzdbHealth(): TzdbHealthService
    {
        return $this->tzdbHealth;
    }

    public function monotonic(): MonotonicClock
    {
        return $this->monotonic;
    }

    public function clockHealth(): ClockHealthService
    {
        return $this->clockHealth;
    }

    public function ensurePlatformFallback(): void
    {
        $fallback = (string) config('time.platform_fallback_timezone', 'UTC');
        $this->catalogue->ensureKnown($fallback);

        $exists = \App\Domain\Time\Models\TimeZonePolicy::query()
            ->where('tenant_key', 'SYSTEM')
            ->where('scope_type', PolicyScopeType::PLATFORM->value)
            ->where('subject_public_id', 'PLATFORM')
            ->where('status', 'ACTIVE')
            ->exists();

        if (! $exists) {
            $draft = $this->policies->draft(
                'SYSTEM',
                PolicyScopeType::PLATFORM,
                'PLATFORM',
                $fallback,
                PolicyPurpose::OPERATIONAL,
                reason: 'Platform fallback seed',
            );
            $this->policies->activate($draft, 'Platform fallback seed');
        }
    }
}
