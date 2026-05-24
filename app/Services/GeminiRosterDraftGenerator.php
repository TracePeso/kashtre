<?php

namespace App\Services;

use App\Models\HrDutyRoster;
use App\Models\ShiftType;
use App\Models\StaffAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class GeminiRosterDraftGenerator
{
    public function __construct(
        private readonly GeminiRosterClient $geminiRosterClient,
        private readonly RosterDraftGenerator $fallbackGenerator,
        private readonly RosterPolicyResolver $policyResolver,
        private readonly RosterPolicyValidator $rosterPolicyValidator,
        private readonly WorkingDayCalculator $workingDayCalculator
    ) {
    }

    /**
     * @param Collection<int, StaffAssignment> $eligibleAssignments
     * @param Collection<int, ShiftType> $shiftTypes
     */
    public function assertPrerequisites(HrDutyRoster $roster, Collection $eligibleAssignments, Collection $shiftTypes): void
    {
        $roster->loadMissing('organization', 'organizationalUnit');
        $eligibleById = $eligibleAssignments->keyBy(fn (StaffAssignment $assignment): string => (string) $assignment->id);
        $usableShiftTypes = $shiftTypes
            ->filter(fn (ShiftType $shiftType): bool => $shiftType->is_active && $shiftType->is_rosterable)
            ->filter(fn (ShiftType $shiftType): bool => $shiftType->effectiveNetMinutes() > 0)
            ->values();

        if ($eligibleById->isEmpty()) {
            throw ValidationException::withMessages([
                'roster' => 'No active staff remain in this title for AI roster generation.',
            ]);
        }

        if ($usableShiftTypes->isEmpty()) {
            throw ValidationException::withMessages([
                'roster' => 'Add at least one active rosterable shift type before using AI roster generation.',
            ]);
        }

        if ($this->dateRange($roster) === []) {
            throw ValidationException::withMessages([
                'roster' => 'The selected roster period has no workdays in the HR calendar.',
            ]);
        }
    }

    /**
     * @param Collection<int, StaffAssignment> $eligibleAssignments
     * @param Collection<int, ShiftType> $shiftTypes
     * @param array<int|string, array<string, mixed>> $entries
     * @param array{
     *   replace_existing_entries?: bool,
     *   variation_seed?: string,
     *   fallback_on_gemini_failure?: bool,
     *   progress_callback?: callable(array{chunk_index:int,total_chunks:int,chunk_start:string,chunk_end:string}): void
     * } $options
     * @return array<int|string, array<string, string>>
     */
    public function generate(
        HrDutyRoster $roster,
        Collection $eligibleAssignments,
        Collection $shiftTypes,
        array $entries = [],
        array $options = []
    ): array {
        $roster->loadMissing('organization', 'organizationalUnit');

        $policy = $this->policyResolver->requireActiveVersion(
            $roster->organization,
            CarbonImmutable::parse($roster->start_date)
        );

        $eligibleById = $eligibleAssignments->keyBy(fn (StaffAssignment $assignment): string => (string) $assignment->id);
        $shiftTypes = $shiftTypes
            ->filter(fn (ShiftType $shiftType): bool => $shiftType->is_active && $shiftType->is_rosterable)
            ->filter(fn (ShiftType $shiftType): bool => $shiftType->effectiveNetMinutes() > 0)
            ->sortBy([
                ['start_time', 'asc'],
                ['name', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
        $this->assertPrerequisites($roster, $eligibleAssignments, $shiftTypes);
        $dates = $this->dateRange($roster);

        $validDates = collect($dates)->mapWithKeys(fn (CarbonImmutable $date): array => [$date->toDateString() => true]);
        $shiftTypeById = $shiftTypes->keyBy(fn (ShiftType $shiftType): string => (string) $shiftType->id);
        $previousSelections = $this->sanitizeSelections($entries, $eligibleById, $shiftTypeById, $validDates);
        $replaceExistingEntries = (bool) ($options['replace_existing_entries'] ?? false);
        $fallbackOnGeminiFailure = (bool) ($options['fallback_on_gemini_failure'] ?? true);
        $variationSeed = filled($options['variation_seed'] ?? null)
            ? (string) $options['variation_seed']
            : now()->format('YmdHisv');
        $progressCallback = is_callable($options['progress_callback'] ?? null)
            ? $options['progress_callback']
            : null;
        $selections = $replaceExistingEntries ? [] : $previousSelections;
        $avoidSelections = $replaceExistingEntries ? $previousSelections : [];
        $weekendDays = $this->weekendDays($roster);
        $payloads = $this->payloadsFromSelections($roster, $selections, $eligibleById, $shiftTypeById);
        $acceptedAiAssignments = 0;
        $dateChunks = $this->dateChunks($dates);
        $totalChunks = count($dateChunks);

        foreach ($dateChunks as $chunkIndex => $dateChunk) {
            $chunkPosition = $chunkIndex + 1;
            $chunkStart = $dateChunk[0]->toDateString();
            $chunkEnd = $dateChunk[array_key_last($dateChunk)]->toDateString();

            if ($progressCallback) {
                $progressCallback([
                    'chunk_index' => $chunkPosition,
                    'total_chunks' => $totalChunks,
                    'chunk_start' => $chunkStart,
                    'chunk_end' => $chunkEnd,
                ]);
            }

            $chunkValidDates = collect($dateChunk)->mapWithKeys(fn (CarbonImmutable $date): array => [$date->toDateString() => true]);
            $chunkSelections = $this->restrictSelectionsToDates($selections, $chunkValidDates);
            $chunkAvoidSelections = $this->restrictSelectionsToDates($avoidSelections, $chunkValidDates);
            $prompt = $this->buildPrompt(
                $roster,
                $policy,
                $eligibleAssignments,
                $shiftTypes,
                $dateChunk,
                $chunkSelections,
                $chunkAvoidSelections,
                $shiftTypeById,
                $weekendDays,
                $replaceExistingEntries,
                $variationSeed.':chunk-'.($chunkIndex + 1),
                $selections,
                $avoidSelections
            );
            $aiAssignments = [];

            try {
                $aiAssignments = $this->geminiRosterClient->generateAssignments($prompt);
            } catch (ValidationException $exception) {
                if (! $fallbackOnGeminiFailure) {
                    throw $exception;
                }

                Log::warning('Gemini AI roster generation failed; using automatic fallback.', [
                    'roster_id' => $roster->id,
                    'organization_id' => $roster->organization_id,
                    'client_space_id' => $roster->organizational_unit_id,
                    'message' => $exception->getMessage(),
                ]);

                break;
            }

            foreach ($aiAssignments as $assignment) {
                $staffId = (string) ($assignment['staff_assignment_id'] ?? '');
                $dateKey = (string) ($assignment['date'] ?? '');
                $shiftId = (string) ($assignment['shift_type_id'] ?? '');

                if (! $eligibleById->has($staffId) || ! $chunkValidDates->has($dateKey) || ! $shiftTypeById->has($shiftId)) {
                    continue;
                }

                if (filled($selections[$staffId][$dateKey] ?? null)) {
                    continue;
                }

                if ($replaceExistingEntries && $this->matchesExistingSelection($avoidSelections, $staffId, $dateKey, $shiftId)) {
                    continue;
                }

                $candidatePayload = $this->payloadForSelection(
                    $roster,
                    $eligibleById->get($staffId),
                    $shiftTypeById->get($shiftId),
                    CarbonImmutable::parse($dateKey)->startOfDay()
                );
                $trialPayloads = [...$payloads, $candidatePayload];

                try {
                    $this->rosterPolicyValidator->validate($roster, $trialPayloads, [
                        'enforce_anchor' => true,
                    ]);
                } catch (ValidationException) {
                    continue;
                }

                $selections[$staffId][$dateKey] = $shiftId;
                $acceptedAiAssignments++;
                $payloads[] = $candidatePayload;
            }
        }

        if (! $fallbackOnGeminiFailure && $acceptedAiAssignments === 0) {
            throw ValidationException::withMessages([
                'roster' => 'Gemini returned roster assignments, but none passed the roster policy checks. The request will be retried.',
            ]);
        }

        if (! $fallbackOnGeminiFailure) {
            return $selections;
        }

        return $this->fallbackGenerator->generate(
            $roster,
            $eligibleAssignments,
            $shiftTypes,
            $selections,
            [
                'avoid_entries' => $avoidSelections,
                'variation_seed' => $variationSeed,
            ]
        );
    }

    /**
     * @param Collection<int, StaffAssignment> $eligibleAssignments
     * @param Collection<int, ShiftType> $shiftTypes
     * @param array<int|string, array<string, mixed>> $entries
     * @param array{
     *   replace_existing_entries?: bool,
     *   variation_seed?: string
     * } $options
     * @return array{
     *   generation_mode:string,
     *   variation_seed:string,
     *   replace_existing_entries:bool,
     *   eligible_staff_count:int,
     *   shift_type_count:int,
     *   chunk_count:int,
     *   chunks:array<int, array{
     *     chunk_index:int,
     *     chunk_start:string,
     *     chunk_end:string,
     *     payload:array<string, mixed>
     *   }>
     * }
     */
    public function promptPreview(
        HrDutyRoster $roster,
        Collection $eligibleAssignments,
        Collection $shiftTypes,
        array $entries = [],
        array $options = []
    ): array {
        $roster->loadMissing('organization', 'organizationalUnit');

        $policy = $this->policyResolver->requireActiveVersion(
            $roster->organization,
            CarbonImmutable::parse($roster->start_date)
        );

        $eligibleAssignments = $eligibleAssignments->values();
        $eligibleById = $eligibleAssignments->keyBy(fn (StaffAssignment $assignment): string => (string) $assignment->id);
        $usableShiftTypes = $shiftTypes
            ->filter(fn (ShiftType $shiftType): bool => $shiftType->is_active && $shiftType->is_rosterable)
            ->filter(fn (ShiftType $shiftType): bool => $shiftType->effectiveNetMinutes() > 0)
            ->sortBy([
                ['start_time', 'asc'],
                ['name', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
        $this->assertPrerequisites($roster, $eligibleAssignments, $usableShiftTypes);

        $dates = $this->dateRange($roster);
        $validDates = collect($dates)->mapWithKeys(fn (CarbonImmutable $date): array => [$date->toDateString() => true]);
        $shiftTypeById = $usableShiftTypes->keyBy(fn (ShiftType $shiftType): string => (string) $shiftType->id);
        $previousSelections = $this->sanitizeSelections($entries, $eligibleById, $shiftTypeById, $validDates);
        $replaceExistingEntries = (bool) ($options['replace_existing_entries'] ?? false);
        $variationSeed = filled($options['variation_seed'] ?? null)
            ? (string) $options['variation_seed']
            : now()->format('YmdHisv');
        $selections = $replaceExistingEntries ? [] : $previousSelections;
        $avoidSelections = $replaceExistingEntries ? $previousSelections : [];
        $weekendDays = $this->weekendDays($roster);
        $dateChunks = $this->dateChunks($dates);

        return [
            'generation_mode' => $replaceExistingEntries ? 'new_variant' : 'fill_blanks',
            'variation_seed' => $variationSeed,
            'replace_existing_entries' => $replaceExistingEntries,
            'eligible_staff_count' => $eligibleAssignments->count(),
            'shift_type_count' => $usableShiftTypes->count(),
            'chunk_count' => count($dateChunks),
            'chunks' => collect($dateChunks)
                ->values()
                ->map(function (array $dateChunk, int $chunkIndex) use (
                    $roster,
                    $policy,
                    $eligibleAssignments,
                    $usableShiftTypes,
                    $selections,
                    $avoidSelections,
                    $shiftTypeById,
                    $weekendDays,
                    $replaceExistingEntries,
                    $variationSeed
                ): array {
                    $chunkValidDates = collect($dateChunk)->mapWithKeys(fn (CarbonImmutable $date): array => [$date->toDateString() => true]);
                    $chunkSelections = $this->restrictSelectionsToDates($selections, $chunkValidDates);
                    $chunkAvoidSelections = $this->restrictSelectionsToDates($avoidSelections, $chunkValidDates);

                    return [
                        'chunk_index' => $chunkIndex + 1,
                        'chunk_start' => $dateChunk[0]->toDateString(),
                        'chunk_end' => $dateChunk[array_key_last($dateChunk)]->toDateString(),
                        'payload' => $this->promptPayload(
                            $roster,
                            $policy,
                            $eligibleAssignments,
                            $usableShiftTypes,
                            $dateChunk,
                            $chunkSelections,
                            $chunkAvoidSelections,
                            $shiftTypeById,
                            $weekendDays,
                            $replaceExistingEntries,
                            $variationSeed.':chunk-'.($chunkIndex + 1),
                            $selections,
                            $avoidSelections
                        ),
                    ];
                })
                ->all(),
        ];
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
     * @param Collection<int, StaffAssignment> $eligibleAssignments
     * @param Collection<int, ShiftType> $shiftTypes
     * @param array<int, CarbonImmutable> $dates
     * @param array<int|string, array<string, string>> $selections
     * @param array<int|string, array<string, string>> $avoidSelections
     * @param array<int|string, array<string, string>> $currentSelections
     * @param array<int|string, array<string, string>> $currentAvoidSelections
     * @param Collection<string, ShiftType> $shiftTypeById
     * @param array<int, int> $weekendDays
     */
    private function buildPrompt(
        HrDutyRoster $roster,
        $policy,
        Collection $eligibleAssignments,
        Collection $shiftTypes,
        array $dates,
        array $selections,
        array $avoidSelections,
        Collection $shiftTypeById,
        array $weekendDays,
        bool $replaceExistingEntries,
        string $variationSeed,
        array $currentSelections,
        array $currentAvoidSelections
    ): string {
        $payload = $this->promptPayload(
            $roster,
            $policy,
            $eligibleAssignments,
            $shiftTypes,
            $dates,
            $selections,
            $avoidSelections,
            $shiftTypeById,
            $weekendDays,
            $replaceExistingEntries,
            $variationSeed,
            $currentSelections,
            $currentAvoidSelections
        );

        return json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @param Collection<int, StaffAssignment> $eligibleAssignments
     * @param Collection<int, ShiftType> $shiftTypes
     * @param array<int, CarbonImmutable> $dates
     * @param array<int|string, array<string, string>> $selections
     * @param array<int|string, array<string, string>> $avoidSelections
     * @param array<int|string, array<string, string>> $currentSelections
     * @param array<int|string, array<string, string>> $currentAvoidSelections
     * @param Collection<string, ShiftType> $shiftTypeById
     * @param array<int, int> $weekendDays
     * @return array<string, mixed>
     */
    private function promptPayload(
        HrDutyRoster $roster,
        $policy,
        Collection $eligibleAssignments,
        Collection $shiftTypes,
        array $dates,
        array $selections,
        array $avoidSelections,
        Collection $shiftTypeById,
        array $weekendDays,
        bool $replaceExistingEntries,
        string $variationSeed,
        array $currentSelections,
        array $currentAvoidSelections
    ): array {
        return [
            'instructions' => [
                $replaceExistingEntries
                    ? 'Generate a fresh roster variant that replaces the previous AI draft.'
                    : 'Return assignments only for blank roster cells.',
                'Use only the listed staff_assignment_id, shift_type_id, and dates.',
                'Use variation_seed to make this draft different from other valid drafts for the same roster.',
                'Avoid repeating previous_assignments_to_avoid when another legal staff/shift option exists.',
                'Spread assignments across available staff as evenly as practical.',
                'Balance day shifts, overnight shifts, weekend duties, total assignment count, total minutes, and individual shift types as evenly as practical.',
                'When roster teams are provided, keep staff from the same team working together as much as legal coverage allows while still balancing work across teams.',
                'When filling overnight shifts, prefer staff with fewer current or projected overnight shifts and avoid consecutive overnight duty unless coverage cannot otherwise be met.',
                'When filling day shifts, prefer staff with fewer current or projected day shifts so day work is also rotated fairly.',
                'Rotate high-demand shifts instead of repeatedly giving them to the same staff member.',
                'Do not assign more than one shift to the same staff member on the same date.',
                'Do not clone the same date and shift pattern across staff unless the listed constraints leave no other valid option.',
                $replaceExistingEntries
                    ? 'Previous assignments are not locked; they are only shown so this run can produce a different draft.'
                    : 'Existing manual assignments must be preserved.',
            ],
            'generation_mode' => $replaceExistingEntries ? 'new_variant' : 'fill_blanks',
            'variation_seed' => $variationSeed,
            'roster' => [
                'name' => $roster->name,
                'client_space' => $roster->organizationalUnit?->name,
                'discipline' => $roster->cadre_or_discipline,
                'start_date' => $roster->start_date?->toDateString(),
                'end_date' => $roster->end_date?->toDateString(),
                'weekend_days' => $weekendDays,
                'teams' => $roster->teamNames(),
            ],
            'fairness_goals' => [
                'assignment_count' => 'Keep the number of rostered shifts per staff member as even as legal coverage allows.',
                'total_minutes' => 'Keep total rostered net minutes close across staff.',
                'day_shifts' => 'Rotate non-overnight day shifts across eligible staff.',
                'night_shifts' => 'Rotate overnight shifts across eligible staff and avoid repeated overnight duty.',
                'weekend_duties' => 'Rotate configured weekend days across eligible staff.',
                'shift_type_rotation' => 'Avoid concentrating the same shift type on one staff member.',
            ],
            'scheduling_requirements' => [
                'Use rosterable active shifts only.',
                'Schedule only eligible staff listed in eligible_staff.',
                'Respect fixed roster profiles, fixed days, preferred shifts, excluded shifts, and max overnight limits.',
                'Respect daily caps, weekly ceilings, rest gaps, consecutive work day limits, blocked days, and staff unavailability; invalid suggestions will be rejected.',
                'Stagger work days and off days across staff.',
            ],
            'policy' => [
                'weekly_standard_minutes' => (int) $policy->weekly_standard_minutes,
                'weekly_absolute_ceiling_minutes' => (int) $policy->weekly_absolute_ceiling_minutes,
                'daily_net_cap_minutes' => (int) $policy->daily_net_cap_minutes,
                'minimum_rest_gap_minutes' => (int) $policy->minimum_rest_gap_minutes,
                'consecutive_work_days_limit' => (int) $policy->consecutive_work_days_limit,
                'rest_after_consecutive_days_minutes' => (int) $policy->rest_after_consecutive_days_minutes,
                'anchor_window_minutes' => (int) $policy->anchor_window_minutes,
            ],
            'dates' => collect($dates)->map(fn (CarbonImmutable $date): string => $date->toDateString())->values()->all(),
            'shift_types' => $shiftTypes->map(fn (ShiftType $shiftType): array => [
                'id' => (int) $shiftType->id,
                'code' => $shiftType->code,
                'name' => $shiftType->name,
                'start_time' => $shiftType->start_time,
                'end_time' => $shiftType->end_time,
                'net_minutes' => $shiftType->effectiveNetMinutes(),
                'is_night_shift' => $shiftType->crossesMidnight(),
            ])->values()->all(),
            'eligible_staff' => $eligibleAssignments->map(fn (StaffAssignment $assignment): array => [
                'id' => (int) $assignment->id,
                'title' => $assignment->staff_title,
                'assignment_type' => $assignment->client_space_assignment_type,
                'team' => $roster->teamLabelForStaffAssignment($assignment->id),
                'rostering_profile' => $this->profileForPrompt($assignment),
            ])->values()->all(),
            'existing_assignments' => $this->existingAssignmentsForPrompt($selections),
            'current_workload' => $this->currentWorkloadForPrompt($currentSelections, $shiftTypeById, $eligibleAssignments, $weekendDays),
            'previous_assignments_to_avoid' => $this->existingAssignmentsForPrompt($avoidSelections),
            'previous_workload_to_avoid' => $this->currentWorkloadForPrompt($currentAvoidSelections, $shiftTypeById, $eligibleAssignments, $weekendDays),
        ];
    }

    /**
     * @param array<int, CarbonImmutable> $dates
     * @return array<int, array<int, CarbonImmutable>>
     */
    private function dateChunks(array $dates): array
    {
        $maxWorkdays = max(1, (int) config('services.gemini.roster_max_workdays_per_request', 7));

        return array_chunk($dates, $maxWorkdays);
    }

    /**
     * @param Collection<string, bool> $validDateKeys
     * @param array<int|string, array<string, string>> $selections
     * @return array<int|string, array<string, string>>
     */
    private function restrictSelectionsToDates(array $selections, Collection $validDateKeys): array
    {
        $filtered = [];

        foreach ($selections as $staffId => $dateMap) {
            if (! is_array($dateMap)) {
                continue;
            }

            foreach ($dateMap as $dateKey => $shiftTypeId) {
                if (! $validDateKeys->has((string) $dateKey)) {
                    continue;
                }

                $filtered[$staffId][(string) $dateKey] = (string) $shiftTypeId;
            }
        }

        return $filtered;
    }

    /**
     * @param array<int|string, array<string, string>> $selections
     * @return array<int, array<string, mixed>>
     */
    private function existingAssignmentsForPrompt(array $selections): array
    {
        $rows = [];

        foreach ($selections as $staffId => $dateMap) {
            foreach ($dateMap as $date => $shiftTypeId) {
                $rows[] = [
                    'staff_assignment_id' => (int) $staffId,
                    'date' => $date,
                    'shift_type_id' => (int) $shiftTypeId,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<int|string, array<string, string>> $selections
     */
    private function matchesExistingSelection(array $selections, string $staffId, string $dateKey, string $shiftTypeId): bool
    {
        return (string) ($selections[$staffId][$dateKey] ?? '') === $shiftTypeId;
    }

    /**
     * @return array<string, mixed>
     */
    private function profileForPrompt(StaffAssignment $assignment): array
    {
        $profile = $assignment->rosteringProfile;

        if (! $profile || ! $profile->is_active) {
            return [
                'mode' => 'dynamic',
                'fixed_shift_type_id' => null,
                'fixed_days_of_week' => [],
                'preferred_shift_type_ids' => [],
                'excluded_shift_type_ids' => [],
                'max_night_shifts_per_cycle' => null,
            ];
        }

        return [
            'mode' => $profile->rostering_mode,
            'fixed_shift_type_id' => $profile->fixed_shift_type_id ? (int) $profile->fixed_shift_type_id : null,
            'fixed_days_of_week' => $profile->fixedDays(),
            'preferred_shift_type_ids' => $profile->preferredShiftIds(),
            'excluded_shift_type_ids' => $profile->excludedShiftIds(),
            'max_night_shifts_per_cycle' => $profile->max_night_shifts_per_cycle !== null
                ? (int) $profile->max_night_shifts_per_cycle
                : null,
        ];
    }

    /**
     * @param array<int|string, array<string, string>> $selections
     * @param Collection<string, ShiftType> $shiftTypeById
     * @param Collection<int, StaffAssignment> $eligibleAssignments
     * @param array<int, int> $weekendDays
     * @return array<int, array<string, mixed>>
     */
    private function currentWorkloadForPrompt(
        array $selections,
        Collection $shiftTypeById,
        Collection $eligibleAssignments,
        array $weekendDays
    ): array {
        $workload = $eligibleAssignments
            ->mapWithKeys(fn (StaffAssignment $assignment): array => [
                (string) $assignment->id => [
                    'staff_assignment_id' => (int) $assignment->id,
                    'assignment_count' => 0,
                    'total_minutes' => 0,
                    'day_shift_count' => 0,
                    'night_shift_count' => 0,
                    'weekend_shift_count' => 0,
                    'shift_counts' => [],
                ],
            ])
            ->all();

        foreach ($selections as $staffId => $dateMap) {
            $staffKey = (string) $staffId;
            $workload[$staffKey] ??= [
                'staff_assignment_id' => (int) $staffId,
                'assignment_count' => 0,
                'total_minutes' => 0,
                'day_shift_count' => 0,
                'night_shift_count' => 0,
                'weekend_shift_count' => 0,
                'shift_counts' => [],
            ];

            foreach ($dateMap as $date => $shiftTypeId) {
                $shiftType = $shiftTypeById->get((string) $shiftTypeId);

                if (! $shiftType) {
                    continue;
                }

                $isNightShift = $shiftType->crossesMidnight();

                $workload[$staffKey]['assignment_count']++;
                $workload[$staffKey]['total_minutes'] += $shiftType->effectiveNetMinutes();
                $workload[$staffKey]['day_shift_count'] += $isNightShift ? 0 : 1;
                $workload[$staffKey]['night_shift_count'] += $isNightShift ? 1 : 0;
                $workload[$staffKey]['weekend_shift_count'] += $this->isWeekendDate(CarbonImmutable::parse($date), $weekendDays) ? 1 : 0;
                $workload[$staffKey]['shift_counts'][(string) $shiftType->id] = ((int) ($workload[$staffKey]['shift_counts'][(string) $shiftType->id] ?? 0)) + 1;
            }
        }

        return array_values($workload);
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
}
