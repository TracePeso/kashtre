<?php

namespace App\Services;

use App\Models\HrAttendanceLedger;
use App\Models\HrCalendarEvent;
use App\Models\HrHolidayLeaveCredit;
use App\Models\HrPolicyVersion;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HolidayLeaveCreditService
{
    public function __construct(
        private readonly RosterPolicyResolver $policyResolver
    ) {
    }

    public function createForPairedPunch(HrAttendanceLedger $outPunch): void
    {
        $outPunch->loadMissing([
            'staffAssignment',
            'pairedWith',
            'rosterEntry',
        ]);

        $inPunch = $outPunch->pairedWith;

        if (! $outPunch->staffAssignment || ! $inPunch instanceof HrAttendanceLedger) {
            return;
        }

        $earnedDate = $this->earnedDate($inPunch, $outPunch);
        $holidayMatches = $this->leaveDayHolidayMatchesForPunchPair($outPunch->organization_id, $inPunch, $outPunch, $earnedDate);

        if ($holidayMatches->isEmpty()) {
            return;
        }

        $scope = $this->holidayCompensatoryScope($inPunch, $outPunch);
        $creditSetting = $this->holidayCompensatoryCreditSetting($outPunch->organization_id, $earnedDate, $scope);
        $withinHolidayCreditSetting = $this->holidayCompensatoryCreditSetting(
            $outPunch->organization_id,
            $earnedDate,
            HrPolicyVersion::HOLIDAY_COMPENSATORY_SCOPE_WITHIN_PUBLIC_HOLIDAY
        );
        $matchedDates = $holidayMatches
            ->pluck('date_key')
            ->unique()
            ->values();

        if (($creditSetting['rule'] ?? HrPolicyVersion::HOLIDAY_COMPENSATORY_CREDIT_NONE) === HrPolicyVersion::HOLIDAY_COMPENSATORY_CREDIT_NONE) {
            return;
        }

        if (
            ($creditSetting['rule'] ?? null) === HrPolicyVersion::HOLIDAY_COMPENSATORY_CREDIT_PER_PUBLIC_HOLIDAY_DATE
            || $matchedDates->count() === 1
        ) {
            if (($creditSetting['rule'] ?? null) === HrPolicyVersion::HOLIDAY_COMPENSATORY_CREDIT_PER_PUBLIC_HOLIDAY_DATE) {
                $this->createCreditsPerHolidayDate(
                    $holidayMatches,
                    $outPunch,
                    $inPunch,
                    $creditSetting,
                    $scope,
                    $withinHolidayCreditSetting
                );

                return;
            }

            foreach ($holidayMatches as $match) {
                /** @var HrCalendarEvent $holiday */
                $holiday = $match['holiday'];
                /** @var Carbon $holidayDate */
                $holidayDate = $match['date'];

                $this->createCredit(
                    $outPunch,
                    $inPunch,
                    $holiday,
                    $holidayDate,
                    $this->creditNotes([$holiday->title], $creditSetting, $scope, $withinHolidayCreditSetting),
                    $this->resolvedCreditDays(
                        $holidayMatches,
                        $outPunch,
                        $inPunch,
                        $creditSetting,
                        $scope,
                        $withinHolidayCreditSetting,
                        $match['date_key']
                    )
                );
            }

            return;
        }

        $anchorMatch = $holidayMatches->first(
            fn (array $match): bool => $match['date_key'] === $earnedDate->toDateString()
        ) ?? $holidayMatches
            ->sortBy(fn (array $match): string => sprintf(
                '%s|%s|%s',
                $match['date_key'],
                $match['holiday']->starts_on?->toDateString() ?? '',
                $match['holiday']->id
            ))
            ->first();

        if (! is_array($anchorMatch) || ! ($anchorMatch['holiday'] ?? null) instanceof HrCalendarEvent || ! ($anchorMatch['date'] ?? null) instanceof Carbon) {
            return;
        }

        $holidayTitles = $holidayMatches
            ->map(fn (array $match): string => $match['holiday']->title)
            ->unique()
            ->values()
            ->all();

        $this->createCredit(
            $outPunch,
            $inPunch,
            $anchorMatch['holiday'],
            $anchorMatch['date'],
            $this->creditNotes($holidayTitles, $creditSetting, $scope, $withinHolidayCreditSetting),
            $this->resolvedCreditDays(
                $holidayMatches,
                $outPunch,
                $inPunch,
                $creditSetting,
                $scope,
                $withinHolidayCreditSetting
            )
        );
    }

    private function createCreditsPerHolidayDate(
        Collection $holidayMatches,
        HrAttendanceLedger $outPunch,
        HrAttendanceLedger $inPunch,
        array $creditSetting,
        string $scope,
        array $withinHolidayCreditSetting
    ): void {
        foreach ($holidayMatches->groupBy('date_key') as $dateMatches) {
            $match = $dateMatches
                ->sortBy(fn (array $entry): string => sprintf(
                    '%s|%s',
                    $entry['holiday']->starts_on?->toDateString() ?? '',
                    $entry['holiday']->id
                ))
                ->first();

            if (! is_array($match) || ! ($match['holiday'] ?? null) instanceof HrCalendarEvent || ! ($match['date'] ?? null) instanceof Carbon) {
                continue;
            }

            $holidayTitles = $dateMatches
                ->map(fn (array $entry): string => $entry['holiday']->title)
                ->unique()
                ->values()
                ->all();

            $this->createCredit(
                $outPunch,
                $inPunch,
                $match['holiday'],
                $match['date'],
                $this->creditNotes($holidayTitles, $creditSetting, $scope, $withinHolidayCreditSetting),
                $this->resolvedCreditDays(
                    $dateMatches,
                    $outPunch,
                    $inPunch,
                    $creditSetting,
                    $scope,
                    $withinHolidayCreditSetting,
                    $match['date_key']
                )
            );
        }
    }

    private function createCredit(
        HrAttendanceLedger $outPunch,
        HrAttendanceLedger $inPunch,
        HrCalendarEvent $holiday,
        Carbon $earnedDate,
        string $notes,
        float $creditDays
    ): void {
        if ($creditDays <= 0) {
            return;
        }

        HrHolidayLeaveCredit::firstOrCreate(
            [
                'organization_id' => $outPunch->organization_id,
                'staff_assignment_id' => $outPunch->staff_assignment_id,
                'hr_calendar_event_id' => $holiday->id,
                'earned_on' => $earnedDate->toDateString(),
            ],
            [
                'source_in_ledger_id' => $inPunch->id,
                'source_out_ledger_id' => $outPunch->id,
                'credit_days' => $creditDays,
                'status' => HrHolidayLeaveCredit::STATUS_AVAILABLE,
                'notes' => $notes,
            ]
        );
    }

    /**
     * @return Collection<int, array{holiday: HrCalendarEvent, date: Carbon, date_key: string}>
     */
    private function leaveDayHolidayMatchesForPunchPair(
        int $organizationId,
        HrAttendanceLedger $inPunch,
        HrAttendanceLedger $outPunch,
        Carbon $earnedDate
    ): Collection {
        $coveredDates = $this->coveredDates($inPunch, $outPunch, $earnedDate);
        $holidays = $this->leaveDayHolidaysForDateRange(
            $organizationId,
            $coveredDates->first(),
            $coveredDates->last()
        );

        return $coveredDates
            ->flatMap(function (Carbon $date) use ($holidays): array {
                return $holidays
                    ->filter(fn (HrCalendarEvent $holiday): bool => $holiday->occursOn($date))
                    ->map(fn (HrCalendarEvent $holiday): array => [
                        'holiday' => $holiday,
                        'date' => $date->copy(),
                        'date_key' => $date->toDateString(),
                    ])
                    ->values()
                    ->all();
            })
            ->values();
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function coveredDates(HrAttendanceLedger $inPunch, HrAttendanceLedger $outPunch, Carbon $earnedDate): Collection
    {
        $dates = collect([
            $inPunch->occurred_at?->copy()->startOfDay(),
            $outPunch->occurred_at?->copy()->startOfDay(),
            $earnedDate->copy()->startOfDay(),
        ])
            ->filter(fn ($date): bool => $date instanceof Carbon)
            ->map(fn (Carbon $date): Carbon => $date->copy()->startOfDay())
            ->unique(fn (Carbon $date): string => $date->toDateString())
            ->sortBy(fn (Carbon $date): string => $date->toDateString())
            ->values();

        if ($dates->isEmpty()) {
            return collect([now()->startOfDay()]);
        }

        $start = $dates->first()->copy()->startOfDay();
        $end = $dates->last()->copy()->startOfDay();
        $covered = collect();

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $covered->push($cursor->copy());
        }

        return $covered;
    }

    /**
     * @return Collection<int, HrCalendarEvent>
     */
    private function leaveDayHolidaysForDateRange(int $organizationId, Carbon $startDate, Carbon $endDate): Collection
    {
        return HrCalendarEvent::query()
            ->where('organization_id', $organizationId)
            ->publicHolidays()
            ->active()
            ->approved()
            ->where('reward_type', HrCalendarEvent::REWARD_LEAVE_DAY)
            ->where(function ($query) use ($startDate, $endDate): void {
                $query
                    ->where('repeats_yearly', true)
                    ->orWhere(function ($rangeQuery) use ($startDate, $endDate): void {
                        $rangeQuery
                            ->whereDate('starts_on', '<=', $endDate->toDateString())
                            ->where(function ($overlapQuery) use ($startDate): void {
                                $overlapQuery
                                    ->whereNull('ends_on')
                                    ->orWhereDate('ends_on', '>=', $startDate->toDateString());
                            });
                    });
            })
            ->get()
            ->values();
    }

    /**
     * @return Collection<int, HrCalendarEvent>
     */
    private function leaveDayHolidaysForDate(int $organizationId, Carbon $date): Collection
    {
        return $this->leaveDayHolidaysForDateRange($organizationId, $date, $date)
            ->filter(fn (HrCalendarEvent $holiday): bool => $holiday->occursOn($date))
            ->values();
    }

    private function earnedDate(HrAttendanceLedger $inPunch, HrAttendanceLedger $outPunch): Carbon
    {
        $rosterDate = $outPunch->rosterEntry?->roster_date ?? $inPunch->rosterEntry?->roster_date;

        if ($rosterDate) {
            return Carbon::parse($rosterDate)->startOfDay();
        }

        return $inPunch->occurred_at?->copy()->startOfDay()
            ?? $outPunch->occurred_at?->copy()->startOfDay()
            ?? now()->startOfDay();
    }

    /**
     * @return array{rule: string, credit_days: float}
     */
    private function holidayCompensatoryCreditSetting(int $organizationId, Carbon $date, string $scope): array
    {
        $organization = Organization::query()->find($organizationId);

        if (! $organization) {
            return HrPolicyVersion::defaultHolidayCompensatoryCreditSettings()[$scope];
        }

        $policy = $this->policyResolver->activeVersionFor($organization, $date);

        return $policy?->holidayCompensatoryCreditSettingFor($scope)
            ?? HrPolicyVersion::defaultHolidayCompensatoryCreditSettings()[$scope];
    }

    private function holidayCompensatoryScope(HrAttendanceLedger $inPunch, HrAttendanceLedger $outPunch): string
    {
        $startDate = $inPunch->occurred_at?->copy()->startOfDay();
        $endDate = $outPunch->occurred_at?->copy()->startOfDay();

        if (
            $startDate instanceof Carbon
            && $endDate instanceof Carbon
            && $startDate->toDateString() === $endDate->toDateString()
        ) {
            return HrPolicyVersion::HOLIDAY_COMPENSATORY_SCOPE_WITHIN_PUBLIC_HOLIDAY;
        }

        return HrPolicyVersion::HOLIDAY_COMPENSATORY_SCOPE_CROSSING_PUBLIC_HOLIDAY;
    }

    /**
     * @param Collection<int, array{holiday: HrCalendarEvent, date: Carbon, date_key: string}> $holidayMatches
     * @param array{rule?: string, credit_days?: float} $creditSetting
     * @param array{rule?: string, credit_days?: float} $withinHolidayCreditSetting
     */
    private function resolvedCreditDays(
        Collection $holidayMatches,
        HrAttendanceLedger $outPunch,
        HrAttendanceLedger $inPunch,
        array $creditSetting,
        string $scope,
        array $withinHolidayCreditSetting,
        ?string $targetDateKey = null
    ): float {
        $baseCreditDays = (float) ($creditSetting['credit_days'] ?? 0);

        if ($scope !== HrPolicyVersion::HOLIDAY_COMPENSATORY_SCOPE_CROSSING_PUBLIC_HOLIDAY) {
            if ($baseCreditDays <= 0) {
                return 0.0;
            }

            return $baseCreditDays;
        }

        [$shiftStart, $shiftEnd] = $this->shiftWindow($inPunch, $outPunch);

        if (! $shiftStart || ! $shiftEnd) {
            return 0.0;
        }

        $shiftMinutes = max(1, $shiftStart->diffInMinutes($shiftEnd));
        $holidayMinutes = $this->holidayOverlapMinutes($holidayMatches, $shiftStart, $shiftEnd, $targetDateKey);

        if ($holidayMinutes <= 0) {
            return 0.0;
        }

        $ratio = min(1.0, $holidayMinutes / $shiftMinutes);
        $bucket = HrPolicyVersion::normalizeHolidayCompensatoryDynamicPercentage($ratio);
        $referenceCreditDays = (float) ($withinHolidayCreditSetting['credit_days'] ?? 0);

        if ($referenceCreditDays <= 0) {
            return 0.0;
        }

        return round($referenceCreditDays * $bucket, 2);
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function shiftWindow(HrAttendanceLedger $inPunch, HrAttendanceLedger $outPunch): array
    {
        $start = $inPunch->occurred_at?->copy();
        $end = $outPunch->occurred_at?->copy();

        if (! $start || ! $end) {
            return [null, null];
        }

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    /**
     * @param Collection<int, array{holiday: HrCalendarEvent, date: Carbon, date_key: string}> $holidayMatches
     */
    private function holidayOverlapMinutes(
        Collection $holidayMatches,
        Carbon $shiftStart,
        Carbon $shiftEnd,
        ?string $targetDateKey = null
    ): int {
        $dateKeys = $holidayMatches
            ->pluck('date_key')
            ->filter()
            ->unique()
            ->when(
                $targetDateKey !== null,
                fn (Collection $keys): Collection => $keys->filter(fn (string $key): bool => $key === $targetDateKey)
            )
            ->values();

        return $dateKeys
            ->reduce(function (int $minutes, string $dateKey) use ($shiftStart, $shiftEnd): int {
                $holidayStart = Carbon::parse($dateKey)->startOfDay();
                $holidayEnd = $holidayStart->copy()->addDay();
                $overlapStart = $shiftStart->greaterThan($holidayStart) ? $shiftStart->copy() : $holidayStart;
                $overlapEnd = $shiftEnd->lessThan($holidayEnd) ? $shiftEnd->copy() : $holidayEnd;

                if ($overlapEnd->lte($overlapStart)) {
                    return $minutes;
                }

                return $minutes + $overlapStart->diffInMinutes($overlapEnd);
            }, 0);
    }

    /**
     * @param array<int, string> $holidayTitles
     * @param array{rule?: string, credit_days?: float} $creditSetting
     * @param array{rule?: string, credit_days?: float} $withinHolidayCreditSetting
     */
    private function creditNotes(
        array $holidayTitles,
        array $creditSetting,
        string $scope,
        array $withinHolidayCreditSetting
    ): string
    {
        $scopeLabel = HrPolicyVersion::holidayCompensatoryCreditScopeOptions()[$scope] ?? $scope;
        $ruleKey = (string) ($creditSetting['rule'] ?? HrPolicyVersion::HOLIDAY_COMPENSATORY_CREDIT_PER_SHIFT);
        $ruleLabel = HrPolicyVersion::holidayCompensatoryCreditPolicyOptions()[$ruleKey] ?? $ruleKey;
        $creditDays = rtrim(rtrim(number_format((float) ($creditSetting['credit_days'] ?? 0), 2, '.', ''), '0'), '.');
        $withinCreditDays = rtrim(rtrim(number_format((float) ($withinHolidayCreditSetting['credit_days'] ?? 0), 2, '.', ''), '0'), '.');
        $holidayLabel = implode(' and ', array_values(array_unique($holidayTitles)));

        if ($scope === HrPolicyVersion::HOLIDAY_COMPENSATORY_SCOPE_CROSSING_PUBLIC_HOLIDAY) {
            return sprintf(
                'Earned by working on %s. Policy applied: %s using %s with dynamic 0%%/25%%/50%%/75%%/100%% of the within-holiday credit (%s day(s)), based on the portion of the shift worked during public holiday time.',
                $holidayLabel,
                $scopeLabel,
                $ruleLabel,
                $withinCreditDays
            );
        }

        return sprintf(
            'Earned by working on %s. Policy applied: %s using %s at %s day(s).',
            $holidayLabel,
            $scopeLabel,
            $ruleLabel,
            $creditDays
        );
    }
}
