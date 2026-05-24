<?php

namespace App\Services;

use App\Jobs\GenerateAiRosterDraft;
use App\Jobs\GenerateRosterDraft;
use App\Models\ApprovalWorkflow;
use App\Models\HrApprovalRequest;
use App\Models\HrAttendanceLedger;
use App\Models\HrClientSpaceStaffAssignment;
use App\Models\HrOpenShift;
use App\Models\HrDutyRoster;
use App\Models\HrDutyRosterEntry;
use App\Models\HrOrganizationalUnit;
use App\Models\Organization;
use App\Models\ShiftType;
use App\Models\StaffAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DutyRosterService
{
    public function __construct(
        private readonly RosteringAuthorizationService $authorizationService,
        private readonly RosterPolicyValidator $rosterPolicyValidator,
        private readonly RosterDraftGenerator $rosterDraftGenerator,
        private readonly GeminiRosterDraftGenerator $geminiRosterDraftGenerator
    ) {
    }

    public function accessibleClientSpaces(User $user, Organization $organization): Collection
    {
        $query = HrOrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->clientSpaces()
            ->withCount([
                'staffAssignments as active_staff_count' => fn ($staffQuery) => $staffQuery->where('status', 'active'),
            ])
            ->orderBy('name');

        if ($this->canViewAllClientSpaces($user)) {
            return $query->get();
        }

        if (! $user->staff_uuid) {
            return collect();
        }

        return $query
            ->where(function ($clientSpaceQuery) use ($user): void {
                $clientSpaceQuery
                    ->whereHas('staffAssignments', function ($staffQuery) use ($user): void {
                        $staffQuery
                            ->where('staff_uuid', $user->staff_uuid)
                            ->where('status', 'active');
                    })
                    ->orWhereHas('clientSpaceStaffAssignments', function ($assignmentQuery) use ($user): void {
                        $assignmentQuery
                            ->where('assignment_type', HrClientSpaceStaffAssignment::TYPE_SECONDARY)
                            ->where('staff_uuid', $user->staff_uuid)
                            ->where('status', HrClientSpaceStaffAssignment::STATUS_ACTIVE);
                    });
            })
            ->get();
    }

    public function availableDisciplines(HrOrganizationalUnit $clientSpace): Collection
    {
        return $this->rosterEligibleAssignments($clientSpace)
            ->filter(fn (StaffAssignment $assignment): bool => filled($this->disciplineResolver()->assignmentLabel($assignment)))
            ->reduce(function (Collection $carry, StaffAssignment $assignment): Collection {
                $label = $this->disciplineResolver()->assignmentLabel($assignment);
                $normalized = $this->normalizeDiscipline($label);

                if ($normalized === '') {
                    return $carry;
                }

                $existing = $carry->get($normalized, [
                    'key' => $normalized,
                    'label' => $this->cleanDisciplineLabel($label),
                    'count' => 0,
                ]);

                $existing['count']++;
                $carry->put($normalized, $existing);

                return $carry;
            }, collect())
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function eligibleAssignments(HrOrganizationalUnit $clientSpace, string|array $discipline): Collection
    {
        $normalizedDisciplines = $this->normalizeDisciplineList($discipline);

        return $this->rosterEligibleAssignments($clientSpace)
            ->filter(fn (StaffAssignment $assignment): bool => in_array(
                $this->normalizeDiscipline($this->disciplineResolver()->assignmentLabel($assignment)),
                $normalizedDisciplines,
                true
            ))
            ->values();
    }

    public function previewRosterApprovalWorkflow(HrOrganizationalUnit $clientSpace, string|array $discipline): ?ApprovalWorkflow
    {
        $normalizedDisciplines = $this->normalizeDisciplineList($discipline);

        if ($normalizedDisciplines === []) {
            return null;
        }

        $candidates = ApprovalWorkflow::query()
            ->where('organization_id', $clientSpace->organization_id)
            ->where('approval_category', 'roster')
            ->where('is_active', true)
            ->where(function ($query) use ($clientSpace): void {
                $query
                    ->where('organizational_unit_id', $clientSpace->id)
                    ->orWhereNull('organizational_unit_id');
            })
            ->with('approvers')
            ->get();

        return $candidates
            ->map(fn (ApprovalWorkflow $workflow): array => [
                'workflow' => $workflow,
                'score' => $this->rosterApprovalWorkflowScore($workflow, $normalizedDisciplines, $clientSpace->id),
            ])
            ->filter(fn (array $candidate): bool => $candidate['score'] > 0)
            ->sortByDesc('score')
            ->pluck('workflow')
            ->first();
    }

    public function canViewClientSpace(User $user, HrOrganizationalUnit $clientSpace): bool
    {
        if (! $clientSpace->isClientSpace()) {
            return false;
        }

        if ($this->canViewAllClientSpaces($user)) {
            return true;
        }

        if (! $user->staff_uuid) {
            return false;
        }

        return $clientSpace->staffAssignments()
            ->where('staff_uuid', $user->staff_uuid)
            ->where('status', 'active')
            ->exists()
            || $clientSpace->clientSpaceStaffAssignments()
                ->where('assignment_type', HrClientSpaceStaffAssignment::TYPE_SECONDARY)
                ->where('staff_uuid', $user->staff_uuid)
                ->where('status', HrClientSpaceStaffAssignment::STATUS_ACTIVE)
                ->exists();
    }

    public function createDraft(Organization $organization, HrOrganizationalUnit $clientSpace, User $user, array $payload): HrDutyRoster
    {
        $validated = $this->validateHeaderPayload($organization, $clientSpace, $payload);
        $this->assertCanManageRoster($user, $clientSpace, $validated['cadre_or_disciplines']);
        $eligibleAssignments = $this->eligibleAssignments($clientSpace, $validated['cadre_or_disciplines']);
        $teamAssignments = $this->resolveTeamAssignments(null, $eligibleAssignments, $validated['team_names']);

        return DB::transaction(function () use ($organization, $user, $validated, $teamAssignments) {
            $roster = HrDutyRoster::create([
                'organization_id' => $organization->id,
                'organizational_unit_id' => $validated['client_space_id'],
                'cadre_or_discipline' => $validated['cadre_or_discipline'],
                'discipline_titles' => $validated['cadre_or_disciplines'],
                'team_definitions' => $validated['team_names'],
                'team_assignments' => $teamAssignments,
                'name' => $validated['name'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'created_by' => $user->staff_uuid,
                'status' => HrDutyRoster::STATUS_DRAFT,
                'approval_status' => HrDutyRoster::APPROVAL_NOT_SUBMITTED,
            ]);

            $this->syncEntries($roster, $validated['entries'] ?? []);

            return $roster->fresh(['entries.shiftType', 'approvalRequest.events', 'approvalRequests']);
            });
    }

    public function publishedEntriesForStaff(
        User $user,
        Organization $organization,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): Collection {
        if (! $user->staff_uuid) {
            return collect();
        }

        $query = HrDutyRosterEntry::query()
            ->where('organization_id', $organization->id)
            ->where('staff_uuid', $user->staff_uuid)
            ->whereHas('dutyRoster', function ($rosterQuery): void {
                $rosterQuery
                    ->where('status', HrDutyRoster::STATUS_PUBLISHED)
                    ->where('approval_status', HrDutyRoster::APPROVAL_APPROVED);
            })
            ->with([
                'shiftType',
                'staffAssignment',
                'dutyRoster.organizationalUnit',
            ])
            ->orderBy('roster_date')
            ->orderBy('id');

        if ($startDate) {
            $query->whereDate('roster_date', '>=', $startDate->toDateString());
        }

        if ($endDate) {
            $query->whereDate('roster_date', '<=', $endDate->toDateString());
        }

        return $query->get();
    }

    public function visibleEntriesForStaff(
        User $user,
        Organization $organization,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): Collection {
        if (! $user->staff_uuid) {
            return collect();
        }

        $query = HrDutyRosterEntry::query()
            ->where('organization_id', $organization->id)
            ->where('staff_uuid', $user->staff_uuid)
            ->whereHas('dutyRoster', function ($rosterQuery): void {
                $rosterQuery->where(function ($visibleRosterQuery): void {
                    $visibleRosterQuery
                        ->where('status', HrDutyRoster::STATUS_DRAFT)
                        ->orWhere(function ($publishedRosterQuery): void {
                            $publishedRosterQuery
                                ->where('status', HrDutyRoster::STATUS_PUBLISHED)
                                ->where('approval_status', HrDutyRoster::APPROVAL_APPROVED);
                        });
                });
            })
            ->with([
                'shiftType',
                'staffAssignment',
                'dutyRoster.organizationalUnit',
            ])
            ->orderBy('roster_date')
            ->orderBy('id');

        if ($startDate) {
            $query->whereDate('roster_date', '>=', $startDate->toDateString());
        }

        if ($endDate) {
            $query->whereDate('roster_date', '<=', $endDate->toDateString());
        }

        return $query->get();
    }

    public function saveDraft(HrDutyRoster $roster, User $user, array $payload): HrDutyRoster
    {
        $this->assertCanManageRoster($user, $roster->organizationalUnit, $roster->disciplineTitles());
        $this->assertNoActiveAiGeneration($roster);

        if (! $roster->isEditable() && $roster->approval_status !== HrDutyRoster::APPROVAL_REJECTED) {
            throw ValidationException::withMessages([
                'roster' => 'This roster cannot be edited in its current state.',
            ]);
        }

        $validated = $this->validateHeaderPayload($roster->organization, $roster->organizationalUnit, array_merge($payload, [
            'cadre_or_discipline' => $roster->cadre_or_discipline,
            'cadre_or_disciplines' => $roster->disciplineTitles(),
        ]));
        $eligibleAssignments = $this->eligibleAssignments($roster->organizationalUnit, $validated['cadre_or_disciplines']);
        $teamAssignments = $this->resolveTeamAssignments($roster->fresh(), $eligibleAssignments, $validated['team_names']);

        return DB::transaction(function () use ($roster, $validated, $teamAssignments) {
            $roster->update([
                'name' => $validated['name'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'team_definitions' => $validated['team_names'],
                'team_assignments' => $teamAssignments,
                'status' => HrDutyRoster::STATUS_DRAFT,
                'approval_status' => HrDutyRoster::APPROVAL_NOT_SUBMITTED,
                'approval_request_id' => null,
                'rejected_at' => null,
            ]);

            $this->syncEntries($roster->fresh('organizationalUnit'), $validated['entries'] ?? []);

            return $roster->fresh(['entries.shiftType', 'approvalRequest.events', 'approvalRequests']);
        });
    }

    public function generateDraft(HrDutyRoster $roster, User $user, array $payload): HrDutyRoster
    {
        $roster = $this->reconcileAiGenerationState($roster);
        $this->assertCanManageRoster($user, $roster->organizationalUnit, $roster->disciplineTitles());

        $isCurrentAutoJob = filled($payload['ai_generation_token'] ?? null)
            && (string) ($payload['ai_generation_token'] ?? '') === (string) $roster->ai_generation_token
            && ($payload['ai_generation_source'] ?? null) === HrDutyRoster::AI_GENERATION_SOURCE_AUTO
            && $this->isCurrentGeneration($roster, (string) $payload['ai_generation_token'], HrDutyRoster::AI_GENERATION_SOURCE_AUTO);

        if (! $isCurrentAutoJob) {
            $this->assertNoActiveAiGeneration($roster);
        }

        if (! $isCurrentAutoJob && ! $roster->isEditable() && $roster->approval_status !== HrDutyRoster::APPROVAL_REJECTED) {
            throw ValidationException::withMessages([
                'roster' => 'This roster cannot be edited in its current state.',
            ]);
        }

        $validated = $this->validateHeaderPayload($roster->organization, $roster->organizationalUnit, array_merge($payload, [
            'cadre_or_discipline' => $roster->cadre_or_discipline,
            'cadre_or_disciplines' => $roster->disciplineTitles(),
        ]));
        $generationOptions = $this->autoGenerationOptions($payload);

        if (! $isCurrentAutoJob && $this->shouldQueueAutoDraftGeneration($roster, $validated)) {
            return $this->startAutoDraftGeneration($roster, $user, $validated, $generationOptions);
        }

        return $this->performGenerateDraft(
            $roster,
            $validated,
            $generationOptions,
            $isCurrentAutoJob ? (string) ($payload['ai_generation_token'] ?? '') : null,
            $isCurrentAutoJob ? HrDutyRoster::AI_GENERATION_SOURCE_AUTO : null
        );
    }

    private function performGenerateDraft(
        HrDutyRoster $roster,
        array $validated,
        array $generationOptions = [],
        ?string $generationToken = null,
        ?string $generationSource = null
    ): HrDutyRoster {
        $eligibleAssignments = $this->eligibleAssignments($roster->organizationalUnit, $validated['cadre_or_disciplines']);
        $teamAssignments = $this->resolveTeamAssignments($roster->fresh(), $eligibleAssignments, $validated['team_names']);

        return DB::transaction(function () use ($roster, $validated, $generationOptions, $generationToken, $generationSource, $eligibleAssignments, $teamAssignments) {
            $roster->update([
                'name' => $validated['name'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'team_definitions' => $validated['team_names'],
                'team_assignments' => $teamAssignments,
                'status' => HrDutyRoster::STATUS_DRAFT,
                'approval_status' => HrDutyRoster::APPROVAL_NOT_SUBMITTED,
                'approval_request_id' => null,
                'rejected_at' => null,
            ]);

            $freshRoster = $roster->fresh(['organization', 'organizationalUnit']);
            $shiftTypes = ShiftType::query()
                ->where('organization_id', $freshRoster->organization_id)
                ->where('is_active', true)
                ->get();
            $existingEntries = $validated['entries'] ?? [];
            $replaceExistingEntries = (bool) ($generationOptions['replace_existing_entries'] ?? false);
            $generatedEntries = $this->rosterDraftGenerator->generate(
                $freshRoster,
                $eligibleAssignments,
                $shiftTypes,
                $replaceExistingEntries ? [] : $existingEntries,
                [
                    'avoid_entries' => $replaceExistingEntries ? $existingEntries : [],
                    'variation_seed' => $generationOptions['variation_seed'] ?? '',
                ]
            );

            if (
                $generationToken !== null
                && $generationSource !== null
                && ! $this->isCurrentGeneration(
                    $roster->fresh(),
                    $generationToken,
                    $generationSource
                )
            ) {
                return $roster->fresh(['entries.shiftType', 'approvalRequest.events', 'approvalRequests']);
            }

            $this->syncEntries($freshRoster, $generatedEntries);

            if ($generationToken === null && $generationSource === null) {
                $freshRoster->update([
                    'ai_generation_status' => HrDutyRoster::AI_GENERATION_COMPLETED,
                    'ai_generation_source' => HrDutyRoster::AI_GENERATION_SOURCE_AUTO,
                    'ai_generation_token' => (string) ($generationOptions['completion_token'] ?? Str::uuid()),
                'ai_generation_message' => 'Automatic roster generation completed and the roster draft was updated.',
                'ai_generation_attempts' => 1,
                'ai_generation_started_at' => $freshRoster->ai_generation_started_at ?: now(),
                'ai_generation_heartbeat_at' => now(),
                'ai_generation_completed_at' => now(),
                'ai_generation_failed_at' => null,
            ]);
            }

            return $roster->fresh(['entries.shiftType', 'approvalRequest.events', 'approvalRequests']);
        });
    }

    public function generateAiDraft(HrDutyRoster $roster, User $user, array $payload): HrDutyRoster
    {
        $this->assertCanManageRoster($user, $roster->organizationalUnit, $roster->disciplineTitles());

        $isCurrentAiJob = filled($payload['ai_generation_token'] ?? null)
            && (string) $payload['ai_generation_token'] === (string) $roster->ai_generation_token
            && $this->isCurrentGeneration(
                $roster,
                (string) $payload['ai_generation_token'],
                HrDutyRoster::AI_GENERATION_SOURCE_GEMINI
            );

        if (! $isCurrentAiJob && ! $roster->isEditable() && $roster->approval_status !== HrDutyRoster::APPROVAL_REJECTED) {
            throw ValidationException::withMessages([
                'roster' => 'This roster cannot be edited in its current state.',
            ]);
        }

        $generationOptions = [
            'replace_existing_entries' => (bool) ($payload['replace_existing_entries'] ?? false),
            'variation_seed' => filled($payload['variation_seed'] ?? null)
                ? (string) $payload['variation_seed']
                : (string) Str::uuid(),
            'fallback_on_gemini_failure' => (bool) ($payload['fallback_on_gemini_failure'] ?? true),
        ];

        $validated = $this->validateHeaderPayload($roster->organization, $roster->organizationalUnit, array_merge($payload, [
            'cadre_or_discipline' => $roster->cadre_or_discipline,
            'cadre_or_disciplines' => $roster->disciplineTitles(),
        ]));
        $eligibleAssignments = $this->eligibleAssignments($roster->organizationalUnit, $validated['cadre_or_disciplines']);
        $teamAssignments = $this->resolveTeamAssignments($roster->fresh(), $eligibleAssignments, $validated['team_names']);
        $freshRoster = $roster->fresh(['organization', 'organizationalUnit']);
        $shiftTypes = ShiftType::query()
            ->where('organization_id', $freshRoster->organization_id)
            ->where('is_active', true)
            ->get();
        $generationToken = filled($payload['ai_generation_token'] ?? null)
            ? (string) $payload['ai_generation_token']
            : null;
        $attemptNumber = max(1, (int) ($payload['ai_attempt_number'] ?? 1));
        $maxAttempts = max($attemptNumber, (int) ($payload['ai_max_attempts'] ?? $attemptNumber));
        $generatedEntries = $this->geminiRosterDraftGenerator->generate(
            $freshRoster,
            $eligibleAssignments,
            $shiftTypes,
            $validated['entries'] ?? [],
            array_merge($generationOptions, [
                'progress_callback' => function (array $progress) use ($roster, $generationToken, $attemptNumber, $maxAttempts): void {
                    $this->updateGeminiGenerationProgress(
                        $roster->id,
                        $generationToken,
                        $attemptNumber,
                        $maxAttempts,
                        $progress
                    );
                },
            ])
        );

        return $this->persistGeneratedAiDraft(
            $roster->id,
            $validated,
            $teamAssignments,
            $generatedEntries,
            $generationToken
        );
    }

    public function generateAutomaticFallbackForAiDraft(HrDutyRoster $roster, User $user, array $payload): HrDutyRoster
    {
        $this->assertCanManageRoster($user, $roster->organizationalUnit, $roster->disciplineTitles());

        $generationToken = (string) ($payload['ai_generation_token'] ?? '');

        if (! filled($generationToken) || ! $this->isCurrentGeneration($roster, $generationToken, HrDutyRoster::AI_GENERATION_SOURCE_GEMINI)) {
            return $roster->fresh(['entries.shiftType', 'approvalRequest.events', 'approvalRequests']);
        }

        $validated = $this->validateHeaderPayload($roster->organization, $roster->organizationalUnit, array_merge($payload, [
            'cadre_or_discipline' => $roster->cadre_or_discipline,
            'cadre_or_disciplines' => $roster->disciplineTitles(),
        ]));

        return $this->performGenerateDraft(
            $roster,
            $validated,
            [
                'replace_existing_entries' => (bool) ($payload['replace_existing_entries'] ?? false),
                'variation_seed' => filled($payload['variation_seed'] ?? null)
                    ? (string) $payload['variation_seed']
                    : (string) Str::uuid(),
            ],
            $generationToken,
            HrDutyRoster::AI_GENERATION_SOURCE_GEMINI
        );
    }

    public function startAiDraftGeneration(HrDutyRoster $roster, User $user, array $payload): HrDutyRoster
    {
        $roster = $this->reconcileAiGenerationState($roster);
        $this->assertCanManageRoster($user, $roster->organizationalUnit, $roster->disciplineTitles());

        if (! $roster->isEditable() && $roster->approval_status !== HrDutyRoster::APPROVAL_REJECTED) {
            throw ValidationException::withMessages([
                'roster' => 'This roster cannot be edited in its current state.',
            ]);
        }

        $generationToken = (string) Str::uuid();
        $variationSeed = (string) Str::uuid();
        $validated = $this->validateHeaderPayload($roster->organization, $roster->organizationalUnit, array_merge($payload, [
            'cadre_or_discipline' => $roster->cadre_or_discipline,
            'cadre_or_disciplines' => $roster->disciplineTitles(),
        ]));
        $eligibleAssignments = $this->eligibleAssignments($roster->organizationalUnit, $validated['cadre_or_disciplines']);
        $shiftTypes = ShiftType::query()
            ->where('organization_id', $roster->organization_id)
            ->where('is_active', true)
            ->get();

        $this->assertGeminiConfigured();
        $this->geminiRosterDraftGenerator->assertPrerequisites($roster, $eligibleAssignments, $shiftTypes);

        $jobPayload = [
            'client_space_id' => $validated['client_space_id'],
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'entries' => $validated['entries'] ?? [],
            'team_grouping_enabled' => $validated['team_grouping_enabled'],
            'team_names' => $validated['team_names'],
            'replace_existing_entries' => true,
            'variation_seed' => $variationSeed,
            'fallback_on_gemini_failure' => false,
            'ai_generation_token' => $generationToken,
        ];
        $teamAssignments = $this->resolveTeamAssignments($roster->fresh(), $eligibleAssignments, $validated['team_names']);

        $queuedRoster = DB::transaction(function () use ($roster, $validated, $generationToken, $teamAssignments): HrDutyRoster {
            $roster->update([
                'name' => $validated['name'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'team_definitions' => $validated['team_names'],
                'team_assignments' => $teamAssignments,
                'status' => HrDutyRoster::STATUS_DRAFT,
                'approval_status' => HrDutyRoster::APPROVAL_NOT_SUBMITTED,
                'approval_request_id' => null,
                'rejected_at' => null,
                'ai_generation_status' => HrDutyRoster::AI_GENERATION_QUEUED,
                'ai_generation_source' => HrDutyRoster::AI_GENERATION_SOURCE_GEMINI,
                'ai_generation_token' => $generationToken,
                'ai_generation_message' => 'Gemini roster generation has started and will keep retrying until it returns usable assignments.',
                'ai_generation_attempts' => 0,
                'ai_generation_started_at' => now(),
                'ai_generation_heartbeat_at' => now(),
                'ai_generation_completed_at' => null,
                'ai_generation_failed_at' => null,
            ]);

            return $roster->fresh(['entries.shiftType', 'approvalRequest.events', 'approvalRequests']);
        });

        $this->dispatchAsyncRosterJob(new GenerateAiRosterDraft(
            $queuedRoster->id,
            $user->id,
            $jobPayload,
            $generationToken
        ));

        return $queuedRoster;
    }

    private function startAutoDraftGeneration(HrDutyRoster $roster, User $user, array $validated, array $generationOptions): HrDutyRoster
    {
        $generationToken = (string) Str::uuid();
        $jobPayload = [
            'client_space_id' => $validated['client_space_id'],
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'entries' => $validated['entries'] ?? [],
            'team_grouping_enabled' => $validated['team_grouping_enabled'],
            'team_names' => $validated['team_names'],
            'replace_existing_entries' => (bool) ($generationOptions['replace_existing_entries'] ?? false),
            'variation_seed' => (string) ($generationOptions['variation_seed'] ?? ''),
            'ai_generation_token' => $generationToken,
            'ai_generation_source' => HrDutyRoster::AI_GENERATION_SOURCE_AUTO,
        ];

        $eligibleAssignments = $this->eligibleAssignments($roster->organizationalUnit, $validated['cadre_or_disciplines']);
        $teamAssignments = $this->resolveTeamAssignments($roster->fresh(), $eligibleAssignments, $validated['team_names']);

        $queuedRoster = DB::transaction(function () use ($roster, $validated, $generationToken, $teamAssignments): HrDutyRoster {
            $roster->update([
                'name' => $validated['name'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'team_definitions' => $validated['team_names'],
                'team_assignments' => $teamAssignments,
                'status' => HrDutyRoster::STATUS_DRAFT,
                'approval_status' => HrDutyRoster::APPROVAL_NOT_SUBMITTED,
                'approval_request_id' => null,
                'rejected_at' => null,
                'ai_generation_status' => HrDutyRoster::AI_GENERATION_QUEUED,
                'ai_generation_source' => HrDutyRoster::AI_GENERATION_SOURCE_AUTO,
                'ai_generation_token' => $generationToken,
                'ai_generation_message' => 'Automatic roster generation has started and is running in the background.',
                'ai_generation_attempts' => 0,
                'ai_generation_started_at' => now(),
                'ai_generation_heartbeat_at' => now(),
                'ai_generation_completed_at' => null,
                'ai_generation_failed_at' => null,
            ]);

            return $roster->fresh(['entries.shiftType', 'approvalRequest.events', 'approvalRequests']);
        });

        $this->dispatchAsyncRosterJob(new GenerateRosterDraft(
            $queuedRoster->id,
            $user->id,
            $jobPayload,
            $generationToken
        ));

        return $queuedRoster;
    }

    private function dispatchAsyncRosterJob(GenerateAiRosterDraft|GenerateRosterDraft $job): void
    {
        $dispatchMode = (string) config('services.roster.async_dispatch_mode', 'after_response');

        if (app()->environment('testing') && $dispatchMode === 'after_response') {
            $dispatchMode = 'database_queue';
        }

        if ($dispatchMode === 'database_queue') {
            dispatch($job->onConnection('database'))->afterResponse();

            return;
        }

        app()->terminating(static function () use ($job): void {
            app()->call([$job, 'handle']);
        });
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{replace_existing_entries: bool, variation_seed: string, completion_token: string}
     */
    private function autoGenerationOptions(array $payload): array
    {
        $replaceExistingEntries = (bool) ($payload['replace_existing_entries'] ?? false);

        return [
            'replace_existing_entries' => $replaceExistingEntries,
            'variation_seed' => $replaceExistingEntries
                ? (filled($payload['variation_seed'] ?? null)
                    ? (string) $payload['variation_seed']
                    : (string) Str::uuid())
                : '',
            'completion_token' => filled($payload['completion_token'] ?? null)
                ? (string) $payload['completion_token']
                : (string) Str::uuid(),
        ];
    }

    public function cancelAiGeneration(HrDutyRoster $roster, User $user): HrDutyRoster
    {
        $roster = $this->reconcileAiGenerationState($roster);
        $this->assertCanManageRoster($user, $roster->organizationalUnit, $roster->disciplineTitles());

        if (! $roster->hasActiveAiGeneration()) {
            return $roster;
        }

        $message = $roster->activeGenerationSource() === HrDutyRoster::AI_GENERATION_SOURCE_AUTO
            ? 'Automatic roster generation was stopped.'
            : 'Gemini roster generation was stopped.';

        $roster->update([
            'ai_generation_status' => HrDutyRoster::AI_GENERATION_FAILED,
            'ai_generation_message' => $message,
            'ai_generation_heartbeat_at' => now(),
            'ai_generation_completed_at' => null,
            'ai_generation_failed_at' => now(),
        ]);

        return $roster->fresh(['entries.shiftType', 'approvalRequest.events', 'approvalRequests']);
    }

    public function reconcileAiGenerationState(HrDutyRoster $roster): HrDutyRoster
    {
        if (! $roster->hasActiveAiGeneration() || ! $this->aiGenerationHasExpired($roster)) {
            return $roster;
        }

        $message = $roster->activeGenerationSource() === HrDutyRoster::AI_GENERATION_SOURCE_AUTO
            ? 'Automatic roster generation timed out before completion. Check that the queue worker is running, then try again.'
            : 'Gemini roster generation timed out before completion. Check that the queue worker is running, then try again.';

        $roster->update([
            'ai_generation_status' => HrDutyRoster::AI_GENERATION_FAILED,
            'ai_generation_message' => $message,
            'ai_generation_heartbeat_at' => now(),
            'ai_generation_completed_at' => null,
            'ai_generation_failed_at' => now(),
        ]);

        return $roster;
    }

    public function submitForApproval(HrDutyRoster $roster, User $user): HrDutyRoster
    {
        $this->assertCanManageRoster($user, $roster->organizationalUnit, $roster->disciplineTitles());
        $this->assertNoActiveAiGeneration($roster);
        $this->ensureRosterCanBeSubmitted($roster);

        if ($user->canManageAllApprovals()) {
            return $this->publishDirectly($roster, $user);
        }

        $workflow = $this->previewRosterApprovalWorkflow($roster->organizationalUnit, $roster->disciplineTitles());

        if (! $workflow) {
            throw ValidationException::withMessages([
                'roster' => 'Configure a roster approval rule for this client space and title set before submitting this roster.',
            ]);
        }

        return DB::transaction(function () use ($roster, $user, $workflow) {
            $request = HrApprovalRequest::submitFromWorkflow($workflow, [
                'linked_roster_id' => $roster->id,
                'requester_staff_uuid' => $user->staff_uuid ?: 'system',
                'requester_name' => $this->resolveRequesterName($user),
                'subject' => sprintf(
                    '%s roster for %s (%s to %s)',
                    $roster->cadre_or_discipline,
                    $roster->organizationalUnit->name,
                    $roster->start_date->format('M j, Y'),
                    $roster->end_date->format('M j, Y')
                ),
                'details' => sprintf(
                    'Generated for %s in %s covering %d scheduled shift entries.',
                    $roster->cadre_or_discipline,
                    $roster->organizationalUnit->name,
                    $roster->entries()->count()
                ),
            ]);

            $roster->update([
                'approval_request_id' => $request->id,
                'approval_status' => HrDutyRoster::APPROVAL_PENDING,
                'submitted_at' => now(),
                'rejected_at' => null,
            ]);

            return $roster->fresh(['approvalRequest.steps', 'approvalRequest.events', 'entries.shiftType']);
        });
    }

    public function publishDirectly(HrDutyRoster $roster, User $user): HrDutyRoster
    {
        if (! $user->canManageAllApprovals()) {
            throw ValidationException::withMessages([
                'roster' => 'Only approval managers can publish a roster directly.',
            ]);
        }

        $this->assertCanManageRoster($user, $roster->organizationalUnit, $roster->disciplineTitles());
        $this->ensureRosterCanBeSubmitted($roster);

        $roster->update([
            'status' => HrDutyRoster::STATUS_PUBLISHED,
            'approval_status' => HrDutyRoster::APPROVAL_APPROVED,
            'approval_request_id' => null,
            'submitted_at' => $roster->submitted_at ?? now(),
            'published_at' => now(),
            'rejected_at' => null,
        ]);

        return $roster->fresh(['entries.shiftType', 'approvalRequests']);
    }

    public function archive(HrDutyRoster $roster, User $user): HrDutyRoster
    {
        if (! $user->canManageAllApprovals()) {
            throw ValidationException::withMessages([
                'roster' => 'Only approval managers can archive a roster.',
            ]);
        }

        $roster->update([
            'status' => HrDutyRoster::STATUS_ARCHIVED,
        ]);

        return $roster->fresh();
    }

    public function unpublish(HrDutyRoster $roster, User $user): HrDutyRoster
    {
        if (! $user->canManageAllApprovals()) {
            throw ValidationException::withMessages([
                'roster' => 'Only approval managers can unpublish a roster.',
            ]);
        }

        $this->assertCanManageRoster($user, $roster->organizationalUnit, $roster->disciplineTitles());

        if ($roster->status !== HrDutyRoster::STATUS_PUBLISHED) {
            throw ValidationException::withMessages([
                'roster' => 'Only published rosters can be unpublished.',
            ]);
        }

        if ($this->rosterHasAttendanceHistory($roster)) {
            throw ValidationException::withMessages([
                'roster' => 'This roster already has attendance linked to it and cannot be unpublished.',
            ]);
        }

        $roster->update([
            'status' => HrDutyRoster::STATUS_DRAFT,
            'approval_status' => HrDutyRoster::APPROVAL_NOT_SUBMITTED,
            'approval_request_id' => null,
            'submitted_at' => null,
            'published_at' => null,
            'rejected_at' => null,
        ]);

        return $roster->fresh(['entries.shiftType', 'approvalRequest.events', 'approvalRequests']);
    }

    public function deleteRoster(HrDutyRoster $roster, User $user): void
    {
        $this->assertCanManageRoster($user, $roster->organizationalUnit, $roster->disciplineTitles());

        if ($roster->status === HrDutyRoster::STATUS_PUBLISHED) {
            throw ValidationException::withMessages([
                'roster' => 'Published rosters must be unpublished before they can be deleted.',
            ]);
        }

        if ($roster->approval_status === HrDutyRoster::APPROVAL_PENDING) {
            throw ValidationException::withMessages([
                'roster' => 'Pending approval rosters cannot be deleted.',
            ]);
        }

        if ($this->rosterHasAttendanceHistory($roster)) {
            throw ValidationException::withMessages([
                'roster' => 'This roster already has attendance linked to it and cannot be deleted.',
            ]);
        }

        DB::transaction(function () use ($roster): void {
            $roster->entries()->delete();
            $roster->delete();
        });
    }

    public function deleteDraft(HrDutyRoster $roster, User $user): void
    {
        $this->deleteRoster($roster, $user);
    }

    private function validateHeaderPayload(Organization $organization, HrOrganizationalUnit $clientSpace, array $payload): array
    {
        $validated = Validator::make($payload, [
            'client_space_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'cadre_or_discipline' => 'nullable|string|max:255',
            'cadre_or_disciplines' => 'array',
            'cadre_or_disciplines.*' => 'string|max:255',
            'team_grouping_enabled' => 'nullable|boolean',
            'team_names' => 'array',
            'team_names.*' => 'nullable|string|max:120',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'entries' => 'array',
        ])->validate();

        if ((int) $validated['client_space_id'] !== (int) $clientSpace->id) {
            throw ValidationException::withMessages([
                'client_space_id' => 'The selected client space is invalid.',
            ]);
        }

        if (! $clientSpace->isClientSpace() || (int) $clientSpace->organization_id !== (int) $organization->id) {
            throw ValidationException::withMessages([
                'client_space_id' => 'Rosters can only be created inside a client space for the current organization.',
            ]);
        }

        $validated['cadre_or_disciplines'] = $this->cleanDisciplineList(
            $validated['cadre_or_disciplines'] ?? ($validated['cadre_or_discipline'] ? [$validated['cadre_or_discipline']] : [])
        );

        if ($validated['cadre_or_disciplines'] === []) {
            throw ValidationException::withMessages([
                'cadre_or_discipline' => 'Select at least one title for this roster.',
            ]);
        }

        $validated['cadre_or_discipline'] = $this->disciplineLabelFromList($validated['cadre_or_disciplines']);

        $eligibleAssignments = $this->eligibleAssignments($clientSpace, $validated['cadre_or_disciplines']);

        if ($eligibleAssignments->isEmpty()) {
            throw ValidationException::withMessages([
                'cadre_or_discipline' => 'No roster-eligible staff in this client space match the selected title set.',
            ]);
        }

        $validated['team_grouping_enabled'] = (bool) ($payload['team_grouping_enabled'] ?? false);
        $validated['team_names'] = $validated['team_grouping_enabled']
            ? $this->cleanTeamList($validated['team_names'] ?? [])
            : [];

        if ($validated['team_grouping_enabled'] && count($validated['team_names']) < 2) {
            throw ValidationException::withMessages([
                'team_names' => 'Add at least two team names before grouping roster staff by teams.',
            ]);
        }

        if ($validated['team_grouping_enabled'] && count($validated['team_names']) > $eligibleAssignments->count()) {
            throw ValidationException::withMessages([
                'team_names' => 'The number of teams cannot exceed the roster-eligible staff count for this client space and title set.',
            ]);
        }

        return $validated;
    }

    private function syncEntries(HrDutyRoster $roster, array $entries): void
    {
        $eligibleAssignments = $this->eligibleAssignments($roster->organizationalUnit, $roster->disciplineTitles())
            ->keyBy(fn (StaffAssignment $assignment): string => (string) $assignment->id);
        $shiftTypes = ShiftType::query()
            ->where('organization_id', $roster->organization_id)
            ->where('is_active', true)
            ->get()
            ->keyBy(fn (ShiftType $shiftType): string => (string) $shiftType->id);
        $startDate = Carbon::parse($roster->start_date)->startOfDay();
        $endDate = Carbon::parse($roster->end_date)->startOfDay();
        $payloads = [];
        $errors = [];

        foreach ($entries as $staffAssignmentId => $dateMap) {
            if (! is_array($dateMap)) {
                continue;
            }

            $assignment = $eligibleAssignments->get((string) $staffAssignmentId);

            if (! $assignment) {
                $errors['entries'][] = 'One or more roster rows reference staff who are no longer eligible for this client space and selected title set.';
                continue;
            }

            foreach ($dateMap as $date => $shiftTypeId) {
                if ($shiftTypeId === null || $shiftTypeId === '' || $shiftTypeId === false) {
                    continue;
                }

                try {
                    $rosterDate = Carbon::parse($date)->startOfDay();
                } catch (\Throwable) {
                    $errors['entries'][] = 'One or more roster dates are invalid.';
                    continue;
                }

                if ($rosterDate->lt($startDate) || $rosterDate->gt($endDate)) {
                    $errors['entries'][] = 'Roster entries must stay inside the selected date range.';
                    continue;
                }

                $shiftType = $shiftTypes->get((string) $shiftTypeId);

                if (! $shiftType) {
                    $errors['entries'][] = 'One or more selected shifts are inactive or invalid.';
                    continue;
                }

                $payloads[] = [
                    'organization_id' => $roster->organization_id,
                    'roster_date' => $rosterDate->toDateString(),
                    'staff_assignment_id' => $assignment->id,
                    'staff_uuid' => $assignment->staff_uuid,
                    'staff_name' => $assignment->staff_name,
                    'staff_cadre' => $assignment->staff_cadre,
                    'shift_type_id' => $shiftType->id,
                    'notes' => null,
                ];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $this->rosterPolicyValidator->validate($roster, $payloads);

        $roster->entries()->delete();

        if ($payloads !== []) {
            $roster->entries()->createMany($payloads);
        }

        if (
            $payloads !== []
            || $roster->status === HrDutyRoster::STATUS_PUBLISHED
        ) {
            app(OpenShiftService::class)->reconcileRoster($roster->fresh('organizationalUnit'));
        }
    }

    /**
     * @param array{chunk_index?: int, total_chunks?: int, chunk_start?: string|null, chunk_end?: string|null} $progress
     */
    private function updateGeminiGenerationProgress(
        int $rosterId,
        ?string $generationToken,
        int $attemptNumber,
        int $maxAttempts,
        array $progress
    ): void {
        if ($generationToken === null) {
            return;
        }

        $chunkIndex = max(1, (int) ($progress['chunk_index'] ?? 1));
        $totalChunks = max($chunkIndex, (int) ($progress['total_chunks'] ?? $chunkIndex));
        $chunkStart = filled($progress['chunk_start'] ?? null) ? (string) $progress['chunk_start'] : null;
        $chunkEnd = filled($progress['chunk_end'] ?? null) ? (string) $progress['chunk_end'] : null;
        $chunkRange = $chunkStart && $chunkEnd
            ? ($chunkStart === $chunkEnd ? $chunkStart : "{$chunkStart} to {$chunkEnd}")
            : null;

        $message = "Gemini is generating the roster draft. Attempt {$attemptNumber} of {$maxAttempts}. Processing chunk {$chunkIndex} of {$totalChunks}";

        if ($chunkRange) {
            $message .= " ({$chunkRange}).";
        } else {
            $message .= '.';
        }

        HrDutyRoster::query()
            ->whereKey($rosterId)
            ->where('ai_generation_token', $generationToken)
            ->where('ai_generation_source', HrDutyRoster::AI_GENERATION_SOURCE_GEMINI)
            ->whereIn('ai_generation_status', [
                HrDutyRoster::AI_GENERATION_QUEUED,
                HrDutyRoster::AI_GENERATION_RUNNING,
            ])
            ->update([
                'ai_generation_status' => HrDutyRoster::AI_GENERATION_RUNNING,
                'ai_generation_message' => $message,
                'ai_generation_attempts' => $attemptNumber,
                'ai_generation_heartbeat_at' => now(),
            ]);
    }

    /**
     * @param array<string, mixed> $validated
     * @param array<int, string> $teamAssignments
     * @param array<int|string, array<string, string>> $generatedEntries
     */
    private function persistGeneratedAiDraft(
        int $rosterId,
        array $validated,
        array $teamAssignments,
        array $generatedEntries,
        ?string $generationToken
    ): HrDutyRoster {
        return $this->runReconnectableTransaction(function () use (
            $rosterId,
            $validated,
            $teamAssignments,
            $generatedEntries,
            $generationToken
        ): HrDutyRoster {
            $roster = HrDutyRoster::query()->findOrFail($rosterId);

            if (
                $generationToken !== null
                && ! $this->isCurrentGeneration(
                    $roster,
                    $generationToken,
                    HrDutyRoster::AI_GENERATION_SOURCE_GEMINI
                )
            ) {
                return $roster->fresh(['entries.shiftType', 'approvalRequest.events', 'approvalRequests']);
            }

            $roster->update([
                'name' => $validated['name'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'team_definitions' => $validated['team_names'],
                'team_assignments' => $teamAssignments,
                'status' => HrDutyRoster::STATUS_DRAFT,
                'approval_status' => HrDutyRoster::APPROVAL_NOT_SUBMITTED,
                'approval_request_id' => null,
                'rejected_at' => null,
            ]);

            $freshRoster = $roster->fresh(['organization', 'organizationalUnit']);
            $this->syncEntries($freshRoster, $generatedEntries);

            return $roster->fresh(['entries.shiftType', 'approvalRequest.events', 'approvalRequests']);
        });
    }

    /**
     * @template TReturn
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function runReconnectableTransaction(callable $callback): mixed
    {
        $connection = DB::getDefaultConnection();

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                return DB::transaction($callback);
            } catch (Throwable $exception) {
                if ($attempt >= 2 || ! $this->wasCausedByLostDatabaseConnection($exception)) {
                    throw $exception;
                }

                DB::disconnect($connection);
                DB::purge($connection);
                DB::reconnect($connection);
            }
        }

        return DB::transaction($callback);
    }

    private function wasCausedByLostDatabaseConnection(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        foreach ([
            'server has gone away',
            'lost connection',
            'error while sending query packet',
            'no connection to the server',
            'is dead or not enabled',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function ensureRosterCanBeSubmitted(HrDutyRoster $roster): void
    {
        if ($roster->status === HrDutyRoster::STATUS_ARCHIVED) {
            throw ValidationException::withMessages([
                'roster' => 'Archived rosters cannot be submitted.',
            ]);
        }

        if ($roster->approval_status === HrDutyRoster::APPROVAL_PENDING) {
            throw ValidationException::withMessages([
                'roster' => 'This roster is already pending approval.',
            ]);
        }

        if (! $roster->entries()->exists()) {
            throw ValidationException::withMessages([
                'roster' => 'Add at least one scheduled shift before submitting this roster.',
            ]);
        }

        $eligibleAssignmentIds = $this->eligibleAssignments($roster->organizationalUnit, $roster->disciplineTitles())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $staleEntries = $roster->entries()
            ->whereNotNull('staff_assignment_id')
            ->whereNotIn('staff_assignment_id', $eligibleAssignmentIds)
            ->count();

        if ($staleEntries > 0) {
            throw ValidationException::withMessages([
                'roster' => 'Refresh this roster before submission because some assigned staff are no longer eligible in this client space and selected title set.',
            ]);
        }

        $this->rosterPolicyValidator->validatePersistedRoster($roster);

        $normalizedDisciplines = $this->normalizeDisciplineList($roster->disciplineTitles());

        $overlapExists = HrDutyRoster::query()
            ->where('organization_id', $roster->organization_id)
            ->where('organizational_unit_id', $roster->organizational_unit_id)
            ->whereKeyNot($roster->id)
            ->where(function ($query): void {
                $query
                    ->where('status', HrDutyRoster::STATUS_PUBLISHED)
                    ->orWhere('approval_status', HrDutyRoster::APPROVAL_PENDING);
            })
            ->whereDate('start_date', '<=', $roster->end_date)
            ->whereDate('end_date', '>=', $roster->start_date)
            ->get()
            ->contains(function (HrDutyRoster $candidate) use ($normalizedDisciplines): bool {
                return $this->disciplineListsIntersect($normalizedDisciplines, $candidate->disciplineTitles());
            });

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'roster' => 'Another published or pending roster already overlaps this client space, selected title set, and date range.',
            ]);
        }
    }

    private function rosterHasAttendanceHistory(HrDutyRoster $roster): bool
    {
        return HrAttendanceLedger::query()
            ->whereIn('roster_entry_id', HrDutyRosterEntry::withTrashed()
                ->where('duty_roster_id', $roster->id)
                ->select('id'))
            ->exists();
    }

    private function assertCanManageRoster(User $user, HrOrganizationalUnit $clientSpace, string|array|null $discipline): void
    {
        $disciplines = $this->cleanDisciplineList(is_array($discipline) ? $discipline : [$discipline]);

        if ($disciplines === []) {
            throw ValidationException::withMessages([
                'cadre_or_discipline' => 'A title is required for this roster.',
            ]);
        }

        if (count($disciplines) > 1 && ! $user->is_hr_admin && ! $user->canManageAllApprovals()) {
            throw ValidationException::withMessages([
                'roster' => 'Only HR managers can create or edit multi-title rosters.',
            ]);
        }

        if (
            $user->is_hr_admin
            || $user->canManageAllApprovals()
            || $this->authorizationService->canGenerateRoster($user, $clientSpace, $disciplines)
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'roster' => 'You are not authorized to manage this roster. Rosters can only be generated inside your client space and selected title set.',
        ]);
    }

    private function assertNoActiveAiGeneration(HrDutyRoster $roster): void
    {
        $roster = $this->reconcileAiGenerationState($roster);

        if (! $roster->hasActiveAiGeneration()) {
            return;
        }

        throw ValidationException::withMessages([
            'roster' => 'Gemini is still generating this roster. Wait for the AI run to finish before changing it.',
        ]);
    }

    private function assertGeminiConfigured(): void
    {
        if (filled((string) config('services.gemini.api_key', ''))) {
            return;
        }

        throw ValidationException::withMessages([
            'roster' => 'Set GEMINI_API_KEY before using Gemini roster generation.',
        ]);
    }

    private function aiGenerationHasExpired(HrDutyRoster $roster): bool
    {
        $lastHeartbeatAt = $roster->ai_generation_heartbeat_at ?: $roster->ai_generation_started_at;
        $staleAfterSeconds = max(300, (int) config('services.gemini.roster_stale_after_seconds', 7500));

        if (! $lastHeartbeatAt) {
            return false;
        }

        return $lastHeartbeatAt->lte(now()->subSeconds($staleAfterSeconds));
    }

    private function shouldQueueAutoDraftGeneration(HrDutyRoster $roster, array $validated): bool
    {
        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->startOfDay();
        $dateCount = $startDate->diffInDays($endDate) + 1;
        $eligibleAssignments = $this->eligibleAssignments($roster->organizationalUnit, $roster->disciplineTitles())->count();
        $shiftTypeCount = ShiftType::query()
            ->where('organization_id', $roster->organization_id)
            ->where('is_active', true)
            ->where('is_rosterable', true)
            ->count();
        $cellThreshold = max(1, (int) config('services.roster.auto_queue_cell_threshold', 180));

        return ($dateCount * max(1, $eligibleAssignments) * max(1, $shiftTypeCount)) >= $cellThreshold;
    }

    private function isCurrentGeneration(HrDutyRoster $roster, string $generationToken, string $generationSource): bool
    {
        return (string) $roster->ai_generation_token === $generationToken
            && $roster->hasActiveAiGeneration()
            && $roster->activeGenerationSource() === $generationSource;
    }

    private function canViewAllClientSpaces(User $user): bool
    {
        return $user->is_hr_admin
            || $user->canViewHrStaff()
            || $user->canViewHrSetup()
            || $user->canManageAllApprovals();
    }

    private function resolveRequesterName(User $user): string
    {
        if ($user->staff_uuid) {
            $assignment = StaffAssignment::query()
                ->where('staff_uuid', $user->staff_uuid)
                ->where('status', 'active')
                ->orderBy('id')
                ->first();

            if ($assignment?->staff_name) {
                return $assignment->staff_name;
            }
        }

        return $user->name;
    }

    private function rosterApprovalWorkflowScore(ApprovalWorkflow $workflow, array $normalizedDisciplines, int $clientSpaceId): int
    {
        $workflowClientSpaceId = $workflow->organizational_unit_id;

        if ($workflowClientSpaceId !== null && (int) $workflowClientSpaceId !== $clientSpaceId) {
            return 0;
        }

        $score = $workflowClientSpaceId === null ? 10 : 40;
        $workflowDiscipline = $this->normalizeDiscipline($workflow->discipline_title);

        if ($workflowDiscipline === '') {
            return $score + 1;
        }

        if (count($normalizedDisciplines) !== 1) {
            return 0;
        }

        return $workflowDiscipline === $normalizedDisciplines[0]
            ? $score + 5
            : 0;
    }

    private function rosterEligibleAssignments(HrOrganizationalUnit $clientSpace): Collection
    {
        $primaryAssignments = $clientSpace->staffAssignments()
            ->where('status', 'active')
            ->orderBy('staff_name')
            ->get()
            ->each(function (StaffAssignment $assignment): void {
                $assignment->setAttribute('client_space_assignment_type', HrClientSpaceStaffAssignment::TYPE_PRIMARY);
            });

        $secondaryAssignments = StaffAssignment::query()
            ->where('organization_id', $clientSpace->organization_id)
            ->whereNotIn('status', ['inactive', 'orphaned'])
            ->whereHas('clientSpaceStaffAssignments', function ($query) use ($clientSpace): void {
                $query
                    ->where('client_space_unit_id', $clientSpace->id)
                    ->where('assignment_type', HrClientSpaceStaffAssignment::TYPE_SECONDARY)
                    ->where('status', HrClientSpaceStaffAssignment::STATUS_ACTIVE);
            })
            ->orderBy('staff_name')
            ->get()
            ->each(function (StaffAssignment $assignment): void {
                $assignment->setAttribute('client_space_assignment_type', HrClientSpaceStaffAssignment::TYPE_SECONDARY);
            });

        return $primaryAssignments
            ->concat($secondaryAssignments)
            ->unique('id')
            ->sortBy('staff_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->pipe(fn (Collection $assignments): Collection => $this->hydrateAssignmentDisciplineMetadata($assignments))
            ->loadMissing('rosteringProfile.fixedShiftType');
    }

    /**
     * Fill missing title/department labels from linked users so roster title pickers
     * still work even before one-time backfills have run.
     *
     * @param Collection<int, StaffAssignment> $assignments
     */
    private function hydrateAssignmentDisciplineMetadata(Collection $assignments): Collection
    {
        $staffUuids = $assignments
            ->filter(fn (StaffAssignment $assignment): bool => blank($assignment->staff_title) || blank($assignment->staff_department))
            ->pluck('staff_uuid')
            ->filter()
            ->unique()
            ->values();

        if ($staffUuids->isEmpty()) {
            return $assignments;
        }

        $usersByStaffUuid = User::query()
            ->with([
                'title:id,name',
                'department:id,name',
            ])
            ->whereIn('staff_uuid', $staffUuids)
            ->orWhereIn('uuid', $staffUuids)
            ->get(['id', 'uuid', 'staff_uuid', 'title_id', 'department_id'])
            ->keyBy(fn (User $user): string => (string) ($user->staff_uuid ?: $user->uuid));

        if ($usersByStaffUuid->isEmpty()) {
            return $assignments;
        }

        return $assignments->each(function (StaffAssignment $assignment) use ($usersByStaffUuid): void {
            $user = $usersByStaffUuid->get((string) $assignment->staff_uuid);

            if (! $user) {
                return;
            }

            if (blank($assignment->staff_title) && filled($user->title?->name)) {
                $assignment->setAttribute('staff_title', $user->title->name);
            }

            if (blank($assignment->staff_department) && filled($user->department?->name)) {
                $assignment->setAttribute('staff_department', $user->department->name);
            }
        });
    }

    private function cleanDisciplineLabel(?string $discipline): string
    {
        return Str::of((string) $discipline)
            ->squish()
            ->trim()
            ->toString();
    }

    /**
     * @param array<int, string|null> $disciplines
     * @return array<int, string>
     */
    private function cleanDisciplineList(array $disciplines): array
    {
        return collect($disciplines)
            ->map(fn ($discipline): string => $this->cleanDisciplineLabel($discipline))
            ->filter(fn (string $discipline): bool => $discipline !== '')
            ->unique(fn (string $discipline): string => $this->normalizeDiscipline($discipline))
            ->values()
            ->all();
    }

    /**
     * @param array<int, string|null> $teams
     * @return array<int, string>
     */
    private function cleanTeamList(array $teams): array
    {
        return collect($teams)
            ->map(fn ($team): string => Str::of((string) $team)->squish()->trim()->toString())
            ->filter(fn (string $team): bool => $team !== '')
            ->unique(fn (string $team): string => Str::lower($team))
            ->values()
            ->all();
    }

    /**
     * @param string|array<int, string>|null $disciplines
     * @return array<int, string>
     */
    private function normalizeDisciplineList(string|array|null $disciplines): array
    {
        return collect(is_array($disciplines) ? $disciplines : [$disciplines])
            ->map(fn ($discipline): string => $this->normalizeDiscipline($discipline))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $disciplines
     */
    private function disciplineLabelFromList(array $disciplines): string
    {
        return implode(', ', $disciplines);
    }

    /**
     * @param Collection<int, StaffAssignment> $eligibleAssignments
     * @param array<int, string> $teamNames
     * @return array<string, string>
     */
    private function resolveTeamAssignments(?HrDutyRoster $roster, Collection $eligibleAssignments, array $teamNames): array
    {
        if ($teamNames === [] || $eligibleAssignments->isEmpty()) {
            return [];
        }

        $previousAssignments = $roster?->teamAssignments() ?? [];
        $resolvedAssignments = [];
        $teamCounts = array_fill_keys($teamNames, 0);
        $unassigned = [];

        foreach ($eligibleAssignments
            ->sortBy('staff_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values() as $assignment) {
            $staffKey = (string) $assignment->id;
            $teamName = $previousAssignments[$staffKey] ?? null;

            if ($teamName && in_array($teamName, $teamNames, true)) {
                $resolvedAssignments[$staffKey] = $teamName;
                $teamCounts[$teamName]++;
                continue;
            }

            $unassigned[] = $assignment;
        }

        foreach ($unassigned as $assignment) {
            $staffKey = (string) $assignment->id;
            $selectedTeam = collect($teamNames)
                ->sortBy(fn (string $team): array => [$teamCounts[$team] ?? 0, array_search($team, $teamNames, true)])
                ->first();

            if (! is_string($selectedTeam) || $selectedTeam === '') {
                continue;
            }

            $resolvedAssignments[$staffKey] = $selectedTeam;
            $teamCounts[$selectedTeam] = ($teamCounts[$selectedTeam] ?? 0) + 1;
        }

        return $resolvedAssignments;
    }

    /**
     * @param array<int, string> $left
     * @param array<int, string> $right
     */
    private function disciplineListsIntersect(array $left, array $right): bool
    {
        return collect($this->normalizeDisciplineList($left))
            ->intersect($this->normalizeDisciplineList($right))
            ->isNotEmpty();
    }

    private function normalizeDiscipline(?string $discipline): string
    {
        return Str::of((string) $discipline)
            ->squish()
            ->lower()
            ->toString();
    }

    private function disciplineResolver(): LocumDisciplineResolver
    {
        return app(LocumDisciplineResolver::class);
    }
}
