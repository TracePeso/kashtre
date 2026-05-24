<?php

namespace App\Services;

use App\Models\HrCalendarEvent;
use App\Models\HrDutyRoster;
use App\Models\HrDutyRosterEntry;
use App\Models\HrPolicyVersion;
use App\Models\HrStaffUnavailability;
use App\Models\ShiftType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RosterPolicyValidator
{
    public function __construct(
        private readonly RosterPolicyResolver $policyResolver
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $candidateEntries
     * @param array{enforce_anchor?: bool} $options
     */
    public function validate(HrDutyRoster $roster, array $candidateEntries, array $options = []): void
    {
        if ($candidateEntries === []) {
            return;
        }

        $roster->loadMissing('organization');

        $policy = $this->policyResolver->requireActiveVersion(
            $roster->organization,
            CarbonImmutable::parse($roster->start_date)
        );

        [$candidateRows, $errors] = $this->candidateRows($roster, $candidateEntries);

        if ($candidateRows->isEmpty()) {
            $this->throwIfErrors($errors);
            return;
        }

        $errors = array_merge($errors, $this->policyCoverageErrors($policy, $candidateRows));
        $errors = array_merge($errors, $this->entryLevelErrors($roster, $policy, $candidateRows, $options));
        $externalRows = $this->externalRows($roster, $candidateRows);
        $allRows = $externalRows
            ->concat($candidateRows)
            ->sortBy(fn (array $row): int => $row['start_at']->getTimestamp())
            ->values();

        $errors = array_merge($errors, $this->staffCapacityErrors($policy, $allRows));

        $this->throwIfErrors($errors);
    }

    /**
     * @param array{enforce_anchor?: bool} $options
     */
    public function validatePersistedRoster(HrDutyRoster $roster, array $options = []): void
    {
        $entries = $roster->entries()
            ->get()
            ->map(fn (HrDutyRosterEntry $entry): array => [
                'organization_id' => $entry->organization_id,
                'roster_date' => $entry->roster_date?->toDateString(),
                'staff_assignment_id' => $entry->staff_assignment_id,
                'staff_uuid' => $entry->staff_uuid,
                'staff_name' => $entry->staff_name,
                'staff_cadre' => $entry->staff_cadre,
                'shift_type_id' => $entry->shift_type_id,
            ])
            ->all();

        $this->validate($roster, $entries, $options);
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array{0: Collection<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function candidateRows(HrDutyRoster $roster, array $entries): array
    {
        $errors = [];
        $shiftTypes = ShiftType::query()
            ->whereIn('id', collect($entries)->pluck('shift_type_id')->filter()->unique())
            ->get()
            ->keyBy('id');
        $rows = collect();

        foreach ($entries as $entry) {
            $shiftTypeId = $entry['shift_type_id'] ?? null;
            $staffAssignmentId = $entry['staff_assignment_id'] ?? null;
            $rosterDate = $entry['roster_date'] ?? null;

            if (! $shiftTypeId || ! $staffAssignmentId || ! $rosterDate) {
                continue;
            }

            $shiftType = $shiftTypes->get((int) $shiftTypeId);

            if (! $shiftType || (int) $shiftType->organization_id !== (int) $roster->organization_id) {
                $errors[] = 'One or more rostered shifts are not valid for this organization.';
                continue;
            }

            try {
                $date = CarbonImmutable::parse($rosterDate)->startOfDay();
            } catch (\Throwable) {
                $errors[] = 'One or more rostered dates are invalid.';
                continue;
            }

            $rows->push($this->rowForShift(
                $date,
                $shiftType,
                (int) $staffAssignmentId,
                (string) ($entry['staff_name'] ?? 'Staff member'),
                true,
                (int) $roster->id,
                $roster->organizationalUnit?->name
            ));
        }

        return [$rows, $errors];
    }

    /**
     * @param Collection<int, array<string, mixed>> $candidateRows
     * @param array{enforce_anchor?: bool} $options
     * @return array<int, string>
     */
    private function entryLevelErrors(
        HrDutyRoster $roster,
        HrPolicyVersion $policy,
        Collection $candidateRows,
        array $options
    ): array {
        $errors = [];
        $blockedEvents = $this->blockingCalendarEvents($roster);
        $unavailabilities = $this->blockingUnavailabilities($roster, $candidateRows);
        $enforceAnchor = (bool) ($options['enforce_anchor'] ?? false);
        $anchorCutoff = now()->addMinutes((int) $policy->anchor_window_minutes);

        foreach ($candidateRows as $row) {
            /** @var ShiftType $shift */
            $shift = $row['shift'];
            /** @var CarbonImmutable $date */
            $date = $row['date'];

            if (! $shift->is_rosterable) {
                $errors[] = "{$row['shift_name']} is not rosterable.";
            }

            if ((int) $row['net_minutes'] > (int) $policy->daily_net_cap_minutes) {
                $errors[] = "{$row['staff_name']} exceeds the daily net cap on {$date->toDateString()} because {$row['shift_name']} counts {$row['net_minutes']} minutes.";
            }

            $blockedEvent = $blockedEvents->first(fn (HrCalendarEvent $event): bool => $event->occursOn($date));

            if ($blockedEvent) {
                $errors[] = "{$date->toDateString()} is blocked for rosters by {$blockedEvent->title}.";
            }

            $staffUnavailable = ($unavailabilities->get((int) $row['staff_assignment_id']) ?? collect())
                ->first(fn (HrStaffUnavailability $unavailability): bool => $this->dateInRange($date, $unavailability->starts_on, $unavailability->ends_on));

            if ($staffUnavailable) {
                $label = $staffUnavailable->title ?: str_replace('_', ' ', $staffUnavailable->reason_type);
                $errors[] = "{$row['staff_name']} is unavailable on {$date->toDateString()} ({$label}).";
            }

            if ($enforceAnchor && $row['start_at']->lessThan($anchorCutoff)) {
                $errors[] = "{$row['staff_name']} has a shift inside the {$policy->anchor_window_minutes}-minute roster anchor window.";
            }
        }

        return $errors;
    }

    /**
     * @param Collection<int, array<string, mixed>> $candidateRows
     * @return array<int, string>
     */
    private function policyCoverageErrors(HrPolicyVersion $policy, Collection $candidateRows): array
    {
        $orderedDates = $candidateRows->pluck('date')->sortBy(fn (CarbonImmutable $date): int => $date->getTimestamp())->values();
        $startDate = $orderedDates->first();
        $endDate = $orderedDates->last();
        $errors = [];

        if ($startDate->toDateString() < $policy->effective_from->toDateString()) {
            $errors[] = "Roster starts before the active policy version begins on {$policy->effective_from->toDateString()}.";
        }

        if ($policy->effective_to && $endDate->toDateString() > $policy->effective_to->toDateString()) {
            $errors[] = "Roster extends after the active policy version ends on {$policy->effective_to->toDateString()}.";
        }

        return $errors;
    }

    /**
     * @param Collection<int, array<string, mixed>> $candidateRows
     * @return Collection<int, array<string, mixed>>
     */
    private function externalRows(HrDutyRoster $roster, Collection $candidateRows): Collection
    {
        $staffAssignmentIds = $candidateRows->pluck('staff_assignment_id')->unique()->values();

        if ($staffAssignmentIds->isEmpty()) {
            return collect();
        }

        $orderedDates = $candidateRows->pluck('date')->sortBy(fn (CarbonImmutable $date): int => $date->getTimestamp())->values();
        $startDate = $orderedDates->first()->subDays(7);
        $endDate = $orderedDates->last()->addDays(7);

        return HrDutyRosterEntry::query()
            ->with(['shiftType', 'dutyRoster.organizationalUnit'])
            ->where('organization_id', $roster->organization_id)
            ->whereIn('staff_assignment_id', $staffAssignmentIds)
            ->where('duty_roster_id', '!=', $roster->id)
            ->whereBetween('roster_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereHas('dutyRoster', function ($query): void {
                $query->where('status', '!=', HrDutyRoster::STATUS_ARCHIVED);
            })
            ->get()
            ->filter(fn (HrDutyRosterEntry $entry): bool => $entry->shiftType !== null && $entry->staff_assignment_id !== null)
            ->map(fn (HrDutyRosterEntry $entry): array => $this->rowForShift(
                CarbonImmutable::parse($entry->roster_date)->startOfDay(),
                $entry->shiftType,
                (int) $entry->staff_assignment_id,
                (string) $entry->staff_name,
                false,
                (int) $entry->duty_roster_id,
                $entry->dutyRoster?->organizationalUnit?->name
            ))
            ->values();
    }

    /**
     * @param Collection<int, array<string, mixed>> $allRows
     * @return array<int, string>
     */
    private function staffCapacityErrors(HrPolicyVersion $policy, Collection $allRows): array
    {
        $errors = [];

        foreach ($allRows->groupBy('staff_assignment_id') as $staffRows) {
            $sortedRows = $staffRows
                ->sortBy(fn (array $row): int => $row['start_at']->getTimestamp())
                ->values();
            $staffName = (string) ($sortedRows->first()['staff_name'] ?? 'Staff member');

            $errors = array_merge($errors, $this->restAndOverlapErrors($policy, $sortedRows, $staffName));
            $errors = array_merge($errors, $this->dailyCapErrors($policy, $sortedRows, $staffName));
            $errors = array_merge($errors, $this->weeklyCeilingErrors($policy, $sortedRows, $staffName));
            $errors = array_merge($errors, $this->consecutiveWorkDayErrors($policy, $sortedRows, $staffName));
        }

        return $errors;
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function restAndOverlapErrors(HrPolicyVersion $policy, Collection $rows, string $staffName): array
    {
        $errors = [];
        $previous = null;

        foreach ($rows as $row) {
            if (! $previous) {
                $previous = $row;
                continue;
            }

            if (! $this->involvesCandidate($previous, $row)) {
                $previous = $row;
                continue;
            }

            if ($row['start_at']->lessThan($previous['end_at'])) {
                $conflictingRow = $previous['candidate'] ? $row : $previous;
                $existingRow = $previous['candidate'] ? $previous : $row;
                $errors[] = "{$staffName} already has {$this->shiftConflictLabel($existingRow)} and cannot also take {$this->shiftConflictLabel($conflictingRow)}.";
                $previous = $row;
                continue;
            }

            $gapMinutes = $previous['end_at']->diffInMinutes($row['start_at'], false);

            if ($gapMinutes < (int) $policy->minimum_rest_gap_minutes) {
                $errors[] = "{$staffName} has only {$gapMinutes} minutes of rest before {$row['date']->toDateString()}; policy requires {$policy->minimum_rest_gap_minutes} minutes.";
            }

            $previous = $row;
        }

        return $errors;
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function dailyCapErrors(HrPolicyVersion $policy, Collection $rows, string $staffName): array
    {
        $errors = [];

        foreach ($rows->groupBy(fn (array $row): string => $row['date']->toDateString()) as $date => $dateRows) {
            if (! $dateRows->contains('candidate', true)) {
                continue;
            }

            $netMinutes = $dateRows->sum('net_minutes');

            if ($netMinutes > (int) $policy->daily_net_cap_minutes) {
                $errors[] = "{$staffName} has {$netMinutes} net minutes on {$date}; policy daily cap is {$policy->daily_net_cap_minutes} minutes.";
            }
        }

        return $errors;
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function weeklyCeilingErrors(HrPolicyVersion $policy, Collection $rows, string $staffName): array
    {
        $errors = [];

        foreach ($rows->groupBy(fn (array $row): string => $row['date']->startOfWeek(CarbonInterface::MONDAY)->toDateString()) as $weekStart => $weekRows) {
            if (! $weekRows->contains('candidate', true)) {
                continue;
            }

            $netMinutes = $weekRows->sum('net_minutes');

            if ($netMinutes > (int) $policy->weekly_absolute_ceiling_minutes) {
                $errors[] = "{$staffName} has {$netMinutes} net minutes in the week starting {$weekStart}; policy absolute ceiling is {$policy->weekly_absolute_ceiling_minutes} minutes.";
            }
        }

        return $errors;
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function consecutiveWorkDayErrors(HrPolicyVersion $policy, Collection $rows, string $staffName): array
    {
        $limit = (int) $policy->consecutive_work_days_limit;

        if ($limit <= 0) {
            return [];
        }

        $errors = [];
        $dateRows = $rows
            ->groupBy(fn (array $row): string => $row['date']->toDateString())
            ->map(fn (Collection $group): array => [
                'date' => $group->first()['date'],
                'candidate' => $group->contains('candidate', true),
            ])
            ->sortBy(fn (array $row): int => $row['date']->getTimestamp())
            ->values();
        $streakCount = 0;
        $streakHasCandidate = false;
        $previousDate = null;

        foreach ($dateRows as $dateRow) {
            /** @var CarbonImmutable $date */
            $date = $dateRow['date'];

            if ($previousDate && $previousDate->diffInDays($date) === 1) {
                $streakCount++;
            } else {
                $streakCount = 1;
                $streakHasCandidate = false;
            }

            $streakHasCandidate = $streakHasCandidate || $dateRow['candidate'];

            if ($streakCount > $limit && $streakHasCandidate) {
                $hours = round(((int) $policy->rest_after_consecutive_days_minutes) / 60, 1);
                $errors[] = "{$staffName} is scheduled for more than {$limit} consecutive work days by {$date->toDateString()}; policy requires {$hours} hours of rest.";
                break;
            }

            $previousDate = $date;
        }

        return $errors;
    }

    /**
     * @return Collection<int, HrCalendarEvent>
     */
    private function blockingCalendarEvents(HrDutyRoster $roster): Collection
    {
        return HrCalendarEvent::query()
            ->where('organization_id', $roster->organization_id)
            ->where('is_active', true)
            ->where('approval_status', HrCalendarEvent::APPROVAL_APPROVED)
            ->where('affects_rosters', true)
            ->where('blocks_rosters', true)
            ->get();
    }

    /**
     * @param Collection<int, array<string, mixed>> $candidateRows
     * @return Collection<int, Collection<int, HrStaffUnavailability>>
     */
    private function blockingUnavailabilities(HrDutyRoster $roster, Collection $candidateRows): Collection
    {
        $orderedDates = $candidateRows->pluck('date')->sortBy(fn (CarbonImmutable $date): int => $date->getTimestamp())->values();
        $startDate = $orderedDates->first();
        $endDate = $orderedDates->last();

        return HrStaffUnavailability::query()
            ->where('organization_id', $roster->organization_id)
            ->whereIn('staff_assignment_id', $candidateRows->pluck('staff_assignment_id')->unique())
            ->where('status', HrStaffUnavailability::STATUS_APPROVED)
            ->where('blocks_rosters', true)
            ->whereDate('starts_on', '<=', $endDate->toDateString())
            ->where(function ($query) use ($startDate): void {
                $query
                    ->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $startDate->toDateString());
            })
            ->get()
            ->groupBy('staff_assignment_id');
    }

    private function rowForShift(
        CarbonImmutable $date,
        ShiftType $shiftType,
        int $staffAssignmentId,
        string $staffName,
        bool $candidate,
        int $rosterId,
        ?string $clientSpaceName = null
    ): array {
        $startAt = CarbonImmutable::parse($date->toDateString().' '.$shiftType->start_time);
        $endAt = CarbonImmutable::parse($date->toDateString().' '.$shiftType->end_time);

        if ($endAt->lessThanOrEqualTo($startAt)) {
            $endAt = $endAt->addDay();
        }

        return [
            'candidate' => $candidate,
            'roster_id' => $rosterId,
            'date' => $date,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'staff_assignment_id' => $staffAssignmentId,
            'staff_name' => $staffName,
            'shift_type_id' => (int) $shiftType->id,
            'shift_name' => $shiftType->name,
            'shift_code' => $shiftType->code,
            'client_space_name' => $clientSpaceName,
            'shift' => $shiftType,
            'net_minutes' => $shiftType->effectiveNetMinutes(),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function shiftConflictLabel(array $row): string
    {
        $shiftCode = trim((string) ($row['shift_code'] ?? ''));
        $shiftName = trim((string) ($row['shift_name'] ?? 'Shift'));
        $label = $shiftCode !== '' ? $shiftCode : $shiftName;
        $date = $row['date'] instanceof CarbonInterface ? $row['date']->toDateString() : 'that date';
        $clientSpace = trim((string) ($row['client_space_name'] ?? ''));

        return $clientSpace !== ''
            ? "{$label} on {$date} in {$clientSpace}"
            : "{$label} on {$date}";
    }

    private function dateInRange(CarbonInterface $date, CarbonInterface $startsOn, ?CarbonInterface $endsOn): bool
    {
        $endsOn ??= $startsOn;

        return $date->toDateString() >= $startsOn->toDateString()
            && $date->toDateString() <= $endsOn->toDateString();
    }

    /**
     * @param array<string, mixed> $previous
     * @param array<string, mixed> $row
     */
    private function involvesCandidate(array $previous, array $row): bool
    {
        return (bool) $previous['candidate'] || (bool) $row['candidate'];
    }

    /**
     * @param array<int, string> $errors
     */
    private function throwIfErrors(array $errors): void
    {
        $errors = collect($errors)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($errors === []) {
            return;
        }

        throw ValidationException::withMessages([
            'roster' => $errors,
        ]);
    }
}
