<?php

namespace App\Services;

use App\Models\HrDutyRoster;
use App\Models\HrPolicyVersion;
use App\Models\HrStaffRosteringProfile;
use App\Models\HrStaffUnavailability;
use App\Models\ShiftType;
use App\Models\StaffAssignment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RosterDraftGenerator
{
    public function __construct(
        private readonly RosterPolicyResolver $policyResolver,
        private readonly RosterPolicyValidator $rosterPolicyValidator,
        private readonly WorkingDayCalculator $workingDayCalculator
    ) {
    }

    /**
     * @param Collection<int, StaffAssignment> $eligibleAssignments
     * @param Collection<int, ShiftType> $shiftTypes
     * @param array<int|string, array<string, mixed>> $entries
     * @param array{avoid_entries?: array<int|string, array<string, mixed>>, variation_seed?: string} $options
     * @return array<int|string, array<string, string>>
     */
    public function generate(
        HrDutyRoster $roster,
        Collection $eligibleAssignments,
        Collection $shiftTypes,
        array $entries = [],
        array $options = []
    ): array {
        $roster->loadMissing('organization');

        $policy = $this->policyResolver->requireActiveVersion(
            $roster->organization,
            CarbonImmutable::parse($roster->start_date)
        );

        $eligibleById = $eligibleAssignments->keyBy(fn (StaffAssignment $assignment): string => (string) $assignment->id);
        $shiftTypes = $shiftTypes
            ->filter(fn (ShiftType $shiftType): bool => $shiftType->is_active && $shiftType->is_rosterable)
            ->filter(fn (ShiftType $shiftType): bool => $shiftType->effectiveNetMinutes() > 0)
            ->sort(function (ShiftType $left, ShiftType $right): int {
                return [
                    $left->start_time,
                    $left->name,
                    $left->id,
                ] <=> [
                    $right->start_time,
                    $right->name,
                    $right->id,
                ];
            })
            ->values();

        if ($eligibleById->isEmpty()) {
            throw ValidationException::withMessages([
                'roster' => 'No active staff remain in this title for automatic roster generation.',
            ]);
        }

        if ($shiftTypes->isEmpty()) {
            throw ValidationException::withMessages([
                'roster' => 'Add at least one active rosterable shift type before generating this roster.',
            ]);
        }

        $dates = $this->dateRange($roster);

        if ($dates === []) {
            throw ValidationException::withMessages([
                'roster' => 'The selected roster period has no workdays in the HR calendar.',
            ]);
        }

        $profilesByStaffId = $eligibleAssignments
            ->mapWithKeys(fn (StaffAssignment $assignment): array => [
                (string) $assignment->id => $assignment->rosteringProfile,
            ]);
        $teamNames = $roster->teamNames();
        $teamAssignments = $roster->teamAssignments();
        $staffIds = $eligibleAssignments
            ->map(fn (StaffAssignment $assignment): string => (string) $assignment->id)
            ->values()
            ->all();
        $validDates = collect($dates)->mapWithKeys(fn (CarbonImmutable $date): array => [$date->toDateString() => true]);
        $shiftTypeById = $shiftTypes->keyBy(fn (ShiftType $shiftType): string => (string) $shiftType->id);
        $hasNightShifts = $shiftTypes->contains(fn (ShiftType $shiftType): bool => $shiftType->crossesMidnight());
        $hasDayShifts = $shiftTypes->contains(fn (ShiftType $shiftType): bool => ! $shiftType->crossesMidnight());
        $availabilityMatrix = $this->availabilityMatrix($roster, $eligibleAssignments, $dates);
        $generatedEntries = $this->sanitizeSelections($entries, $eligibleById, $shiftTypeById, $validDates);
        $avoidPayload = is_array($options['avoid_entries'] ?? null) ? $options['avoid_entries'] : [];
        $avoidEntries = $this->sanitizeSelections($avoidPayload, $eligibleById, $shiftTypeById, $validDates);
        $variationSeed = filled($options['variation_seed'] ?? null) ? (string) $options['variation_seed'] : '';
        $payloads = $this->payloadsFromSelections($roster, $generatedEntries, $eligibleById, $shiftTypeById);
        $weekendDays = $this->weekendDays($roster);
        $state = $this->buildState($payloads, $shiftTypeById, $weekendDays, $teamAssignments);
        $weeklyTargets = $this->weeklyTargets($dates, $policy);
        $weekDateKeysByWeek = collect($dates)
            ->groupBy(fn (CarbonImmutable $date): string => $date->startOfWeek(CarbonInterface::MONDAY)->toDateString())
            ->map(fn (Collection $weekDates): array => $weekDates
                ->map(fn (CarbonImmutable $date): string => $date->toDateString())
                ->values()
                ->all())
            ->all();
        foreach (collect($dates)->groupBy(fn (CarbonImmutable $date): string => $date->startOfWeek(CarbonInterface::MONDAY)->toDateString()) as $weekKey => $weekDates) {
            $weekTarget = (int) ($weeklyTargets[$weekKey] ?? 0);

            if ($weekTarget <= 0) {
                continue;
            }

            while (true) {
                $candidate = $this->nextWorkloadTargetAssignment(
                    $roster,
                    $eligibleAssignments,
                    $profilesByStaffId,
                    $shiftTypes,
                    $generatedEntries,
                    $payloads,
                    $weekDates->values()->all(),
                    (string) $weekKey,
                    $weekTarget,
                    $state,
                    $availabilityMatrix,
                    $weekendDays,
                    $staffIds,
                    $hasDayShifts,
                    $hasNightShifts,
                    $avoidEntries,
                    $teamNames,
                    $teamAssignments,
                    $variationSeed,
                    now()
                );

                if (! $candidate) {
                    break;
                }

                /** @var StaffAssignment $assignment */
                $assignment = $candidate['assignment'];
                /** @var ShiftType $shiftType */
                $shiftType = $candidate['shift_type'];
                /** @var CarbonImmutable $date */
                $date = $candidate['date'];
                $staffId = (string) $assignment->id;
                $dateKey = $date->toDateString();

                $generatedEntries[$staffId][$dateKey] = (string) $shiftType->id;
                $payloads[] = $candidate['payload'];
                $state = $this->applyAssignmentState($state, $staffId, (string) $weekKey, $date, $shiftType, $weekendDays, $teamAssignments);
            }
        }

        $generatedEntries = $this->ensureMinimumCoverageAssignments(
            $roster,
            $eligibleAssignments,
            $profilesByStaffId,
            $shiftTypes,
            $generatedEntries,
            $payloads,
            $state,
            $dates,
            $availabilityMatrix,
            $weekendDays,
            $teamAssignments
        );

        if ($teamNames !== []) {
            return $generatedEntries;
        }

        $generatedEntries = $this->rebalanceWorkloadDateSpread(
            $roster,
            $generatedEntries,
            $eligibleById,
            $shiftTypes,
            $shiftTypeById,
            $profilesByStaffId,
            $dates,
            now(),
            $availabilityMatrix
        );
        $generatedEntries = $this->rebalanceUniformDateShiftMix(
            $roster,
            $generatedEntries,
            $eligibleById,
            $shiftTypes,
            $shiftTypeById,
            $profilesByStaffId,
            $dates,
            now(),
            $availabilityMatrix
        );
        $generatedEntries = $this->rebalanceWorkloadNightShiftSpread(
            $roster,
            $generatedEntries,
            $eligibleById,
            $shiftTypes,
            $shiftTypeById,
            $profilesByStaffId,
            $dates,
            now(),
            $availabilityMatrix
        );

        return $generatedEntries;
    }

    /**
     * @param array<int|string, array<string, mixed>> $entries
     * @param Collection<string, StaffAssignment> $eligibleById
     * @param Collection<string, ShiftType> $shiftTypeById
     * @param Collection<string, bool> $validDates
     * @return array<int|string, array<string, string>>
     */
    private function sanitizeSelections(
        array $entries,
        Collection $eligibleById,
        Collection $shiftTypeById,
        Collection $validDates
    ): array {
        $sanitized = [];

        foreach ($entries as $staffId => $dateMap) {
            $staffKey = (string) $staffId;

            if (! $eligibleById->has($staffKey) || ! is_array($dateMap)) {
                continue;
            }

            foreach ($dateMap as $date => $shiftTypeId) {
                $dateKey = (string) $date;
                $shiftKey = trim((string) $shiftTypeId);

                if ($shiftKey === '' || ! $validDates->has($dateKey) || ! $shiftTypeById->has($shiftKey)) {
                    continue;
                }

                $sanitized[$staffKey][$dateKey] = $shiftKey;
            }
        }

        return $sanitized;
    }

    /**
     * @param Collection<int, StaffAssignment> $eligibleAssignments
     * @param Collection<string, HrStaffRosteringProfile|null> $profilesByStaffId
     * @param Collection<int, ShiftType> $shiftTypes
     * @param array<int|string, array<string, string>> $generatedEntries
     * @param array<int, array<string, mixed>> $payloads
     * @param array{
     *     assignment_counts: array<string, int>,
     *     total_minutes: array<string, int>,
     *     week_minutes: array<string, array<string, int>>,
     *     staff_shift_counts: array<string, array<string, int>>,
     *     global_shift_counts: array<string, int>,
     *     date_counts: array<string, int>,
     *     date_shift_counts: array<string, array<string, int>>,
     *     last_assigned_on: array<string, CarbonImmutable>,
     *     day_shift_counts: array<string, int>,
     *     night_shift_counts: array<string, int>,
     *     weekend_shift_counts: array<string, int>,
     *     last_shift_was_night: array<string, bool>,
     *     consecutive_night_streaks: array<string, int>
     * } $state
     * @param array<int, CarbonImmutable> $dates
     * @param array<string, array<string, string>> $availabilityMatrix
     * @param array<int, int> $weekendDays
     * @param array<string, string> $teamAssignments
     * @return array<int|string, array<string, string>>
     */
    private function ensureMinimumCoverageAssignments(
        HrDutyRoster $roster,
        Collection $eligibleAssignments,
        Collection $profilesByStaffId,
        Collection $shiftTypes,
        array $generatedEntries,
        array $payloads,
        array $state,
        array $dates,
        array $availabilityMatrix,
        array $weekendDays,
        array $teamAssignments
    ): array {
        if ($this->generatedSelectionCount($generatedEntries) > 0 || $shiftTypes->isEmpty() || $dates === []) {
            return $generatedEntries;
        }

        foreach ($eligibleAssignments as $assignment) {
            $staffId = (string) $assignment->id;
            $profile = $profilesByStaffId->get($staffId);

            foreach ($dates as $date) {
                $dateKey = $date->toDateString();

                if (filled($generatedEntries[$staffId][$dateKey] ?? null)) {
                    continue;
                }

                if ($this->availabilityStatus($availabilityMatrix, $staffId, $date) === HrStaffUnavailability::STATUS_APPROVED) {
                    continue;
                }

                foreach ($this->preferredFallbackShiftTypes($shiftTypes, $profile) as $shiftType) {
                    if (! $this->canAutoAssignShift($profile, $shiftType, $date, $staffId, $state, $availabilityMatrix)) {
                        continue;
                    }

                    $candidatePayload = $this->payloadForSelection($roster, $assignment, $shiftType, $date);

                    try {
                        $this->rosterPolicyValidator->validate($roster, [...$payloads, $candidatePayload]);
                    } catch (ValidationException) {
                        continue;
                    }

                    $generatedEntries[$staffId][$dateKey] = (string) $shiftType->id;
                    $payloads[] = $candidatePayload;
                    $weekKey = $date->startOfWeek(CarbonInterface::MONDAY)->toDateString();
                    $state = $this->applyAssignmentState($state, $staffId, $weekKey, $date, $shiftType, $weekendDays, $teamAssignments);
                    break;
                }
            }
        }

        if ($this->generatedSelectionCount($generatedEntries) > 0) {
            return $generatedEntries;
        }

        foreach ($eligibleAssignments as $assignment) {
            $staffId = (string) $assignment->id;
            $profile = $profilesByStaffId->get($staffId);

            foreach ($dates as $date) {
                $dateKey = $date->toDateString();

                if (filled($generatedEntries[$staffId][$dateKey] ?? null)) {
                    continue;
                }

                if ($this->availabilityStatus($availabilityMatrix, $staffId, $date) === HrStaffUnavailability::STATUS_APPROVED) {
                    continue;
                }

                foreach ($this->preferredFallbackShiftTypes($shiftTypes, $profile) as $shiftType) {
                    if (! $this->canAutoAssignShift($profile, $shiftType, $date, $staffId, $state, $availabilityMatrix)) {
                        continue;
                    }

                    $generatedEntries[$staffId][$dateKey] = (string) $shiftType->id;
                    break 2;
                }
            }
        }

        return $generatedEntries;
    }

    /**
     * @param array<int|string, array<string, string>> $entries
     * @param Collection<string, StaffAssignment> $eligibleById
     * @param Collection<string, ShiftType> $shiftTypeById
     * @return array<int, array<string, mixed>>
     */
    private function payloadsFromSelections(
        HrDutyRoster $roster,
        array $entries,
        Collection $eligibleById,
        Collection $shiftTypeById
    ): array {
        $payloads = [];

        foreach ($entries as $staffId => $dateMap) {
            $assignment = $eligibleById->get((string) $staffId);

            if (! $assignment) {
                continue;
            }

            foreach ($dateMap as $date => $shiftTypeId) {
                $shiftType = $shiftTypeById->get((string) $shiftTypeId);

                if (! $shiftType) {
                    continue;
                }

                $payloads[] = $this->payloadForSelection(
                    $roster,
                    $assignment,
                    $shiftType,
                    CarbonImmutable::parse($date)->startOfDay()
                );
            }
        }

        return $payloads;
    }

    /**
     * @param array<int|string, array<string, string>> $entries
     */
    private function assignedCountFor(array $entries, string $dateKey, string $shiftTypeId): int
    {
        $count = 0;

        foreach ($entries as $dateMap) {
            if (($dateMap[$dateKey] ?? null) !== $shiftTypeId) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    /**
     * @param Collection<int, StaffAssignment> $eligibleAssignments
     * @param Collection<string, HrStaffRosteringProfile|null> $profilesByStaffId
     * @param array<int|string, array<string, string>> $generatedEntries
     * @param array{
     *     assignment_counts: array<string, int>,
     *     total_minutes: array<string, int>,
     *     week_minutes: array<string, array<string, int>>,
     *     staff_shift_counts: array<string, array<string, int>>,
     *     global_shift_counts: array<string, int>,
     *     date_counts: array<string, int>,
     *     date_shift_counts: array<string, array<string, int>>,
     *     last_assigned_on: array<string, CarbonImmutable>,
     *     day_shift_counts: array<string, int>,
     *     night_shift_counts: array<string, int>,
     *     weekend_shift_counts: array<string, int>,
     *     last_shift_was_night: array<string, bool>,
     *     consecutive_night_streaks: array<string, int>
     * } $state
     * @param array<string, array<string, string>> $availabilityMatrix
     * @param array<int, int> $weekendDays
     * @param array<int, string> $staffIds
     * @param array<int, string> $rejectedStaffIds
     * @param array<int|string, array<string, string>> $avoidEntries
     * @param array<int, string> $teamNames
     * @param array<string, string> $teamAssignments
     */
    private function nextCoverageAssignment(
        Collection $eligibleAssignments,
        Collection $profilesByStaffId,
        array $generatedEntries,
        CarbonImmutable $date,
        ShiftType $shiftType,
        array $state,
        string $weekKey,
        int $weekTarget,
        array $weekDateKeys,
        array $availabilityMatrix,
        array $weekendDays,
        array $staffIds,
        array $rejectedStaffIds,
        array $avoidEntries,
        array $teamNames,
        array $teamAssignments,
        string $variationSeed
    ): ?StaffAssignment {
        $dateKey = $date->toDateString();
        $shiftTypeId = (string) $shiftType->id;
        $shiftMinutes = $shiftType->effectiveNetMinutes();

        return $eligibleAssignments
            ->reject(function (StaffAssignment $assignment) use ($generatedEntries, $dateKey, $shiftType, $date, $state, $availabilityMatrix, $rejectedStaffIds): bool {
                $staffId = (string) $assignment->id;
                $profile = $assignment->rosteringProfile;

                return in_array($staffId, $rejectedStaffIds, true)
                    || filled($generatedEntries[$staffId][$dateKey] ?? null)
                    || ! $this->canAutoAssignShift($profile, $shiftType, $date, $staffId, $state, $availabilityMatrix);
            })
            ->sort(function (StaffAssignment $left, StaffAssignment $right) use ($state, $weekKey, $weekTarget, $weekDateKeys, $date, $profilesByStaffId, $availabilityMatrix, $weekendDays, $staffIds, $shiftType, $shiftTypeId, $shiftMinutes, $dateKey, $avoidEntries, $teamNames, $teamAssignments, $variationSeed): int {
                $leftStaffId = (string) $left->id;
                $rightStaffId = (string) $right->id;
                $leftProfile = $profilesByStaffId->get($leftStaffId);
                $rightProfile = $profilesByStaffId->get($rightStaffId);
                $leftWeekMinutes = (int) ($state['week_minutes'][$leftStaffId][$weekKey] ?? 0);
                $rightWeekMinutes = (int) ($state['week_minutes'][$rightStaffId][$weekKey] ?? 0);

                return [
                    $this->pendingLeavePenalty($availabilityMatrix, $leftStaffId, $date),
                    $this->previousDateAssignmentPenalty($avoidEntries, $leftStaffId, $dateKey),
                    $this->assignmentDatePriority($leftProfile, $date),
                    $this->shiftPreferenceRank($leftProfile, $shiftType),
                    $this->teamCohesionPenalty($state, $leftStaffId, $dateKey, $teamAssignments),
                    $this->repeatedShiftTypePenalty($state, $leftStaffId, $shiftType, $date),
                    $this->postNightRecoveryPenalty($state, $leftStaffId, $date),
                    $this->targetOvershootPenalty($leftWeekMinutes, $weekTarget, $shiftMinutes),
                    ...$this->teamFairnessRank($state, $teamNames, $leftStaffId, $teamAssignments),
                    ...$this->projectedAssignmentFairnessRank($state, $staffIds, $leftStaffId, $weekKey, $date, $shiftType, $weekendDays),
                    ...$this->shiftChoiceFairnessRank($state, $staffIds, $leftStaffId, $weekKey, $date, $shiftType, $weekendDays),
                    $leftWeekMinutes,
                    $this->weekendLoadPenalty($state, $leftStaffId, $date, $weekendDays),
                    $state['staff_shift_counts'][$leftStaffId][$shiftTypeId] ?? 0,
                    $state['assignment_counts'][$leftStaffId] ?? 0,
                    $state['day_shift_counts'][$leftStaffId] ?? 0,
                    $state['night_shift_counts'][$leftStaffId] ?? 0,
                    $state['total_minutes'][$leftStaffId] ?? 0,
                    $this->staffWeekStaggerRank($weekDateKeys, $leftStaffId, $dateKey, $variationSeed),
                    $this->lastAssignedDistance($state['last_assigned_on'][$leftStaffId] ?? null, $date),
                    $this->variantRank($variationSeed, $dateKey, $shiftTypeId, $leftStaffId),
                    mb_strtolower((string) $left->staff_name),
                ] <=> [
                    $this->pendingLeavePenalty($availabilityMatrix, $rightStaffId, $date),
                    $this->previousDateAssignmentPenalty($avoidEntries, $rightStaffId, $dateKey),
                    $this->assignmentDatePriority($rightProfile, $date),
                    $this->shiftPreferenceRank($rightProfile, $shiftType),
                    $this->teamCohesionPenalty($state, $rightStaffId, $dateKey, $teamAssignments),
                    $this->repeatedShiftTypePenalty($state, $rightStaffId, $shiftType, $date),
                    $this->postNightRecoveryPenalty($state, $rightStaffId, $date),
                    $this->targetOvershootPenalty($rightWeekMinutes, $weekTarget, $shiftMinutes),
                    ...$this->teamFairnessRank($state, $teamNames, $rightStaffId, $teamAssignments),
                    ...$this->projectedAssignmentFairnessRank($state, $staffIds, $rightStaffId, $weekKey, $date, $shiftType, $weekendDays),
                    ...$this->shiftChoiceFairnessRank($state, $staffIds, $rightStaffId, $weekKey, $date, $shiftType, $weekendDays),
                    $rightWeekMinutes,
                    $this->weekendLoadPenalty($state, $rightStaffId, $date, $weekendDays),
                    $state['staff_shift_counts'][$rightStaffId][$shiftTypeId] ?? 0,
                    $state['assignment_counts'][$rightStaffId] ?? 0,
                    $state['day_shift_counts'][$rightStaffId] ?? 0,
                    $state['night_shift_counts'][$rightStaffId] ?? 0,
                    $state['total_minutes'][$rightStaffId] ?? 0,
                    $this->staffWeekStaggerRank($weekDateKeys, $rightStaffId, $dateKey, $variationSeed),
                    $this->lastAssignedDistance($state['last_assigned_on'][$rightStaffId] ?? null, $date),
                    $this->variantRank($variationSeed, $dateKey, $shiftTypeId, $rightStaffId),
                    mb_strtolower((string) $right->staff_name),
                ];
            })
            ->first();
    }

    /**
     * @param Collection<int, StaffAssignment> $eligibleAssignments
     * @param Collection<string, HrStaffRosteringProfile|null> $profilesByStaffId
     * @param Collection<int, ShiftType> $shiftTypes
     * @param array<int|string, array<string, string>> $generatedEntries
     * @param array<int, array<string, mixed>> $payloads
     * @param array<int, CarbonImmutable> $weekDates
     * @param array<string, array<string, string>> $availabilityMatrix
     * @param array<int, int> $weekendDays
     * @param array<int, string> $staffIds
     * @param bool $hasDayShifts
     * @param bool $hasNightShifts
     * @param array<int|string, array<string, string>> $avoidEntries
     * @param array<int, string> $teamNames
     * @param array<string, string> $teamAssignments
     * @return array{assignment: StaffAssignment, shift_type: ShiftType, date: CarbonImmutable, payload: array<string, mixed>}|null
     */
    private function nextWorkloadTargetAssignment(
        HrDutyRoster $roster,
        Collection $eligibleAssignments,
        Collection $profilesByStaffId,
        Collection $shiftTypes,
        array $generatedEntries,
        array $payloads,
        array $weekDates,
        string $weekKey,
        int $weekTarget,
        array $state,
        array $availabilityMatrix,
        array $weekendDays,
        array $staffIds,
        bool $hasDayShifts,
        bool $hasNightShifts,
        array $avoidEntries,
        array $teamNames,
        array $teamAssignments,
        string $variationSeed,
        CarbonInterface $anchorCutoff
    ): ?array {
        $dateKeys = collect($weekDates)
            ->map(fn (CarbonImmutable $date): string => $date->toDateString())
            ->values()
            ->all();
        $candidates = [];

        foreach ($eligibleAssignments as $assignment) {
            $staffId = (string) $assignment->id;
            $profile = $profilesByStaffId->get($staffId);
            $currentWeekMinutes = (int) ($state['week_minutes'][$staffId][$weekKey] ?? 0);

            if ($currentWeekMinutes >= $weekTarget) {
                continue;
            }

            $remainingWeekMinutes = max(0, $weekTarget - $currentWeekMinutes);

            foreach ($weekDates as $date) {
                $dateKey = $date->toDateString();

                if (filled($generatedEntries[$staffId][$dateKey] ?? null)) {
                    continue;
                }

                if ($this->availabilityStatus($availabilityMatrix, $staffId, $date) === HrStaffUnavailability::STATUS_APPROVED) {
                    continue;
                }

                foreach ($shiftTypes as $shiftType) {
                    $shiftTypeId = (string) $shiftType->id;
                    $shiftMinutes = $shiftType->effectiveNetMinutes();

                    if (! $this->canAutoAssignShift($profile, $shiftType, $date, $staffId, $state, $availabilityMatrix)) {
                        continue;
                    }

                    if (! $this->shouldAssignShift($currentWeekMinutes, $weekTarget, $shiftMinutes)) {
                        continue;
                    }

                    $candidatePayload = $this->payloadForSelection($roster, $assignment, $shiftType, $date);

                    try {
                        $this->rosterPolicyValidator->validate($roster, [...$payloads, $candidatePayload]);
                    } catch (ValidationException) {
                        continue;
                    }

                    $candidates[] = [
                        'rank' => [
                            $this->pendingLeavePenalty($availabilityMatrix, $staffId, $date),
                            $this->previousDateAssignmentPenalty($avoidEntries, $staffId, $dateKey),
                            $this->previousShiftAssignmentPenalty($avoidEntries, $staffId, $dateKey, $shiftTypeId),
                            $this->assignmentDatePriority($profile, $date),
                            $this->teamCohesionPenalty($state, $staffId, $dateKey, $teamAssignments),
                            $this->nightRecoveryShiftPenalty($state, $staffId, $shiftType, $date),
                            $this->consecutiveNightPenalty($state, $staffId, $shiftType, $date),
                            $this->repeatedShiftTypePenalty($state, $staffId, $shiftType, $date),
                            $this->shiftPreferenceRank($profile, $shiftType),
                            $this->targetOvershootPenalty($currentWeekMinutes, $weekTarget, $shiftMinutes),
                            ...$this->teamFairnessRank($state, $teamNames, $staffId, $teamAssignments),
                            $this->dateShiftFamilyConcentrationPenalty($state, $dateKey, $shiftType, $hasDayShifts, $hasNightShifts),
                            $this->projectedSpread($state['date_counts'] ?? [], $dateKeys, $dateKey, 1),
                            $this->lastAssignedDistance($state['last_assigned_on'][$staffId] ?? null, $date),
                            $this->staffWeekStaggerRank($dateKeys, $staffId, $dateKey, $variationSeed),
                            ...$this->projectedAssignmentFairnessRank($state, $staffIds, $staffId, $weekKey, $date, $shiftType, $weekendDays),
                            ...$this->shiftChoiceFairnessRank($state, $staffIds, $staffId, $weekKey, $date, $shiftType, $weekendDays),
                            $this->projectedNestedSpread($state['date_shift_counts'] ?? [], $dateKeys, $dateKey, $shiftTypeId, 1),
                            $this->targetDistance($shiftMinutes, $remainingWeekMinutes),
                            $state['week_minutes'][$staffId][$weekKey] ?? 0,
                            $this->weekendLoadPenalty($state, $staffId, $date, $weekendDays),
                            $state['date_counts'][$dateKey] ?? 0,
                            $state['date_shift_counts'][$dateKey][$shiftTypeId] ?? 0,
                            $this->variantRank($variationSeed, $dateKey, $staffId, $shiftTypeId),
                            $dateKey,
                            mb_strtolower((string) $assignment->staff_name),
                            $shiftType->start_time,
                            $shiftType->id,
                        ],
                        'assignment' => $assignment,
                        'shift_type' => $shiftType,
                        'date' => $date,
                        'payload' => $candidatePayload,
                    ];
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn (array $left, array $right): int => $left['rank'] <=> $right['rank']);

        return $candidates[0];
    }

    /**
     * @param array<int|string, array<string, string>> $generatedEntries
     * @param Collection<string, StaffAssignment> $eligibleById
     * @param Collection<int, ShiftType> $shiftTypes
     * @param Collection<string, ShiftType> $shiftTypeById
     * @param Collection<string, HrStaffRosteringProfile|null> $profilesByStaffId
     * @param array<int, CarbonImmutable> $dates
     * @param array<string, array<string, string>> $availabilityMatrix
     * @return array<int|string, array<string, string>>
     */
    private function rebalanceWorkloadDateSpread(
        HrDutyRoster $roster,
        array $generatedEntries,
        Collection $eligibleById,
        Collection $shiftTypes,
        Collection $shiftTypeById,
        Collection $profilesByStaffId,
        array $dates,
        CarbonInterface $anchorCutoff,
        array $availabilityMatrix
    ): array {
        foreach (collect($dates)->groupBy(fn (CarbonImmutable $date): string => $date->startOfWeek(CarbonInterface::MONDAY)->toDateString()) as $weekDates) {
            $weekDateMap = collect($weekDates)
                ->mapWithKeys(fn (CarbonImmutable $date): array => [$date->toDateString() => $date]);
            $dateKeys = $weekDateMap->keys()->values()->all();

            if (count($dateKeys) <= 1) {
                continue;
            }

            $maxPasses = max(1, count($dateKeys) * max(1, $eligibleById->count()) * max(1, $shiftTypes->count()));

            for ($pass = 0; $pass < $maxPasses; $pass++) {
                $dateCounts = $this->dateAssignmentCounts($generatedEntries, $dateKeys);
                $highestCount = max($dateCounts);
                $lowestCount = min($dateCounts);

                if (($highestCount - $lowestCount) <= 1) {
                    break;
                }

                $overfilledDates = collect($dateCounts)
                    ->filter(fn (int $count): bool => $count === $highestCount)
                    ->keys()
                    ->sort()
                    ->values()
                    ->all();
                $underfilledDates = collect($dateCounts)
                    ->filter(fn (int $count): bool => $count === $lowestCount)
                    ->keys()
                    ->sort()
                    ->values()
                    ->all();
                $moved = false;

                foreach ($overfilledDates as $overfilledDate) {
                    $staffIdsOnDate = collect($generatedEntries)
                        ->filter(fn (array $dateMap): bool => filled($dateMap[$overfilledDate] ?? null))
                        ->keys()
                        ->map(fn ($staffId): string => (string) $staffId)
                        ->sortBy(fn (string $staffId): string => mb_strtolower((string) $eligibleById->get($staffId)?->staff_name))
                        ->values()
                        ->all();

                    foreach ($underfilledDates as $underfilledDate) {
                        $targetDate = $weekDateMap->get($underfilledDate);

                        if (! $targetDate) {
                            continue;
                        }

                        foreach ($staffIdsOnDate as $staffId) {
                            if (filled($generatedEntries[$staffId][$underfilledDate] ?? null)) {
                                continue;
                            }

                            $assignment = $eligibleById->get($staffId);
                            $currentShift = $shiftTypeById->get((string) ($generatedEntries[$staffId][$overfilledDate] ?? ''));

                            if (! $assignment || ! $currentShift) {
                                continue;
                            }

                            $candidateShiftTypes = collect([$currentShift])
                                ->merge($shiftTypes)
                                ->unique(fn (ShiftType $shiftType): string => (string) $shiftType->id)
                                ->values();

                            foreach ($candidateShiftTypes as $shiftType) {
                                $trialEntries = $generatedEntries;
                                unset($trialEntries[$staffId][$overfilledDate]);
                                $trialEntries[$staffId][$underfilledDate] = (string) $shiftType->id;

                                if (! $this->profileAllowsRebalancedSelection(
                                    $profilesByStaffId->get($staffId),
                                    $shiftType,
                                    $targetDate,
                                    $staffId,
                                    $trialEntries,
                                    $shiftTypeById,
                                    $availabilityMatrix
                                )) {
                                    continue;
                                }

                                try {
                                    $this->rosterPolicyValidator->validate(
                                        $roster,
                                        $this->payloadsFromSelections($roster, $trialEntries, $eligibleById, $shiftTypeById)
                                    );
                                } catch (ValidationException) {
                                    continue;
                                }

                                $generatedEntries = $trialEntries;
                                $moved = true;
                                break 4;
                            }
                        }
                    }
                }

                if (! $moved) {
                    break;
                }
            }
        }

        return $generatedEntries;
    }

    /**
     * @param array<int|string, array<string, string>> $generatedEntries
     * @param array<int, string> $dateKeys
     * @return array<string, int>
     */
    private function dateAssignmentCounts(array $generatedEntries, array $dateKeys): array
    {
        $counts = array_fill_keys($dateKeys, 0);

        foreach ($generatedEntries as $dateMap) {
            foreach ($dateMap as $date => $shiftTypeId) {
                $dateKey = (string) $date;

                if (filled($shiftTypeId) && array_key_exists($dateKey, $counts)) {
                    $counts[$dateKey]++;
                }
            }
        }

        return $counts;
    }

    /**
     * @param array<int|string, array<string, string>> $generatedEntries
     * @param Collection<string, StaffAssignment> $eligibleById
     * @param Collection<int, ShiftType> $shiftTypes
     * @param Collection<string, ShiftType> $shiftTypeById
     * @param Collection<string, HrStaffRosteringProfile|null> $profilesByStaffId
     * @param array<int, CarbonImmutable> $dates
     * @param array<string, array<string, string>> $availabilityMatrix
     * @return array<int|string, array<string, string>>
     */
    private function rebalanceUniformDateShiftMix(
        HrDutyRoster $roster,
        array $generatedEntries,
        Collection $eligibleById,
        Collection $shiftTypes,
        Collection $shiftTypeById,
        Collection $profilesByStaffId,
        array $dates,
        CarbonInterface $anchorCutoff,
        array $availabilityMatrix
    ): array {
        if (
            $eligibleById->count() <= 1
            || ! $shiftTypes->contains(fn (ShiftType $shiftType): bool => $shiftType->crossesMidnight())
            || ! $shiftTypes->contains(fn (ShiftType $shiftType): bool => ! $shiftType->crossesMidnight())
        ) {
            return $generatedEntries;
        }

        $dateMap = collect($dates)
            ->mapWithKeys(fn (CarbonImmutable $date): array => [$date->toDateString() => $date]);
        $dayShiftTypes = $shiftTypes
            ->filter(fn (ShiftType $shiftType): bool => ! $shiftType->crossesMidnight())
            ->values();
        $nightShiftTypes = $shiftTypes
            ->filter(fn (ShiftType $shiftType): bool => $shiftType->crossesMidnight())
            ->values();

        foreach ($dateMap as $dateKey => $date) {
            $assignedStaffIds = collect($generatedEntries)
                ->filter(fn (array $dateSelections): bool => filled($dateSelections[$dateKey] ?? null))
                ->keys()
                ->map(fn ($staffId): string => (string) $staffId)
                ->sort()
                ->values()
                ->all();

            if (count($assignedStaffIds) <= 1) {
                continue;
            }

            $assignedShiftTypes = collect($assignedStaffIds)
                ->map(fn (string $staffId): ?ShiftType => $shiftTypeById->get((string) ($generatedEntries[$staffId][$dateKey] ?? '')))
                ->filter();

            if ($assignedShiftTypes->count() <= 1) {
                continue;
            }

            $allNight = $assignedShiftTypes->every(fn (ShiftType $shiftType): bool => $shiftType->crossesMidnight());
            $allDay = $assignedShiftTypes->every(fn (ShiftType $shiftType): bool => ! $shiftType->crossesMidnight());

            if (! $allNight && ! $allDay) {
                continue;
            }

            $candidateShiftTypes = ($allNight ? $dayShiftTypes : $nightShiftTypes)
                ->sortBy(fn (ShiftType $shiftType): array => [$shiftType->start_time, mb_strtolower($shiftType->name), $shiftType->id])
                ->values();

            foreach ($assignedStaffIds as $staffId) {
                foreach ($candidateShiftTypes as $candidateShiftType) {
                    $trialEntries = $generatedEntries;
                    $trialEntries[$staffId][$dateKey] = (string) $candidateShiftType->id;

                    if (! $this->profileAllowsRebalancedSelection(
                        $profilesByStaffId->get($staffId),
                        $candidateShiftType,
                        $date,
                        $staffId,
                        $trialEntries,
                        $shiftTypeById,
                        $availabilityMatrix
                    )) {
                        continue;
                    }

                    try {
                        $this->rosterPolicyValidator->validate(
                            $roster,
                            $this->payloadsFromSelections($roster, $trialEntries, $eligibleById, $shiftTypeById)
                        );
                    } catch (ValidationException) {
                        continue;
                    }

                    $generatedEntries = $trialEntries;
                    continue 3;
                }
            }
        }

        return $generatedEntries;
    }

    /**
     * @param array<int|string, array<string, string>> $generatedEntries
     * @param Collection<string, StaffAssignment> $eligibleById
     * @param Collection<int, ShiftType> $shiftTypes
     * @param Collection<string, ShiftType> $shiftTypeById
     * @param Collection<string, HrStaffRosteringProfile|null> $profilesByStaffId
     * @param array<int, CarbonImmutable> $dates
     * @param array<string, array<string, string>> $availabilityMatrix
     * @return array<int|string, array<string, string>>
     */
    private function rebalanceWorkloadNightShiftSpread(
        HrDutyRoster $roster,
        array $generatedEntries,
        Collection $eligibleById,
        Collection $shiftTypes,
        Collection $shiftTypeById,
        Collection $profilesByStaffId,
        array $dates,
        CarbonInterface $anchorCutoff,
        array $availabilityMatrix
    ): array {
        if (
            $eligibleById->count() <= 1
            || ! $shiftTypes->contains(fn (ShiftType $shiftType): bool => $shiftType->crossesMidnight())
            || ! $shiftTypes->contains(fn (ShiftType $shiftType): bool => ! $shiftType->crossesMidnight())
        ) {
            return $generatedEntries;
        }

        $dateMap = collect($dates)
            ->mapWithKeys(fn (CarbonImmutable $date): array => [$date->toDateString() => $date]);
        $staffIds = $eligibleById->keys()->map(fn ($staffId): string => (string) $staffId)->values()->all();
        $maxPasses = max(1, count($staffIds) * max(1, $dateMap->count()));

        for ($pass = 0; $pass < $maxPasses; $pass++) {
            $nightCounts = $this->staffNightShiftCounts($generatedEntries, $staffIds, $shiftTypeById);
            $highestCount = max($nightCounts);
            $lowestCount = min($nightCounts);

            if (($highestCount - $lowestCount) <= 1) {
                break;
            }

            $overloadedStaffIds = collect($nightCounts)
                ->filter(fn (int $count): bool => $count === $highestCount)
                ->keys()
                ->sort()
                ->values()
                ->all();
            $underloadedStaffIds = collect($nightCounts)
                ->filter(fn (int $count): bool => $count === $lowestCount)
                ->keys()
                ->sort()
                ->values()
                ->all();
            $swapped = false;

            foreach ($overloadedStaffIds as $overloadedStaffId) {
                $nightEntries = collect($generatedEntries[$overloadedStaffId] ?? [])
                    ->filter(fn (string $shiftTypeId): bool => (bool) $shiftTypeById->get((string) $shiftTypeId)?->crossesMidnight())
                    ->sortKeys();

                foreach ($underloadedStaffIds as $underloadedStaffId) {
                    $dayEntries = collect($generatedEntries[$underloadedStaffId] ?? [])
                        ->filter(fn (string $shiftTypeId): bool => ! (bool) $shiftTypeById->get((string) $shiftTypeId)?->crossesMidnight())
                        ->sortKeys();

                    foreach ($nightEntries as $nightDate => $nightShiftTypeId) {
                        $nightDateModel = $dateMap->get((string) $nightDate);
                        $nightShiftType = $shiftTypeById->get((string) $nightShiftTypeId);

                        if (! $nightDateModel || ! $nightShiftType) {
                            continue;
                        }

                        foreach ($dayEntries as $dayDate => $dayShiftTypeId) {
                            $dayDateModel = $dateMap->get((string) $dayDate);
                            $dayShiftType = $shiftTypeById->get((string) $dayShiftTypeId);

                            if (! $dayDateModel || ! $dayShiftType) {
                                continue;
                            }

                            $trialEntries = $generatedEntries;
                            $trialEntries[$overloadedStaffId][(string) $nightDate] = (string) $dayShiftType->id;
                            $trialEntries[$underloadedStaffId][(string) $dayDate] = (string) $nightShiftType->id;

                            if (
                                ! $this->profileAllowsRebalancedSelection(
                                    $profilesByStaffId->get($overloadedStaffId),
                                    $dayShiftType,
                                    $nightDateModel,
                                    $overloadedStaffId,
                                    $trialEntries,
                                    $shiftTypeById,
                                    $availabilityMatrix
                                )
                                || ! $this->profileAllowsRebalancedSelection(
                                    $profilesByStaffId->get($underloadedStaffId),
                                    $nightShiftType,
                                    $dayDateModel,
                                    $underloadedStaffId,
                                    $trialEntries,
                                    $shiftTypeById,
                                    $availabilityMatrix
                                )
                            ) {
                                continue;
                            }

                            try {
                                $this->rosterPolicyValidator->validate(
                                    $roster,
                                    $this->payloadsFromSelections($roster, $trialEntries, $eligibleById, $shiftTypeById)
                                );
                            } catch (ValidationException) {
                                continue;
                            }

                            $generatedEntries = $trialEntries;
                            $swapped = true;
                            break 4;
                        }
                    }
                }
            }

            if (! $swapped) {
                break;
            }
        }

        return $generatedEntries;
    }

    /**
     * @param array<int|string, array<string, string>> $generatedEntries
     * @param array<int, string> $staffIds
     * @param Collection<string, ShiftType> $shiftTypeById
     * @return array<string, int>
     */
    private function staffNightShiftCounts(array $generatedEntries, array $staffIds, Collection $shiftTypeById): array
    {
        $counts = array_fill_keys($staffIds, 0);

        foreach ($generatedEntries as $staffId => $dateMap) {
            $staffKey = (string) $staffId;

            if (! array_key_exists($staffKey, $counts)) {
                continue;
            }

            foreach ($dateMap as $shiftTypeId) {
                if ($shiftTypeById->get((string) $shiftTypeId)?->crossesMidnight()) {
                    $counts[$staffKey]++;
                }
            }
        }

        return $counts;
    }

    /**
     * @param array<int|string, array<string, string>> $trialEntries
     * @param Collection<string, ShiftType> $shiftTypeById
     * @param array<string, array<string, string>> $availabilityMatrix
     */
    private function profileAllowsRebalancedSelection(
        ?HrStaffRosteringProfile $profile,
        ShiftType $shiftType,
        CarbonImmutable $date,
        string $staffId,
        array $trialEntries,
        Collection $shiftTypeById,
        array $availabilityMatrix
    ): bool {
        if ($this->availabilityStatus($availabilityMatrix, $staffId, $date) === HrStaffUnavailability::STATUS_APPROVED) {
            return false;
        }

        if (! $profile || ! $profile->is_active) {
            return true;
        }

        if ($profile->usesFixedMode() && ! $profile->allowsDate($date)) {
            return false;
        }

        if ($profile->usesFixedMode() && $profile->fixed_shift_type_id && (int) $profile->fixed_shift_type_id !== (int) $shiftType->id) {
            return false;
        }

        if ($profile->excludesShift($shiftType)) {
            return false;
        }

        if ($profile->max_night_shifts_per_cycle !== null) {
            $nightShiftCount = collect($trialEntries[$staffId] ?? [])
                ->filter(fn (string $shiftTypeId): bool => (bool) $shiftTypeById->get((string) $shiftTypeId)?->crossesMidnight())
                ->count();

            if ($nightShiftCount > (int) $profile->max_night_shifts_per_cycle) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int|string, array<string, string>> $avoidEntries
     */
    private function previousDateAssignmentPenalty(array $avoidEntries, string $staffId, string $dateKey): int
    {
        return filled($avoidEntries[$staffId][$dateKey] ?? null) ? 1 : 0;
    }

    /**
     * @param array<int|string, array<string, string>> $avoidEntries
     */
    private function previousShiftAssignmentPenalty(array $avoidEntries, string $staffId, string $dateKey, string $shiftTypeId): int
    {
        return (string) ($avoidEntries[$staffId][$dateKey] ?? '') === $shiftTypeId ? 1 : 0;
    }

    /**
     * @param array{
     *     assignment_counts: array<string, int>,
     *     total_minutes: array<string, int>,
     *     week_minutes: array<string, array<string, int>>,
     *     staff_shift_counts: array<string, array<string, int>>,
     *     global_shift_counts: array<string, int>,
     *     day_shift_counts: array<string, int>,
     *     night_shift_counts: array<string, int>,
     *     weekend_shift_counts: array<string, int>
     * } $state
     * @param array<int, string> $staffIds
     * @return array<int, int>
     */
    private function projectedAssignmentFairnessRank(
        array $state,
        array $staffIds,
        string $staffId,
        string $weekKey,
        CarbonImmutable $date,
        ShiftType $shiftType,
        array $weekendDays
    ): array {
        $shiftTypeId = (string) $shiftType->id;
        $shiftMinutes = $shiftType->effectiveNetMinutes();
        $isNightShift = $shiftType->crossesMidnight();

        return [
            $this->projectedSpread($state['assignment_counts'] ?? [], $staffIds, $staffId, 1),
            $this->projectedNestedSpread($state['week_minutes'] ?? [], $staffIds, $staffId, $weekKey, $shiftMinutes),
            $this->projectedSpread($state['total_minutes'] ?? [], $staffIds, $staffId, $shiftMinutes),
            $isNightShift
                ? $this->projectedSpread($state['night_shift_counts'] ?? [], $staffIds, $staffId, 1)
                : $this->projectedSpread($state['day_shift_counts'] ?? [], $staffIds, $staffId, 1),
            $this->projectedNestedSpread($state['staff_shift_counts'] ?? [], $staffIds, $staffId, $shiftTypeId, 1),
            $this->isWeekendDate($date, $weekendDays)
                ? $this->projectedSpread($state['weekend_shift_counts'] ?? [], $staffIds, $staffId, 1)
                : 0,
        ];
    }

    /**
     * @param array{
     *     assignment_counts: array<string, int>,
     *     total_minutes: array<string, int>,
     *     week_minutes: array<string, array<string, int>>,
     *     day_shift_counts: array<string, int>,
     *     night_shift_counts: array<string, int>,
     *     weekend_shift_counts: array<string, int>
     * } $state
     * @return array<int, int>
     */
    private function staffWorkloadRank(
        array $state,
        string $staffId,
        string $weekKey,
        CarbonImmutable $date,
        array $weekendDays
    ): array {
        return [
            $state['assignment_counts'][$staffId] ?? 0,
            $state['week_minutes'][$staffId][$weekKey] ?? 0,
            $state['total_minutes'][$staffId] ?? 0,
            $this->weekendLoadPenalty($state, $staffId, $date, $weekendDays),
            $state['day_shift_counts'][$staffId] ?? 0,
            $state['night_shift_counts'][$staffId] ?? 0,
        ];
    }

    /**
     * @param array{
     *     assignment_counts: array<string, int>,
     *     total_minutes: array<string, int>,
     *     week_minutes: array<string, array<string, int>>,
     *     staff_shift_counts: array<string, array<string, int>>,
     *     global_shift_counts: array<string, int>,
     *     day_shift_counts: array<string, int>,
     *     night_shift_counts: array<string, int>,
     *     weekend_shift_counts: array<string, int>
     * } $state
     * @param array<int, string> $staffIds
     * @return array<int, int>
     */
    private function shiftChoiceFairnessRank(
        array $state,
        array $staffIds,
        string $staffId,
        string $weekKey,
        CarbonImmutable $date,
        ShiftType $shiftType,
        array $weekendDays
    ): array {
        $shiftTypeId = (string) $shiftType->id;
        $shiftMinutes = $shiftType->effectiveNetMinutes();
        $isNightShift = $shiftType->crossesMidnight();

        return [
            $state['global_shift_counts'][$shiftTypeId] ?? 0,
            $this->projectedNestedSpread($state['staff_shift_counts'] ?? [], $staffIds, $staffId, $shiftTypeId, 1),
            $isNightShift
                ? $this->projectedSpread($state['night_shift_counts'] ?? [], $staffIds, $staffId, 1)
                : $this->projectedSpread($state['day_shift_counts'] ?? [], $staffIds, $staffId, 1),
            $this->projectedSpread($state['assignment_counts'] ?? [], $staffIds, $staffId, 1),
            $this->projectedNestedSpread($state['week_minutes'] ?? [], $staffIds, $staffId, $weekKey, $shiftMinutes),
            $this->projectedSpread($state['total_minutes'] ?? [], $staffIds, $staffId, $shiftMinutes),
            $this->isWeekendDate($date, $weekendDays)
                ? $this->projectedSpread($state['weekend_shift_counts'] ?? [], $staffIds, $staffId, 1)
                : 0,
        ];
    }

    /**
     * @param array<string, int> $values
     * @param array<int, string> $keys
     */
    private function projectedSpread(array $values, array $keys, string $candidateKey, int $increment): int
    {
        if ($keys === []) {
            return 0;
        }

        $projected = array_map(
            fn (string $key): int => (int) ($values[$key] ?? 0) + ($key === $candidateKey ? $increment : 0),
            $keys
        );

        return max($projected) - min($projected);
    }

    /**
     * @param array<string, array<string, int>> $values
     * @param array<int, string> $keys
     */
    private function projectedNestedSpread(array $values, array $keys, string $candidateKey, string $nestedKey, int $increment): int
    {
        if ($keys === []) {
            return 0;
        }

        $projected = array_map(
            fn (string $key): int => (int) ($values[$key][$nestedKey] ?? 0) + ($key === $candidateKey ? $increment : 0),
            $keys
        );

        return max($projected) - min($projected);
    }

    /**
     * @param array<string, string> $teamAssignments
     */
    private function teamCohesionPenalty(array $state, string $staffId, string $dateKey, array $teamAssignments): int
    {
        $teamName = $teamAssignments[$staffId] ?? null;

        if (! $teamName) {
            return 0;
        }

        $dateTeamCounts = $state['date_team_counts'][$dateKey] ?? [];

        if ($dateTeamCounts === []) {
            return 0;
        }

        return array_key_exists($teamName, $dateTeamCounts) ? 0 : 1;
    }

    /**
     * @param array<int, string> $teamNames
     * @param array<string, string> $teamAssignments
     * @return array<int, int>
     */
    private function teamFairnessRank(array $state, array $teamNames, string $staffId, array $teamAssignments): array
    {
        $teamName = $teamAssignments[$staffId] ?? null;

        if (! $teamName || $teamNames === []) {
            return [0, 0];
        }

        return [
            $this->projectedSpread($state['team_assignment_counts'] ?? [], $teamNames, $teamName, 1),
            (int) ($state['team_assignment_counts'][$teamName] ?? 0),
        ];
    }

    private function variantRank(string $variationSeed, string ...$parts): int
    {
        if ($variationSeed === '') {
            return 0;
        }

        return (int) sprintf('%u', crc32($variationSeed.'|'.implode('|', $parts)));
    }

    /**
     * Give each staff member a deterministic preferred offset inside the week so
     * equally valid workloads do not collapse into the same work/off-day pattern.
     * Blend the reroll seed in here so the earliest dates can also move between drafts.
     *
     * @param array<int, string> $dateKeys
     */
    private function staffWeekStaggerRank(array $dateKeys, string $staffId, string $dateKey, string $variationSeed = ''): int
    {
        $weekLength = count($dateKeys);

        if ($weekLength <= 1) {
            return 0;
        }

        $dateIndex = array_search($dateKey, $dateKeys, true);

        if (! is_int($dateIndex)) {
            return $weekLength;
        }

        $preferredOffset = (int) sprintf('%u', crc32($variationSeed.'|'.$staffId.'|'.$dateKeys[0])) % $weekLength;

        return ($dateIndex - $preferredOffset + $weekLength) % $weekLength;
    }

    /**
     * @param Collection<int, StaffAssignment> $eligibleAssignments
     * @param array<int, CarbonImmutable> $dates
     * @return array<string, array<string, string>>
     */
    private function availabilityMatrix(HrDutyRoster $roster, Collection $eligibleAssignments, array $dates): array
    {
        if ($eligibleAssignments->isEmpty() || $dates === []) {
            return [];
        }

        $startDate = $dates[0]->toDateString();
        $endDate = $dates[array_key_last($dates)]->toDateString();
        $matrix = [];

        $unavailabilities = HrStaffUnavailability::query()
            ->where('organization_id', $roster->organization_id)
            ->whereIn('staff_assignment_id', $eligibleAssignments->pluck('id'))
            ->whereIn('status', [
                HrStaffUnavailability::STATUS_PENDING,
                HrStaffUnavailability::STATUS_APPROVED,
            ])
            ->where('blocks_rosters', true)
            ->whereDate('starts_on', '<=', $endDate)
            ->where(function ($query) use ($startDate): void {
                $query
                    ->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $startDate);
            })
            ->get();

        foreach ($unavailabilities as $unavailability) {
            $staffId = (string) $unavailability->staff_assignment_id;
            $status = (string) $unavailability->status;
            $cursor = CarbonImmutable::parse($unavailability->starts_on)->startOfDay();
            $end = CarbonImmutable::parse($unavailability->ends_on ?: $unavailability->starts_on)->startOfDay();

            for (; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addDay()) {
                $dateKey = $cursor->toDateString();
                $current = $matrix[$staffId][$dateKey] ?? null;

                if ($current === HrStaffUnavailability::STATUS_APPROVED) {
                    continue;
                }

                if ($status === HrStaffUnavailability::STATUS_APPROVED || $current === null) {
                    $matrix[$staffId][$dateKey] = $status;
                }
            }
        }

        return $matrix;
    }

    /**
     * @param array<int, array<string, mixed>> $payloads
     * @param Collection<string, ShiftType> $shiftTypeById
     * @return array{
     *     assignment_counts: array<string, int>,
     *     total_minutes: array<string, int>,
     *     week_minutes: array<string, array<string, int>>,
     *     staff_shift_counts: array<string, array<string, int>>,
     *     global_shift_counts: array<string, int>,
     *     last_assigned_on: array<string, CarbonImmutable>,
     *     day_shift_counts: array<string, int>,
     *     night_shift_counts: array<string, int>,
     *     weekend_shift_counts: array<string, int>,
     *     team_assignment_counts: array<string, int>,
     *     date_team_counts: array<string, array<string, int>>,
     *     date_day_shift_counts: array<string, int>,
     *     date_night_shift_counts: array<string, int>,
     *     last_shift_was_night: array<string, bool>,
     *     consecutive_night_streaks: array<string, int>,
     *     last_shift_type_ids: array<string, string>,
     *     consecutive_shift_type_streaks: array<string, int>
     * }
     */
    private function buildState(array $payloads, Collection $shiftTypeById, array $weekendDays, array $teamAssignments = []): array
    {
        $state = [
            'assignment_counts' => [],
            'total_minutes' => [],
            'week_minutes' => [],
            'staff_shift_counts' => [],
            'global_shift_counts' => [],
            'date_counts' => [],
            'date_shift_counts' => [],
            'last_assigned_on' => [],
            'day_shift_counts' => [],
            'night_shift_counts' => [],
            'weekend_shift_counts' => [],
            'team_assignment_counts' => [],
            'date_team_counts' => [],
            'date_day_shift_counts' => [],
            'date_night_shift_counts' => [],
            'last_shift_was_night' => [],
            'consecutive_night_streaks' => [],
            'last_shift_type_ids' => [],
            'consecutive_shift_type_streaks' => [],
        ];

        foreach (
            collect($payloads)
                ->sortBy(fn (array $payload): string => sprintf('%s|%s|%s', $payload['roster_date'] ?? '', $payload['staff_assignment_id'] ?? '', $payload['shift_type_id'] ?? ''))
                ->values() as $payload
        ) {
            $staffId = (string) $payload['staff_assignment_id'];
            $shiftId = (string) $payload['shift_type_id'];
            $date = CarbonImmutable::parse($payload['roster_date'])->startOfDay();
            $weekKey = $date->startOfWeek(CarbonInterface::MONDAY)->toDateString();
            $shiftType = $shiftTypeById->get($shiftId);
            $shiftMinutes = (int) ($shiftType?->effectiveNetMinutes() ?? 0);
            $isNightShift = $shiftType?->crossesMidnight() ?? false;
            $teamName = $teamAssignments[$staffId] ?? null;

            $state['assignment_counts'][$staffId] = ($state['assignment_counts'][$staffId] ?? 0) + 1;
            $state['total_minutes'][$staffId] = ($state['total_minutes'][$staffId] ?? 0) + $shiftMinutes;
            $state['week_minutes'][$staffId][$weekKey] = ($state['week_minutes'][$staffId][$weekKey] ?? 0) + $shiftMinutes;
            $state['staff_shift_counts'][$staffId][$shiftId] = ($state['staff_shift_counts'][$staffId][$shiftId] ?? 0) + 1;
            $state['global_shift_counts'][$shiftId] = ($state['global_shift_counts'][$shiftId] ?? 0) + 1;
            $state['date_counts'][$date->toDateString()] = ($state['date_counts'][$date->toDateString()] ?? 0) + 1;
            $state['date_shift_counts'][$date->toDateString()][$shiftId] = ($state['date_shift_counts'][$date->toDateString()][$shiftId] ?? 0) + 1;
            $state['day_shift_counts'][$staffId] = ($state['day_shift_counts'][$staffId] ?? 0)
                + ($isNightShift ? 0 : 1);
            $state['night_shift_counts'][$staffId] = ($state['night_shift_counts'][$staffId] ?? 0)
                + ($isNightShift ? 1 : 0);
            $state['date_day_shift_counts'][$date->toDateString()] = ($state['date_day_shift_counts'][$date->toDateString()] ?? 0)
                + ($isNightShift ? 0 : 1);
            $state['date_night_shift_counts'][$date->toDateString()] = ($state['date_night_shift_counts'][$date->toDateString()] ?? 0)
                + ($isNightShift ? 1 : 0);
            $state['weekend_shift_counts'][$staffId] = ($state['weekend_shift_counts'][$staffId] ?? 0)
                + ($this->isWeekendDate($date, $weekendDays) ? 1 : 0);

            if ($teamName) {
                $state['team_assignment_counts'][$teamName] = ($state['team_assignment_counts'][$teamName] ?? 0) + 1;
                $state['date_team_counts'][$date->toDateString()][$teamName] = ($state['date_team_counts'][$date->toDateString()][$teamName] ?? 0) + 1;
            }

            $previousAssignedDate = $state['last_assigned_on'][$staffId] ?? null;
            $previousShiftWasNight = (bool) ($state['last_shift_was_night'][$staffId] ?? false);
            $previousShiftTypeId = (string) ($state['last_shift_type_ids'][$staffId] ?? '');

            if (
                $isNightShift
                && $previousShiftWasNight
                && $previousAssignedDate instanceof CarbonImmutable
                && $previousAssignedDate->diffInDays($date) === 1
            ) {
                $state['consecutive_night_streaks'][$staffId] = ($state['consecutive_night_streaks'][$staffId] ?? 1) + 1;
            } elseif ($isNightShift) {
                $state['consecutive_night_streaks'][$staffId] = 1;
            } else {
                $state['consecutive_night_streaks'][$staffId] = 0;
            }

            if (
                $previousAssignedDate instanceof CarbonImmutable
                && $previousAssignedDate->diffInDays($date) === 1
                && $previousShiftTypeId === $shiftId
            ) {
                $state['consecutive_shift_type_streaks'][$staffId] = ($state['consecutive_shift_type_streaks'][$staffId] ?? 1) + 1;
            } else {
                $state['consecutive_shift_type_streaks'][$staffId] = 1;
            }

            if (
                ! isset($state['last_assigned_on'][$staffId])
                || $date->greaterThan($state['last_assigned_on'][$staffId])
            ) {
                $state['last_assigned_on'][$staffId] = $date;
                $state['last_shift_was_night'][$staffId] = $isNightShift;
                $state['last_shift_type_ids'][$staffId] = $shiftId;
            }
        }

        return $state;
    }

    /**
     * @param array{
     *     assignment_counts: array<string, int>,
     *     total_minutes: array<string, int>,
     *     week_minutes: array<string, array<string, int>>,
     *     staff_shift_counts: array<string, array<string, int>>,
     *     global_shift_counts: array<string, int>,
     *     date_counts: array<string, int>,
     *     date_shift_counts: array<string, array<string, int>>,
     *     last_assigned_on: array<string, CarbonImmutable>,
     *     day_shift_counts: array<string, int>,
     *     night_shift_counts: array<string, int>,
     *     weekend_shift_counts: array<string, int>,
     *     team_assignment_counts: array<string, int>,
     *     date_team_counts: array<string, array<string, int>>,
     *     date_day_shift_counts: array<string, int>,
     *     date_night_shift_counts: array<string, int>,
     *     last_shift_was_night: array<string, bool>,
     *     consecutive_night_streaks: array<string, int>,
     *     last_shift_type_ids: array<string, string>,
     *     consecutive_shift_type_streaks: array<string, int>
     * } $state
     * @return array{
     *     assignment_counts: array<string, int>,
     *     total_minutes: array<string, int>,
     *     week_minutes: array<string, array<string, int>>,
     *     staff_shift_counts: array<string, array<string, int>>,
     *     global_shift_counts: array<string, int>,
     *     date_counts: array<string, int>,
     *     date_shift_counts: array<string, array<string, int>>,
     *     last_assigned_on: array<string, CarbonImmutable>,
     *     day_shift_counts: array<string, int>,
     *     night_shift_counts: array<string, int>,
     *     weekend_shift_counts: array<string, int>,
     *     team_assignment_counts: array<string, int>,
     *     date_team_counts: array<string, array<string, int>>,
     *     date_day_shift_counts: array<string, int>,
     *     date_night_shift_counts: array<string, int>,
     *     last_shift_was_night: array<string, bool>,
     *     consecutive_night_streaks: array<string, int>,
     *     last_shift_type_ids: array<string, string>,
     *     consecutive_shift_type_streaks: array<string, int>
     * }
     */
    private function applyAssignmentState(
        array $state,
        string $staffId,
        string $weekKey,
        CarbonImmutable $date,
        ShiftType $shiftType,
        array $weekendDays,
        array $teamAssignments = []
    ): array {
        $shiftId = (string) $shiftType->id;
        $shiftMinutes = $shiftType->effectiveNetMinutes();
        $isNightShift = $shiftType->crossesMidnight();
        $teamName = $teamAssignments[$staffId] ?? null;
        $previousAssignedDate = $state['last_assigned_on'][$staffId] ?? null;
        $previousShiftWasNight = (bool) ($state['last_shift_was_night'][$staffId] ?? false);
        $previousShiftTypeId = (string) ($state['last_shift_type_ids'][$staffId] ?? '');

        $state['assignment_counts'][$staffId] = ($state['assignment_counts'][$staffId] ?? 0) + 1;
        $state['total_minutes'][$staffId] = ($state['total_minutes'][$staffId] ?? 0) + $shiftMinutes;
        $state['week_minutes'][$staffId][$weekKey] = ($state['week_minutes'][$staffId][$weekKey] ?? 0) + $shiftMinutes;
        $state['staff_shift_counts'][$staffId][$shiftId] = ($state['staff_shift_counts'][$staffId][$shiftId] ?? 0) + 1;
        $state['global_shift_counts'][$shiftId] = ($state['global_shift_counts'][$shiftId] ?? 0) + 1;
        $state['date_counts'][$date->toDateString()] = ($state['date_counts'][$date->toDateString()] ?? 0) + 1;
        $state['date_shift_counts'][$date->toDateString()][$shiftId] = ($state['date_shift_counts'][$date->toDateString()][$shiftId] ?? 0) + 1;
        $state['day_shift_counts'][$staffId] = ($state['day_shift_counts'][$staffId] ?? 0) + ($isNightShift ? 0 : 1);
        $state['night_shift_counts'][$staffId] = ($state['night_shift_counts'][$staffId] ?? 0) + ($isNightShift ? 1 : 0);
        $state['date_day_shift_counts'][$date->toDateString()] = ($state['date_day_shift_counts'][$date->toDateString()] ?? 0)
            + ($isNightShift ? 0 : 1);
        $state['date_night_shift_counts'][$date->toDateString()] = ($state['date_night_shift_counts'][$date->toDateString()] ?? 0)
            + ($isNightShift ? 1 : 0);
        $state['weekend_shift_counts'][$staffId] = ($state['weekend_shift_counts'][$staffId] ?? 0)
            + ($this->isWeekendDate($date, $weekendDays) ? 1 : 0);

        if ($teamName) {
            $state['team_assignment_counts'][$teamName] = ($state['team_assignment_counts'][$teamName] ?? 0) + 1;
            $state['date_team_counts'][$date->toDateString()][$teamName] = ($state['date_team_counts'][$date->toDateString()][$teamName] ?? 0) + 1;
        }

        $state['last_assigned_on'][$staffId] = $date;
        $state['last_shift_was_night'][$staffId] = $isNightShift;
        $state['last_shift_type_ids'][$staffId] = $shiftId;

        if (
            $isNightShift
            && $previousShiftWasNight
            && $previousAssignedDate instanceof CarbonImmutable
            && $previousAssignedDate->diffInDays($date) === 1
        ) {
            $state['consecutive_night_streaks'][$staffId] = ($state['consecutive_night_streaks'][$staffId] ?? 1) + 1;
        } elseif ($isNightShift) {
            $state['consecutive_night_streaks'][$staffId] = 1;
        } else {
            $state['consecutive_night_streaks'][$staffId] = 0;
        }

        if (
            $previousAssignedDate instanceof CarbonImmutable
            && $previousAssignedDate->diffInDays($date) === 1
            && $previousShiftTypeId === $shiftId
        ) {
            $state['consecutive_shift_type_streaks'][$staffId] = ($state['consecutive_shift_type_streaks'][$staffId] ?? 1) + 1;
        } else {
            $state['consecutive_shift_type_streaks'][$staffId] = 1;
        }

        return $state;
    }

    /**
     * @param array{
     *     total_minutes: array<string, int>,
     *     week_minutes: array<string, array<string, int>>,
     *     staff_shift_counts: array<string, array<string, int>>,
     *     global_shift_counts: array<string, int>,
     *     last_assigned_on: array<string, CarbonImmutable>,
     *     night_shift_counts: array<string, int>,
     *     weekend_shift_counts: array<string, int>,
     *     last_shift_was_night: array<string, bool>,
     *     consecutive_night_streaks: array<string, int>
     * } $state
     * @param array<string, array<string, string>> $availabilityMatrix
     */
    private function canAutoAssignShift(
        ?HrStaffRosteringProfile $profile,
        ShiftType $shiftType,
        CarbonImmutable $date,
        string $staffId,
        array $state,
        array $availabilityMatrix
    ): bool {
        if ($this->availabilityStatus($availabilityMatrix, $staffId, $date) === HrStaffUnavailability::STATUS_APPROVED) {
            return false;
        }

        if (! $profile || ! $profile->is_active) {
            return true;
        }

        if ($profile->usesFixedMode() && ! $profile->allowsDate($date)) {
            return false;
        }

        if ($profile->usesFixedMode() && $profile->fixed_shift_type_id && (int) $profile->fixed_shift_type_id !== (int) $shiftType->id) {
            return false;
        }

        if ($profile->excludesShift($shiftType)) {
            return false;
        }

        if (
            $profile->max_night_shifts_per_cycle !== null
            && $shiftType->crossesMidnight()
            && (($state['night_shift_counts'][$staffId] ?? 0) >= (int) $profile->max_night_shifts_per_cycle)
        ) {
            return false;
        }

        return true;
    }

    private function shiftPreferenceRank(?HrStaffRosteringProfile $profile, ShiftType $shiftType): int
    {
        if (! $profile || ! $profile->is_active) {
            return 2;
        }

        if ($profile->fixed_shift_type_id && (int) $profile->fixed_shift_type_id === (int) $shiftType->id) {
            return 0;
        }

        if ($profile->prefersShift($shiftType)) {
            return 1;
        }

        return 2;
    }

    private function assignmentDatePriority(?HrStaffRosteringProfile $profile, CarbonImmutable $date): int
    {
        if ($profile && $profile->usesFixedMode() && $profile->allowsDate($date)) {
            return 0;
        }

        return 1;
    }

    /**
     * @param array{
     *     last_assigned_on: array<string, CarbonImmutable>,
     *     last_shift_type_ids: array<string, string>,
     *     consecutive_shift_type_streaks: array<string, int>
     * } $state
     */
    private function repeatedShiftTypePenalty(array $state, string $staffId, ShiftType $shiftType, CarbonImmutable $date): int
    {
        $lastAssignedOn = $state['last_assigned_on'][$staffId] ?? null;

        if (
            ! ($lastAssignedOn instanceof CarbonImmutable)
            || $lastAssignedOn->diffInDays($date) !== 1
            || (string) ($state['last_shift_type_ids'][$staffId] ?? '') !== (string) $shiftType->id
        ) {
            return 0;
        }

        return (int) ($state['consecutive_shift_type_streaks'][$staffId] ?? 1);
    }

    /**
     * @param array{
     *     date_day_shift_counts: array<string, int>,
     *     date_night_shift_counts: array<string, int>
     * } $state
     */
    private function dateShiftFamilyConcentrationPenalty(
        array $state,
        string $dateKey,
        ShiftType $shiftType,
        bool $hasDayShifts,
        bool $hasNightShifts
    ): int {
        if (! $hasDayShifts || ! $hasNightShifts) {
            return 0;
        }

        $dayCount = (int) ($state['date_day_shift_counts'][$dateKey] ?? 0);
        $nightCount = (int) ($state['date_night_shift_counts'][$dateKey] ?? 0);

        if ($dayCount === 0 && $nightCount === 0) {
            return 0;
        }

        if ($shiftType->crossesMidnight()) {
            return $nightCount > 0 && $dayCount === 0 ? $nightCount : 0;
        }

        return $dayCount > 0 && $nightCount === 0 ? $dayCount : 0;
    }

    /**
     * @param array{
     *     total_minutes: array<string, int>,
     *     week_minutes: array<string, array<string, int>>,
     *     staff_shift_counts: array<string, array<string, int>>,
     *     global_shift_counts: array<string, int>,
     *     last_assigned_on: array<string, CarbonImmutable>,
     *     night_shift_counts: array<string, int>,
     *     weekend_shift_counts: array<string, int>,
     *     last_shift_was_night: array<string, bool>,
     *     consecutive_night_streaks: array<string, int>
     * } $state
     */
    private function postNightRecoveryPenalty(array $state, string $staffId, CarbonImmutable $date): int
    {
        $lastAssignedOn = $state['last_assigned_on'][$staffId] ?? null;

        if (
            ! ($lastAssignedOn instanceof CarbonImmutable)
            || ! ($state['last_shift_was_night'][$staffId] ?? false)
            || $lastAssignedOn->diffInDays($date) !== 1
        ) {
            return 0;
        }

        return 1;
    }

    /**
     * @param array{
     *     total_minutes: array<string, int>,
     *     week_minutes: array<string, array<string, int>>,
     *     staff_shift_counts: array<string, array<string, int>>,
     *     global_shift_counts: array<string, int>,
     *     last_assigned_on: array<string, CarbonImmutable>,
     *     night_shift_counts: array<string, int>,
     *     weekend_shift_counts: array<string, int>,
     *     last_shift_was_night: array<string, bool>,
     *     consecutive_night_streaks: array<string, int>
     * } $state
     * @param array<int, int> $weekendDays
     */
    private function weekendLoadPenalty(array $state, string $staffId, CarbonImmutable $date, array $weekendDays): int
    {
        if (! $this->isWeekendDate($date, $weekendDays)) {
            return 0;
        }

        return (int) ($state['weekend_shift_counts'][$staffId] ?? 0);
    }

    /**
     * @param array{
     *     total_minutes: array<string, int>,
     *     week_minutes: array<string, array<string, int>>,
     *     staff_shift_counts: array<string, array<string, int>>,
     *     global_shift_counts: array<string, int>,
     *     last_assigned_on: array<string, CarbonImmutable>,
     *     night_shift_counts: array<string, int>,
     *     weekend_shift_counts: array<string, int>,
     *     last_shift_was_night: array<string, bool>,
     *     consecutive_night_streaks: array<string, int>
     * } $state
     */
    private function nightRecoveryShiftPenalty(array $state, string $staffId, ShiftType $shiftType, CarbonImmutable $date): int
    {
        if (! $shiftType->crossesMidnight()) {
            return 0;
        }

        return $this->postNightRecoveryPenalty($state, $staffId, $date);
    }

    /**
     * @param array{
     *     total_minutes: array<string, int>,
     *     week_minutes: array<string, array<string, int>>,
     *     staff_shift_counts: array<string, array<string, int>>,
     *     global_shift_counts: array<string, int>,
     *     last_assigned_on: array<string, CarbonImmutable>,
     *     night_shift_counts: array<string, int>,
     *     weekend_shift_counts: array<string, int>,
     *     last_shift_was_night: array<string, bool>,
     *     consecutive_night_streaks: array<string, int>
     * } $state
     */
    private function consecutiveNightPenalty(array $state, string $staffId, ShiftType $shiftType, CarbonImmutable $date): int
    {
        if (! $shiftType->crossesMidnight()) {
            return 0;
        }

        $lastAssignedOn = $state['last_assigned_on'][$staffId] ?? null;

        if (
            ! ($lastAssignedOn instanceof CarbonImmutable)
            || ! ($state['last_shift_was_night'][$staffId] ?? false)
            || $lastAssignedOn->diffInDays($date) !== 1
        ) {
            return 0;
        }

        return (int) ($state['consecutive_night_streaks'][$staffId] ?? 1);
    }

    /**
     * @param array<string, array<string, string>> $availabilityMatrix
     */
    private function availabilityStatus(array $availabilityMatrix, string $staffId, CarbonImmutable $date): ?string
    {
        return $availabilityMatrix[$staffId][$date->toDateString()] ?? null;
    }

    /**
     * @param array<string, array<string, string>> $availabilityMatrix
     */
    private function pendingLeavePenalty(array $availabilityMatrix, string $staffId, CarbonImmutable $date): int
    {
        return $this->availabilityStatus($availabilityMatrix, $staffId, $date) === HrStaffUnavailability::STATUS_PENDING
            ? 1
            : 0;
    }

    /**
     * @return array<string, int>
     */
    private function weeklyTargets(array $dates, HrPolicyVersion $policy): array
    {
        return collect($dates)
            ->groupBy(fn (CarbonImmutable $date): string => $date->startOfWeek(CarbonInterface::MONDAY)->toDateString())
            ->map(fn (Collection $weekDates): int => (int) round(((int) $policy->weekly_standard_minutes * $weekDates->count()) / 7))
            ->all();
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function dateRange(HrDutyRoster $roster): array
    {
        if (! $roster->organization) {
            return [];
        }

        return $this->workingDayCalculator->dates(
            $roster->organization,
            CarbonImmutable::parse($roster->start_date)->startOfDay(),
            CarbonImmutable::parse($roster->end_date)->startOfDay()
        );
    }

    private function payloadForSelection(
        HrDutyRoster $roster,
        StaffAssignment $assignment,
        ShiftType $shiftType,
        CarbonImmutable $date
    ): array {
        return [
            'organization_id' => $roster->organization_id,
            'roster_date' => $date->toDateString(),
            'staff_assignment_id' => $assignment->id,
            'staff_uuid' => $assignment->staff_uuid,
            'staff_name' => $assignment->staff_name,
            'staff_cadre' => $assignment->staff_cadre,
            'shift_type_id' => $shiftType->id,
            'notes' => null,
        ];
    }

    private function shouldAssignShift(int $currentMinutes, int $targetMinutes, int $shiftMinutes): bool
    {
        if ($targetMinutes <= 0 || $shiftMinutes <= 0 || $currentMinutes >= $targetMinutes) {
            return false;
        }

        if (($currentMinutes + $shiftMinutes) <= $targetMinutes) {
            return true;
        }

        $remainingMinutes = $targetMinutes - $currentMinutes;

        return abs($remainingMinutes - $shiftMinutes) < $remainingMinutes;
    }

    private function targetDistance(int $shiftMinutes, int $remainingMinutes): int
    {
        return abs($remainingMinutes - $shiftMinutes);
    }

    private function targetOvershootPenalty(int $currentMinutes, int $targetMinutes, int $shiftMinutes): int
    {
        return max(0, ($currentMinutes + $shiftMinutes) - $targetMinutes);
    }

    private function lastAssignedDistance(?CarbonImmutable $lastAssignedOn, CarbonImmutable $date): int
    {
        if (! $lastAssignedOn) {
            return PHP_INT_MAX;
        }

        if ($lastAssignedOn->greaterThan($date)) {
            return 0;
        }

        return $lastAssignedOn->diffInDays($date) * -1;
    }

    /**
     * @param Collection<int, ShiftType> $shiftTypes
     * @return Collection<int, ShiftType>
     */
    private function preferredFallbackShiftTypes(Collection $shiftTypes, ?HrStaffRosteringProfile $profile): Collection
    {
        return $shiftTypes
            ->sort(function (ShiftType $left, ShiftType $right) use ($profile): int {
                return [
                    $this->shiftPreferenceRank($profile, $left),
                    $left->isRegularWorkingHoursDefault() ? 0 : 1,
                    $left->crossesMidnight() ? 1 : 0,
                    $left->start_time,
                    $left->id,
                ] <=> [
                    $this->shiftPreferenceRank($profile, $right),
                    $right->isRegularWorkingHoursDefault() ? 0 : 1,
                    $right->crossesMidnight() ? 1 : 0,
                    $right->start_time,
                    $right->id,
                ];
            })
            ->values();
    }

    /**
     * @param array<int|string, array<string, string>> $generatedEntries
     */
    private function generatedSelectionCount(array $generatedEntries): int
    {
        return collect($generatedEntries)
            ->sum(fn ($dateSelections): int => is_array($dateSelections) ? count($dateSelections) : 0);
    }

    /**
     * @return array<int, int>
     */
    private function weekendDays(HrDutyRoster $roster): array
    {
        $configured = collect($roster->organization?->weekend_days ?? [0, 6])
            ->map(fn ($day): int => (int) $day)
            ->filter(fn (int $day): bool => $day >= 0 && $day <= 6)
            ->unique()
            ->values()
            ->all();

        return $configured === [] ? [0, 6] : $configured;
    }

    /**
     * @param array<int, int> $weekendDays
     */
    private function isWeekendDate(CarbonImmutable $date, array $weekendDays): bool
    {
        return in_array((int) $date->dayOfWeek, $weekendDays, true);
    }
}
