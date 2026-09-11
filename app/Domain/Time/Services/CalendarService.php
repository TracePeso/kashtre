<?php

namespace App\Domain\Time\Services;

use App\Domain\Time\Enums\CalendarDayType;
use App\Domain\Time\Models\BusinessCalendar;
use App\Domain\Time\Models\BusinessCalendarDay;
use App\Domain\Time\Models\TimeAudit;
use App\Domain\Time\ValueObjects\LocalDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 — versioned business calendars.
 */
final class CalendarService
{
    public function __construct(private readonly TimeZoneCatalogueService $catalogue) {}

    public function create(
        string $tenantKey,
        string $code,
        string $name,
        ?string $ianaId = null,
        ?\DateTimeInterface $effectiveFrom = null,
    ): BusinessCalendar {
        if ($ianaId) {
            $this->catalogue->ensureKnown($ianaId);
        }

        $calendar = BusinessCalendar::query()->create([
            'tenant_key' => $tenantKey,
            'code' => strtoupper($code),
            'name' => $name,
            'iana_id' => $ianaId,
            'version_no' => 1,
            'status' => 'ACTIVE',
            'effective_from' => $effectiveFrom ?? now('UTC'),
        ]);

        TimeAudit::query()->create([
            'tenant_key' => $tenantKey,
            'actor_user_id' => Auth::id(),
            'action' => 'CALENDAR_CREATE',
            'object_type' => 'core_business_calendars',
            'object_public_id' => $calendar->public_id,
            'after' => $calendar->toArray(),
        ]);

        return $calendar;
    }

    public function setDay(
        BusinessCalendar $calendar,
        LocalDate $date,
        CalendarDayType $type,
        ?string $label = null,
    ): BusinessCalendarDay {
        return BusinessCalendarDay::query()->updateOrCreate(
            [
                'calendar_id' => $calendar->id,
                'local_date' => $date->toString(),
            ],
            [
                'day_type' => $type->value,
                'label' => $label,
            ],
        );
    }

    public function seedWeekends(BusinessCalendar $calendar, LocalDate $from, LocalDate $to): int
    {
        $count = 0;
        $cursor = $from;
        while ($cursor->toString() <= $to->toString()) {
            $dow = (int) CarbonImmutable::create($cursor->year, $cursor->month, $cursor->day)->dayOfWeekIso;
            if ($dow >= 6) {
                $this->setDay($calendar, $cursor, CalendarDayType::WEEKEND);
                $count++;
            }
            $cursor = $cursor->addDays(1);
        }

        return $count;
    }

    public function dayType(BusinessCalendar $calendar, LocalDate $date): CalendarDayType
    {
        $row = BusinessCalendarDay::query()
            ->where('calendar_id', $calendar->id)
            ->whereDate('local_date', $date->toString())
            ->first();

        if ($row) {
            return CalendarDayType::from($row->day_type);
        }

        $dow = (int) CarbonImmutable::create($date->year, $date->month, $date->day)->dayOfWeekIso;

        return $dow >= 6 ? CalendarDayType::WEEKEND : CalendarDayType::BUSINESS;
    }

    public function isBusinessDay(BusinessCalendar $calendar, LocalDate $date): bool
    {
        return $this->dayType($calendar, $date) === CalendarDayType::BUSINESS;
    }

    public function nextBusinessDay(BusinessCalendar $calendar, LocalDate $date, int $maxLookahead = 366): LocalDate
    {
        $cursor = $date->addDays(1);
        for ($i = 0; $i < $maxLookahead; $i++) {
            if ($this->isBusinessDay($calendar, $cursor)) {
                return $cursor;
            }
            $cursor = $cursor->addDays(1);
        }

        return $cursor;
    }

    public function findActive(string $tenantKey, string $code): ?BusinessCalendar
    {
        return BusinessCalendar::query()
            ->where('tenant_key', $tenantKey)
            ->where('code', strtoupper($code))
            ->where('status', 'ACTIVE')
            ->orderByDesc('version_no')
            ->first();
    }
}
