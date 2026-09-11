<?php

namespace App\Domain\Time\Services;

use App\Domain\Time\Contracts\Clock;
use App\Domain\Time\Models\TimeTenantSetting;
use App\Domain\Time\ValueObjects\IanaTimezoneId;
use App\Domain\Time\ValueObjects\LocalDate;
use App\Domain\Time\ValueObjects\LocalDateTime;
use App\Domain\Time\ValueObjects\TimeContext;
use App\Domain\Time\ValueObjects\UtcInstant;
use Carbon\CarbonImmutable;

/**
 * Phase 3 — business dates, half-open local-day windows, day rollover.
 */
final class BusinessDateService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly TimeZoneResolutionService $resolution,
        private readonly CivilTimeService $civil,
    ) {}

    public function rolloverOffsetMinutes(?string $tenantKey = null): int
    {
        if ($tenantKey && \Illuminate\Support\Facades\Schema::hasTable('core_time_tenant_settings')) {
            return TimeTenantSetting::forTenant($tenantKey)->day_rollover_offset_minutes;
        }

        return (int) config('time.day_rollover.offset_minutes', 0);
    }

    public function businessDateFor(
        ?UtcInstant $instant = null,
        ?TimeContext $context = null,
        ?string $tenantKey = null,
        ?int $rolloverOffsetMinutes = null,
    ): LocalDate {
        $at = $instant ?? $this->clock->now();
        $resolved = $this->resolution->resolve($context ?? new TimeContext(tenantId: $tenantKey), $tenantKey);
        $offset = $rolloverOffsetMinutes ?? $this->rolloverOffsetMinutes($tenantKey ?? $context?->tenantId);

        $local = $at->toCarbon()->setTimezone($resolved->ianaId->toDateTimeZone());
        if ($offset !== 0) {
            $local = $local->subMinutes($offset);
        }

        return LocalDate::of((int) $local->year, (int) $local->month, (int) $local->day);
    }

    /**
     * Half-open [start, end) UTC window for a local business date.
     *
     * @return array{start: UtcInstant, end: UtcInstant, localDate: string, ianaId: string}
     */
    public function localDayWindow(
        LocalDate $localDate,
        IanaTimezoneId|string $ianaId,
        ?int $rolloverOffsetMinutes = null,
    ): array {
        $id = $ianaId instanceof IanaTimezoneId ? $ianaId : IanaTimezoneId::of($ianaId);
        $offset = $rolloverOffsetMinutes ?? (int) config('time.day_rollover.offset_minutes', 0);

        $startLocal = LocalDateTime::of($localDate->year, $localDate->month, $localDate->day, 0, 0, 0);
        $endDate = $localDate->addDays(1);
        $endLocal = LocalDateTime::of($endDate->year, $endDate->month, $endDate->day, 0, 0, 0);

        $start = $this->civil->localToUtc($startLocal, $id);
        $end = $this->civil->localToUtc($endLocal, $id);

        $startInstant = $start['instant']->toCarbon()->addMinutes($offset);
        $endInstant = $end['instant']->toCarbon()->addMinutes($offset);

        return [
            'start' => UtcInstant::fromDateTime($startInstant),
            'end' => UtcInstant::fromDateTime($endInstant),
            'localDate' => $localDate->toString(),
            'ianaId' => $id->value(),
            'rolloverOffsetMinutes' => $offset,
        ];
    }

    public function today(?TimeContext $context = null, ?string $tenantKey = null): LocalDate
    {
        return $this->businessDateFor(null, $context, $tenantKey);
    }

    /**
     * Reporting window as inclusive local dates mapped to half-open UTC.
     *
     * @return array{start: UtcInstant, end: UtcInstant}
     */
    public function reportingWindow(
        LocalDate $fromInclusive,
        LocalDate $toInclusive,
        IanaTimezoneId|string $ianaId,
    ): array {
        $start = $this->localDayWindow($fromInclusive, $ianaId);
        $endExclusive = $this->localDayWindow($toInclusive.addDays(1), $ianaId);

        return [
            'start' => $start['start'],
            'end' => $endExclusive['start'],
        ];
    }
}
