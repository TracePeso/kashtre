<?php

namespace App\Services;

use App\Models\HrCalendarEvent;
use App\Models\Organization;
use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;

class WorkingDayCalculator
{
    /**
     * @var array<string, array<string, bool>>
     */
    private array $holidayIndexCache = [];

    /**
     * @var array<string, array<int, int>>
     */
    private array $weekendDayCache = [];

    /**
     * @return array<int, CarbonImmutable>
     */
    public function dates(Organization $organization, CarbonInterface|string $startDate, CarbonInterface|string $endDate): array
    {
        return $this->workingDaySummary($organization, $startDate, $endDate)['dates'];
    }

    public function count(Organization $organization, CarbonInterface|string $startDate, CarbonInterface|string $endDate): int
    {
        return $this->workingDaySummary($organization, $startDate, $endDate, false)['count'];
    }

    /**
     * @return array{weekendDays: array<int, int>, holidayDates: array<int, string>, recurringHolidayTokens: array<int, string>}
     */
    public function previewConfig(Organization $organization): array
    {
        $holidays = HrCalendarEvent::query()
            ->forOrganization($organization)
            ->publicHolidays()
            ->active()
            ->approved()
            ->get();

        $holidayDates = [];
        $recurringHolidayTokens = [];

        foreach ($holidays as $holiday) {
            if ($holiday->repeats_yearly) {
                foreach (array_keys($this->recurringDayTokens($holiday)) as $token) {
                    $recurringHolidayTokens[$token] = true;
                }

                continue;
            }

            $cursor = CarbonImmutable::instance($holiday->starts_on)->startOfDay();
            $end = CarbonImmutable::instance($holiday->ends_on ?? $holiday->starts_on)->startOfDay();

            for (; $cursor->lte($end); $cursor = $cursor->addDay()) {
                $holidayDates[$cursor->toDateString()] = true;
            }
        }

        return [
            'weekendDays' => $this->weekendDays($organization),
            'holidayDates' => array_keys($holidayDates),
            'recurringHolidayTokens' => array_keys($recurringHolidayTokens),
        ];
    }

    /**
     * @return array{dates: array<int, CarbonImmutable>, count: int}
     */
    private function workingDaySummary(
        Organization $organization,
        CarbonInterface|string $startDate,
        CarbonInterface|string $endDate,
        bool $collectDates = true
    ): array {
        $start = $this->normalizeDate($startDate);
        $end = $this->normalizeDate($endDate);

        if ($end->lt($start)) {
            return ['dates' => [], 'count' => 0];
        }

        $weekendDays = $this->weekendDays($organization);
        $holidayIndex = $this->holidayDateIndex($organization, $start, $end);
        $dates = [];
        $count = 0;

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $dateKey = $date->toDateString();
            $isHoliday = isset($holidayIndex[$dateKey]);

            if (! in_array($date->dayOfWeek, $weekendDays, true) && ! $isHoliday) {
                $count++;

                if ($collectDates) {
                    $dates[] = $date;
                }
            }
        }

        return [
            'dates' => $dates,
            'count' => $count,
        ];
    }

    /**
     * @return array<int, int>
     */
    public function weekendDays(Organization $organization): array
    {
        $cacheKey = implode('|', [
            $organization->id,
            json_encode($organization->weekend_days ?? [], JSON_THROW_ON_ERROR),
        ]);

        if (array_key_exists($cacheKey, $this->weekendDayCache)) {
            return $this->weekendDayCache[$cacheKey];
        }

        $weekendDays = collect($organization->weekend_days ?? [0, 6])
            ->map(fn (mixed $day): ?int => is_numeric($day) ? (int) $day : null)
            ->filter(fn (?int $day): bool => $day !== null && $day >= 0 && $day <= 6)
            ->unique()
            ->values()
            ->all();

        return $this->weekendDayCache[$cacheKey] = ($weekendDays !== [] ? $weekendDays : [0, 6]);
    }

    /**
     * @return array<string, bool>
     */
    private function holidayDateIndex(Organization $organization, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $cacheKey = implode('|', [
            $organization->id,
            $start->toDateString(),
            $end->toDateString(),
        ]);

        if (array_key_exists($cacheKey, $this->holidayIndexCache)) {
            return $this->holidayIndexCache[$cacheKey];
        }

        $holidays = HrCalendarEvent::query()
            ->forOrganization($organization)
            ->publicHolidays()
            ->active()
            ->approved()
            ->where(function ($query) use ($start, $end): void {
                $query
                    ->where('repeats_yearly', true)
                    ->orWhere(function ($rangeQuery) use ($start, $end): void {
                        $rangeQuery
                            ->whereDate('starts_on', '<=', $end->toDateString())
                            ->where(function ($overlapQuery) use ($start): void {
                                $overlapQuery
                                    ->whereNull('ends_on')
                                    ->orWhereDate('ends_on', '>=', $start->toDateString());
                            });
                    });
            })
            ->get();

        $index = [];
        $recurringTokens = [];

        foreach ($holidays as $holiday) {
            if ($holiday->repeats_yearly) {
                foreach ($this->recurringDayTokens($holiday) as $token) {
                    $recurringTokens[$token] = true;
                }

                continue;
            }

            $holidayStart = CarbonImmutable::instance($holiday->starts_on)->startOfDay();
            $holidayEnd = CarbonImmutable::instance($holiday->ends_on ?? $holiday->starts_on)->startOfDay();
            $cursor = $holidayStart->greaterThan($start) ? $holidayStart : $start;
            $limit = $holidayEnd->lessThan($end) ? $holidayEnd : $end;

            for (; $cursor->lte($limit); $cursor = $cursor->addDay()) {
                $index[$cursor->toDateString()] = true;
            }
        }

        if ($recurringTokens !== []) {
            for ($date = $start; $date->lte($end); $date = $date->addDay()) {
                if (isset($recurringTokens[$date->format('m-d')])) {
                    $index[$date->toDateString()] = true;
                }
            }
        }

        return $this->holidayIndexCache[$cacheKey] = $index;
    }

    /**
     * @return array<string, bool>
     */
    private function recurringDayTokens(HrCalendarEvent $holiday): array
    {
        $tokens = [];
        $cursor = CarbonImmutable::instance($holiday->starts_on)->startOfDay();
        $end = CarbonImmutable::instance($holiday->ends_on ?? $holiday->starts_on)->startOfDay();

        for (; $cursor->lte($end); $cursor = $cursor->addDay()) {
            $tokens[$cursor->format('m-d')] = true;
        }

        return $tokens;
    }

    private function normalizeDate(CarbonInterface|string $value): CarbonImmutable
    {
        return $value instanceof CarbonInterface
            ? CarbonImmutable::instance($value)->startOfDay()
            : CarbonImmutable::parse($value)->startOfDay();
    }
}
