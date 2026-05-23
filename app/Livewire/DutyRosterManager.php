<?php

namespace App\Livewire;

use App\Models\HrCalendarEvent;
use App\Models\HrDutyRoster;
use App\Models\HrOrganizationalUnit;
use App\Models\HrStaffRosteringProfile;
use App\Models\HrStaffUnavailability;
use App\Models\Organization;
use App\Models\ShiftType;
use App\Models\User;
use App\Services\DutyRosterService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class DutyRosterManager extends Component
{
    private const DURATION_WEEKLY = 'weekly';
    private const DURATION_BIWEEKLY = 'biweekly';
    private const DURATION_MONTHLY = 'monthly';
    private const DURATION_CUSTOM = 'custom';

    public ?int $selectedClientSpaceId = null;
    public ?int $selectedRosterId = null;

    public bool $showCreateModal = false;
    public string $newRosterName = '';
    public string $newRosterDiscipline = '';
    public array $newRosterDisciplines = [];
    public bool $newRosterUsesTeams = false;
    public array $newRosterTeamNames = [];
    public string $newRosterDurationPreset = self::DURATION_MONTHLY;
    public ?string $newRosterStartDate = null;
    public ?string $newRosterEndDate = null;

    public string $editingName = '';
    public bool $editingUsesTeams = false;
    public array $editingTeamNames = [];
    public ?string $editingStartDate = null;
    public ?string $editingEndDate = null;
    public array $entrySelections = [];

    public ?string $message = null;
    public ?string $hydratedAiGenerationToken = null;
    private ?string $requestedClientSpaceUuid = null;

    public function mount(): void
    {
        $this->requestedClientSpaceUuid = request()->query('client_space');
    }

    public function updatedSelectedClientSpaceId(): void
    {
        $this->selectedRosterId = null;
        $this->editingName = '';
        $this->editingUsesTeams = false;
        $this->editingTeamNames = [];
        $this->editingStartDate = null;
        $this->editingEndDate = null;
        $this->entrySelections = [];
        $this->newRosterDisciplines = [];
        $this->newRosterUsesTeams = false;
        $this->newRosterTeamNames = [];
        $this->resetValidation();
    }

    public function openCreateModal(?string $discipline = null): void
    {
        $selectedClientSpace = $this->selectedClientSpace();

        abort_unless($selectedClientSpace, 404);

        $disciplineOptions = $this->dutyRosterService()->availableDisciplines($selectedClientSpace);
        $selectedDisciplines = $discipline
            ? [$discipline]
            : array_values(array_filter([(string) ($disciplineOptions->first()['label'] ?? '')]));

        $this->resetValidation();
        $this->newRosterDisciplines = $selectedDisciplines;
        $this->newRosterDiscipline = $selectedDisciplines[0] ?? '';
        $this->newRosterName = $this->defaultRosterName($selectedClientSpace, $selectedDisciplines);
        $this->newRosterUsesTeams = false;
        $this->newRosterTeamNames = [];
        $this->newRosterDurationPreset = self::DURATION_MONTHLY;
        $this->newRosterStartDate = now()->toDateString();
        $this->syncNewRosterEndDateToDuration();
        $this->showCreateModal = true;
    }

    public function updatedNewRosterDiscipline(string $value): void
    {
        $value = trim($value);
        $this->newRosterDisciplines = $value !== '' ? [$value] : [];
        $this->syncNewRosterNameToDisciplines();
    }

    public function updatedNewRosterDisciplines(): void
    {
        $this->newRosterDisciplines = collect($this->newRosterDisciplines)
            ->map(fn ($discipline): string => trim((string) $discipline))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $this->newRosterDiscipline = $this->newRosterDisciplines[0] ?? '';
        $this->syncNewRosterNameToDisciplines();
    }

    public function updatedNewRosterDurationPreset(string $value): void
    {
        if (! in_array($value, $this->durationPresetValues(), true)) {
            $this->newRosterDurationPreset = self::DURATION_MONTHLY;
        }

        if ($this->newRosterDurationPreset !== self::DURATION_CUSTOM) {
            $this->syncNewRosterEndDateToDuration();
        }
    }

    public function updatedNewRosterStartDate(?string $value): void
    {
        if ($this->newRosterDurationPreset !== self::DURATION_CUSTOM) {
            $this->syncNewRosterEndDateToDuration();
        }
    }

    public function updatedEntrySelections(): void
    {
        $this->hydratedAiGenerationToken = null;
    }

    public function updatedNewRosterUsesTeams(bool $value): void
    {
        if ($value && $this->newRosterTeamNames === []) {
            $this->newRosterTeamNames = ['Team A', 'Team B'];
        }

        if (! $value) {
            $this->newRosterTeamNames = [];
        }
    }

    public function updatedEditingUsesTeams(bool $value): void
    {
        if ($value && $this->editingTeamNames === []) {
            $this->editingTeamNames = ['Team A', 'Team B'];
        }

        if (! $value) {
            $this->editingTeamNames = [];
        }
    }

    public function addNewRosterTeam(): void
    {
        $this->newRosterUsesTeams = true;
        $this->newRosterTeamNames[] = '';
    }

    public function removeNewRosterTeam(int $index): void
    {
        unset($this->newRosterTeamNames[$index]);
        $this->newRosterTeamNames = array_values($this->newRosterTeamNames);
    }

    public function addEditingTeam(): void
    {
        $this->editingUsesTeams = true;
        $this->editingTeamNames[] = '';
    }

    public function removeEditingTeam(int $index): void
    {
        unset($this->editingTeamNames[$index]);
        $this->editingTeamNames = array_values($this->editingTeamNames);
    }

    public function createRoster(): void
    {
        $organization = Organization::current();
        $user = Auth::user();
        $selectedClientSpace = $this->selectedClientSpace();

        abort_unless($organization && $user instanceof User && $selectedClientSpace, 404);

        try {
            $roster = $this->dutyRosterService()->createDraft($organization, $selectedClientSpace, $user, [
                'client_space_id' => $selectedClientSpace->id,
                'name' => $this->newRosterName,
                'cadre_or_discipline' => $this->newRosterDiscipline,
                'cadre_or_disciplines' => $this->newRosterDisciplines,
                'team_grouping_enabled' => $this->newRosterUsesTeams,
                'team_names' => $this->newRosterTeamNames,
                'start_date' => $this->newRosterStartDate,
                'end_date' => $this->newRosterEndDate,
                'entries' => [],
            ]);
        } catch (ValidationException $exception) {
            $this->forwardValidationErrors($exception);
            return;
        }

        $this->showCreateModal = false;
        $this->selectedRosterId = $roster->id;
        $this->hydrateSelectedRoster($roster);
        $this->message = 'Roster draft created.';
    }

    public function openRoster(int $rosterId): void
    {
        $roster = $this->findAccessibleRoster($rosterId);

        abort_unless($roster, 404);

        $this->selectedClientSpaceId = $roster->organizational_unit_id;
        $this->selectedRosterId = $roster->id;
        $this->hydrateSelectedRoster($roster);
    }

    public function saveRoster(): void
    {
        $roster = $this->selectedRoster();
        $user = Auth::user();

        abort_unless($roster && $user instanceof User, 404);

        try {
            $roster = $this->dutyRosterService()->saveDraft($roster, $user, [
                'client_space_id' => $roster->organizational_unit_id,
                'name' => $this->editingName,
                'team_grouping_enabled' => $this->editingUsesTeams,
                'team_names' => $this->editingTeamNames,
                'start_date' => $this->editingStartDate,
                'end_date' => $this->editingEndDate,
                'entries' => $this->filteredEntrySelections(),
            ]);
        } catch (ValidationException $exception) {
            $this->forwardValidationErrors($exception);
            return;
        }

        $this->hydrateSelectedRoster($roster);
        $this->message = 'Roster draft saved.';
    }

    public function generateRoster(): void
    {
        $roster = $this->selectedRoster();
        $user = Auth::user();

        abort_unless($roster && $user instanceof User, 404);

        $this->message = null;
        $this->resetValidation();
        $shouldReroll = $this->shouldRerollAutoDraft($roster);

        try {
            $roster = $this->dutyRosterService()->generateDraft($roster, $user, [
                'client_space_id' => $roster->organizational_unit_id,
                'name' => $this->editingName,
                'team_grouping_enabled' => $this->editingUsesTeams,
                'team_names' => $this->editingTeamNames,
                'start_date' => $this->editingStartDate,
                'end_date' => $this->editingEndDate,
                'entries' => $this->filteredEntrySelections(),
                'replace_existing_entries' => $shouldReroll,
                'variation_seed' => $shouldReroll ? (string) Str::uuid() : null,
            ]);
        } catch (ValidationException $exception) {
            $this->forwardValidationErrors($exception);
            return;
        }

        $this->hydrateSelectedRoster($roster);
        $this->message = $roster->hasActiveAutoGeneration()
            ? 'Automatic roster generation started.'
            : 'Roster draft generated automatically.';
    }

    public function generateRosterWithAi(): void
    {
        $roster = $this->selectedRoster();
        $user = Auth::user();

        abort_unless($roster && $user instanceof User, 404);

        $this->message = null;
        $this->resetValidation();

        try {
            $roster = $this->dutyRosterService()->startAiDraftGeneration($roster, $user, [
                'client_space_id' => $roster->organizational_unit_id,
                'name' => $this->editingName,
                'team_grouping_enabled' => $this->editingUsesTeams,
                'team_names' => $this->editingTeamNames,
                'start_date' => $this->editingStartDate,
                'end_date' => $this->editingEndDate,
                'entries' => $this->filteredEntrySelections(),
            ]);
        } catch (ValidationException $exception) {
            $this->forwardValidationErrors($exception);
            return;
        }

        $this->hydrateSelectedRoster($roster);
        $this->message = 'Gemini roster generation started.';
    }

    public function refreshAiGeneration(): void
    {
        $roster = $this->selectedRoster();

        if (! $roster) {
            return;
        }

        if ($roster->hasCompletedAiGeneration()) {
            $this->hydrateSelectedRoster($roster);
            $this->message = $roster->activeGenerationSource() === HrDutyRoster::AI_GENERATION_SOURCE_AUTO
                ? 'Automatic roster draft generated.'
                : 'New Gemini roster draft generated.';
            return;
        }

        if ($roster->ai_generation_status === HrDutyRoster::AI_GENERATION_FAILED) {
            $this->hydrateSelectedRoster($roster);
            $this->message = $roster->ai_generation_message ?: 'Gemini roster generation failed.';
        }
    }

    public function cancelAiGeneration(): void
    {
        $roster = $this->selectedRoster();
        $user = Auth::user();

        abort_unless($roster && $user instanceof User, 404);

        try {
            $roster = $this->dutyRosterService()->cancelAiGeneration($roster, $user);
        } catch (ValidationException $exception) {
            $this->forwardValidationErrors($exception);
            return;
        }

        $this->hydrateSelectedRoster($roster);
        $this->message = $roster->activeGenerationSource() === HrDutyRoster::AI_GENERATION_SOURCE_AUTO
            ? 'Automatic roster generation stopped.'
            : 'Gemini roster generation stopped.';
    }

    public function submitRoster(): void
    {
        $roster = $this->selectedRoster();
        $user = Auth::user();

        abort_unless($roster && $user instanceof User, 404);

        try {
            $roster = $this->dutyRosterService()->submitForApproval($roster, $user);
        } catch (ValidationException $exception) {
            $this->forwardValidationErrors($exception);
            return;
        }

        $this->hydrateSelectedRoster($roster);
        $this->message = $user->canManageAllApprovals()
            ? 'Roster published.'
            : 'Roster submitted for approval.';
    }

    public function archiveRoster(): void
    {
        $roster = $this->selectedRoster();
        $user = Auth::user();

        abort_unless($roster && $user instanceof User, 404);

        try {
            $roster = $this->dutyRosterService()->archive($roster, $user);
        } catch (ValidationException $exception) {
            $this->forwardValidationErrors($exception);
            return;
        }

        $this->hydrateSelectedRoster($roster);
        $this->message = 'Roster archived.';
    }

    public function unpublishRoster(): void
    {
        $roster = $this->selectedRoster();
        $user = Auth::user();

        abort_unless($roster && $user instanceof User, 404);

        try {
            $roster = $this->dutyRosterService()->unpublish($roster, $user);
        } catch (ValidationException $exception) {
            $this->forwardValidationErrors($exception);
            return;
        }

        $this->hydrateSelectedRoster($roster);
        $this->message = 'Roster unpublished and returned to draft.';
    }

    public function deleteRoster(): void
    {
        $roster = $this->selectedRoster();
        $user = Auth::user();

        abort_unless($roster && $user instanceof User, 404);

        try {
            $this->dutyRosterService()->deleteRoster($roster, $user);
        } catch (ValidationException $exception) {
            $this->forwardValidationErrors($exception);
            return;
        }

        $this->resetSelectedRosterState();
        $this->message = 'Roster deleted.';
    }

    public function deleteRosterDraft(): void
    {
        $this->deleteRoster();
    }

    public function render(): View
    {
        $organization = Organization::current();
        $user = Auth::user();

        abort_unless($organization && $user instanceof User, 403);

        $clientSpaces = $this->dutyRosterService()->accessibleClientSpaces($user, $organization);
        $canSeeRosterModule = $this->canSeeRosterModule($user);

        abort_if($clientSpaces->isEmpty() && ! $canSeeRosterModule, 403);

        if ($clientSpaces->isNotEmpty()) {
            $this->initializeSelectedClientSpace($clientSpaces);
        } else {
            $this->selectedClientSpaceId = null;
        }

        $selectedClientSpace = $clientSpaces->isNotEmpty()
            ? $this->selectedClientSpace($clientSpaces)
            : null;
        $disciplineOptions = $selectedClientSpace
            ? $this->dutyRosterService()->availableDisciplines($selectedClientSpace)
            : collect();
        $rosters = $clientSpaces->isNotEmpty()
            ? HrDutyRoster::query()
                ->where('organization_id', $organization->id)
                ->whereIn('organizational_unit_id', $clientSpaces->pluck('id'))
                ->when($selectedClientSpace, fn ($query) => $query->where('organizational_unit_id', $selectedClientSpace->id))
                ->with(['organizationalUnit', 'approvalRequest'])
                ->orderByDesc('start_date')
                ->orderBy('name')
                ->get()
            : collect();
        $rosters = $rosters->map(fn (HrDutyRoster $roster): HrDutyRoster => $this->dutyRosterService()->reconcileAiGenerationState($roster));

        if ($this->selectedRosterId && ! $rosters->contains('id', $this->selectedRosterId)) {
            $this->resetSelectedRosterState();
        }

        $selectedRoster = $this->selectedRoster();

        if ($selectedRoster && $this->editingName === '') {
            $this->hydrateSelectedRoster($selectedRoster);
        }

        $shiftTypes = ShiftType::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $editorDates = $selectedRoster
            ? $this->dateRange($this->editingStartDate ?: $selectedRoster->start_date?->toDateString(), $this->editingEndDate ?: $selectedRoster->end_date?->toDateString())
            : [];
        $rosterEvents = $selectedRoster
            ? HrCalendarEvent::query()
                ->forOrganization($organization)
                ->active()
                ->approved()
                ->where('affects_rosters', true)
                ->whereDate('starts_on', '<=', $this->editingEndDate ?: $selectedRoster->end_date)
                ->whereDate('ends_on', '>=', $this->editingStartDate ?: $selectedRoster->start_date)
                ->orderBy('starts_on')
                ->get()
            : collect();
        $staffRows = ($selectedClientSpace && $selectedRoster)
            ? $this->dutyRosterService()->eligibleAssignments($selectedClientSpace, $selectedRoster->disciplineTitles())
            : collect();
        $staffScheduledHours = $this->staffScheduledHours($staffRows, $shiftTypes, $editorDates);
        $staffUiContext = $this->staffUiContext($organization, $staffRows, $shiftTypes, $editorDates);
        $resolvedApprovalWorkflow = ($selectedClientSpace && $selectedRoster && ! $selectedRoster->approvalRequest)
            ? $this->dutyRosterService()->previewRosterApprovalWorkflow($selectedClientSpace, $selectedRoster->disciplineTitles())
            : null;

        return view('livewire.duty-roster-manager', [
            'clientSpaces' => $clientSpaces,
            'selectedClientSpace' => $selectedClientSpace,
            'disciplineOptions' => $disciplineOptions,
            'rosters' => $rosters,
            'selectedRoster' => $selectedRoster,
            'shiftTypes' => $shiftTypes,
            'editorDates' => $editorDates,
            'rosterEvents' => $rosterEvents,
            'staffRows' => $staffRows,
            'staffScheduledHours' => $staffScheduledHours,
            'staffUiContext' => $staffUiContext,
            'resolvedApprovalWorkflow' => $resolvedApprovalWorkflow,
            'canDirectPublish' => $user->canManageAllApprovals(),
            'canArchiveRosters' => $user->canManageAllApprovals(),
        ]);
    }

    public function rosterStatusLabel(HrDutyRoster $roster): string
    {
        if ($roster->status === HrDutyRoster::STATUS_ARCHIVED) {
            return 'Archived';
        }

        if ($roster->status === HrDutyRoster::STATUS_PUBLISHED) {
            return 'Published';
        }

        if ($roster->hasActiveAiGeneration()) {
            return $roster->activeGenerationSource() === HrDutyRoster::AI_GENERATION_SOURCE_AUTO
                ? 'Generating'
                : 'Gemini Running';
        }

        if ($roster->ai_generation_status === HrDutyRoster::AI_GENERATION_FAILED) {
            return $roster->activeGenerationSource() === HrDutyRoster::AI_GENERATION_SOURCE_AUTO
                ? 'Generation Failed'
                : 'Gemini Failed';
        }

        return match ($roster->approval_status) {
            HrDutyRoster::APPROVAL_PENDING => 'Pending Approval',
            HrDutyRoster::APPROVAL_REJECTED => 'Rejected',
            default => 'Draft',
        };
    }

    public function rosterStatusClasses(HrDutyRoster $roster): string
    {
        if ($roster->status === HrDutyRoster::STATUS_ARCHIVED) {
            return 'bg-gray-100 text-gray-700';
        }

        if ($roster->status === HrDutyRoster::STATUS_PUBLISHED) {
            return 'bg-green-100 text-green-800';
        }

        if ($roster->hasActiveAiGeneration()) {
            return 'bg-blue-100 text-blue-800';
        }

        if ($roster->ai_generation_status === HrDutyRoster::AI_GENERATION_FAILED) {
            return 'bg-rose-100 text-rose-800';
        }

        return match ($roster->approval_status) {
            HrDutyRoster::APPROVAL_PENDING => 'bg-amber-100 text-amber-800',
            HrDutyRoster::APPROVAL_REJECTED => 'bg-rose-100 text-rose-800',
            default => 'bg-blue-100 text-blue-800',
        };
    }

    public function generationHeartbeatMessage(HrDutyRoster $roster): ?string
    {
        if (! $roster->hasActiveAiGeneration()) {
            return null;
        }

        $heartbeatAt = $roster->ai_generation_heartbeat_at ?: $roster->ai_generation_started_at;

        if (! $heartbeatAt) {
            return 'No worker heartbeat has been recorded for this run yet.';
        }

        $secondsSinceHeartbeat = max(0, $heartbeatAt->diffInSeconds(now()));
        $elapsed = $this->formatElapsedSeconds($secondsSinceHeartbeat);

        if ($roster->activeGenerationSource() === HrDutyRoster::AI_GENERATION_SOURCE_GEMINI) {
            $chunkTimeoutSeconds = max(1, (int) config('services.gemini.timeout', 120));

            if ($secondsSinceHeartbeat > $chunkTimeoutSeconds) {
                return "Last confirmed worker activity was {$elapsed} ago. This exceeds the {$chunkTimeoutSeconds}s Gemini request window, so the job may be stuck until the stale-run timeout marks it failed.";
            }

            return "Last confirmed worker activity was {$elapsed} ago. A single Gemini chunk can stay in flight for up to {$chunkTimeoutSeconds}s before the request times out.";
        }

        return "Last confirmed worker activity was {$elapsed} ago.";
    }

    public function generationHeartbeatClasses(HrDutyRoster $roster): string
    {
        if (! $roster->hasActiveAiGeneration()) {
            return 'text-gray-500';
        }

        $heartbeatAt = $roster->ai_generation_heartbeat_at ?: $roster->ai_generation_started_at;

        if (! $heartbeatAt) {
            return 'text-amber-700';
        }

        $secondsSinceHeartbeat = max(0, $heartbeatAt->diffInSeconds(now()));

        if (
            $roster->activeGenerationSource() === HrDutyRoster::AI_GENERATION_SOURCE_GEMINI
            && $secondsSinceHeartbeat > max(1, (int) config('services.gemini.timeout', 120))
        ) {
            return 'text-amber-800';
        }

        return 'text-blue-700';
    }

    private function dutyRosterService(): DutyRosterService
    {
        return app(DutyRosterService::class);
    }

    private function initializeSelectedClientSpace(Collection $clientSpaces): void
    {
        if ($this->selectedClientSpaceId && $clientSpaces->contains('id', $this->selectedClientSpaceId)) {
            return;
        }

        if ($this->requestedClientSpaceUuid) {
            $requestedClientSpace = $clientSpaces->firstWhere('uuid', $this->requestedClientSpaceUuid);

            if ($requestedClientSpace) {
                $this->selectedClientSpaceId = $requestedClientSpace->id;
                $this->requestedClientSpaceUuid = null;
                return;
            }
        }

        $this->selectedClientSpaceId = $clientSpaces->first()?->id;
    }

    private function selectedClientSpace(?Collection $clientSpaces = null): ?HrOrganizationalUnit
    {
        $user = Auth::user();
        $organization = Organization::current();

        if (! $user instanceof User || ! $organization) {
            return null;
        }

        $clientSpaces ??= $this->dutyRosterService()->accessibleClientSpaces($user, $organization);

        return $clientSpaces->firstWhere('id', $this->selectedClientSpaceId);
    }

    private function selectedRoster(): ?HrDutyRoster
    {
        if (! $this->selectedRosterId) {
            return null;
        }

        $roster = $this->findAccessibleRoster($this->selectedRosterId);

        return $roster ? $this->dutyRosterService()->reconcileAiGenerationState($roster) : null;
    }

    private function findAccessibleRoster(int $rosterId): ?HrDutyRoster
    {
        $organization = Organization::current();
        $user = Auth::user();

        if (! $organization || ! $user instanceof User) {
            return null;
        }

        $clientSpaceIds = $this->dutyRosterService()
            ->accessibleClientSpaces($user, $organization)
            ->pluck('id');

        return HrDutyRoster::query()
            ->where('organization_id', $organization->id)
            ->whereIn('organizational_unit_id', $clientSpaceIds)
            ->with([
                'organizationalUnit',
                'entries.shiftType',
                'approvalRequest.steps',
                'approvalRequest.events',
                'approvalRequests',
            ])
            ->find($rosterId);
    }

    private function resetSelectedRosterState(): void
    {
        $this->selectedRosterId = null;
        $this->editingName = '';
        $this->editingUsesTeams = false;
        $this->editingTeamNames = [];
        $this->editingStartDate = null;
        $this->editingEndDate = null;
        $this->entrySelections = [];
        $this->hydratedAiGenerationToken = null;
    }

    private function hydrateSelectedRoster(HrDutyRoster $roster): void
    {
        $this->selectedClientSpaceId = $roster->organizational_unit_id;
        $this->selectedRosterId = $roster->id;
        $this->editingName = $roster->name;
        $this->editingUsesTeams = $roster->usesTeams();
        $this->editingTeamNames = $roster->teamNames();
        $this->editingStartDate = $roster->start_date?->toDateString();
        $this->editingEndDate = $roster->end_date?->toDateString();
        $this->hydratedAiGenerationToken = $roster->hasCompletedAiGeneration()
            ? $roster->ai_generation_token
            : null;
        $this->entrySelections = [];

        foreach ($roster->entries as $entry) {
            if (! $entry->staff_assignment_id) {
                continue;
            }

            $this->entrySelections[$entry->staff_assignment_id][$entry->roster_date->toDateString()] = (string) $entry->shift_type_id;
        }
    }

    private function shouldRerollAutoDraft(HrDutyRoster $roster): bool
    {
        return filled($this->hydratedAiGenerationToken)
            && (string) $this->hydratedAiGenerationToken === (string) $roster->ai_generation_token
            && $roster->hasCompletedAiGeneration()
            && $roster->activeGenerationSource() === HrDutyRoster::AI_GENERATION_SOURCE_AUTO;
    }

    private function defaultRosterName(HrOrganizationalUnit $clientSpace, string|array $discipline): string
    {
        $disciplines = is_array($discipline)
            ? collect($discipline)->map(fn ($label): string => trim((string) $label))->filter()->values()->all()
            : [trim($discipline)];

        if ($disciplines === [] || ($disciplines[0] ?? '') === '') {
            return '';
        }

        return sprintf('%s Duty Roster - %s', implode(', ', $disciplines), $clientSpace->name);
    }

    private function syncNewRosterNameToDisciplines(): void
    {
        $selectedClientSpace = $this->selectedClientSpace();

        if (! $selectedClientSpace) {
            return;
        }

        $this->newRosterName = $this->defaultRosterName($selectedClientSpace, $this->newRosterDisciplines);
    }

    /**
     * @return array<int, string>
     */
    private function durationPresetValues(): array
    {
        return [
            self::DURATION_WEEKLY,
            self::DURATION_BIWEEKLY,
            self::DURATION_MONTHLY,
            self::DURATION_CUSTOM,
        ];
    }

    private function syncNewRosterEndDateToDuration(): void
    {
        $startDate = $this->newRosterStartDate;

        if (! $startDate) {
            return;
        }

        $calculatedEndDate = $this->calculatedEndDateForDuration($startDate, $this->newRosterDurationPreset);

        if ($calculatedEndDate !== null) {
            $this->newRosterEndDate = $calculatedEndDate;
        }
    }

    private function calculatedEndDateForDuration(string $startDate, string $durationPreset): ?string
    {
        try {
            $start = Carbon::parse($startDate)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return match ($durationPreset) {
            self::DURATION_WEEKLY => $start->copy()->addDays(6)->toDateString(),
            self::DURATION_BIWEEKLY => $start->copy()->addDays(13)->toDateString(),
            self::DURATION_MONTHLY => $start->copy()->addMonthNoOverflow()->subDay()->toDateString(),
            default => null,
        };
    }

    /**
     * @return array<int|string, array<string, string>>
     */
    private function filteredEntrySelections(): array
    {
        $validDates = collect($this->dateRange($this->editingStartDate, $this->editingEndDate))
            ->mapWithKeys(fn (\Carbon\Carbon $date): array => [$date->toDateString() => true]);

        if ($validDates->isEmpty()) {
            return [];
        }

        $filtered = [];

        foreach ($this->entrySelections as $staffId => $dateMap) {
            if (! is_array($dateMap)) {
                continue;
            }

            foreach ($dateMap as $date => $shiftTypeId) {
                $shiftValue = trim((string) $shiftTypeId);

                if ($shiftValue === '' || ! $validDates->has((string) $date)) {
                    continue;
                }

                $filtered[$staffId][(string) $date] = $shiftValue;
            }
        }

        return $filtered;
    }

    /**
     * @param Collection<int, \App\Models\StaffAssignment> $staffRows
     * @param Collection<int, ShiftType> $shiftTypes
     * @param array<int, \Carbon\Carbon> $editorDates
     * @return array<int|string, string>
     */
    private function staffScheduledHours(Collection $staffRows, Collection $shiftTypes, array $editorDates): array
    {
        $shiftMinutes = $shiftTypes
            ->mapWithKeys(fn (ShiftType $shiftType): array => [(string) $shiftType->id => $shiftType->effectiveNetMinutes()]);
        $validDates = collect($editorDates)
            ->mapWithKeys(fn (\Carbon\Carbon $date): array => [$date->toDateString() => true]);
        $hours = [];

        foreach ($staffRows as $staffAssignment) {
            $scheduledMinutes = 0;

            foreach (($this->entrySelections[$staffAssignment->id] ?? []) as $date => $shiftTypeId) {
                if (! $validDates->has((string) $date)) {
                    continue;
                }

                $scheduledMinutes += (int) ($shiftMinutes[(string) $shiftTypeId] ?? 0);
            }

            $hours[$staffAssignment->id] = $this->formatHours($scheduledMinutes);
        }

        return $hours;
    }

    private function formatHours(int $minutes): string
    {
        return number_format($minutes / 60, 1).'h';
    }

    private function formatElapsedSeconds(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }

        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return $remainingSeconds > 0
                ? sprintf('%dm %02ds', $minutes, $remainingSeconds)
                : sprintf('%dm', $minutes);
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return $remainingMinutes > 0
            ? sprintf('%dh %02dm', $hours, $remainingMinutes)
            : sprintf('%dh', $hours);
    }

    /**
     * @param Collection<int, \App\Models\StaffAssignment> $staffRows
     * @param Collection<int, ShiftType> $shiftTypes
     * @param array<int, \Carbon\Carbon> $editorDates
     * @return array<int, array{badges: array<int, array{label: string, class: string}>, profile_summary: string, date_statuses: array<string, array{status: string, label: string, cell_class: string, text_class: string}>}>
     */
    private function staffUiContext(Organization $organization, Collection $staffRows, Collection $shiftTypes, array $editorDates): array
    {
        if ($staffRows->isEmpty()) {
            return [];
        }

        $shiftTypesById = $shiftTypes->keyBy(fn (ShiftType $shiftType): string => (string) $shiftType->id);
        $unavailabilitiesByStaff = collect();

        if ($editorDates !== []) {
            $startDate = $editorDates[0]->toDateString();
            $endDate = $editorDates[array_key_last($editorDates)]->toDateString();

            $unavailabilitiesByStaff = HrStaffUnavailability::query()
                ->with('leaveType')
                ->where('organization_id', $organization->id)
                ->whereIn('staff_assignment_id', $staffRows->pluck('id'))
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
                ->orderBy('starts_on')
                ->get()
                ->groupBy('staff_assignment_id');
        }

        $context = [];

        foreach ($staffRows as $staffAssignment) {
            $profile = $staffAssignment->rosteringProfile;
            $badges = [];
            $profileSummary = $this->profileSummary($profile, $shiftTypesById);
            $dateStatuses = [];

            if ($profile && $profile->is_active) {
                $badges[] = [
                    'label' => $profile->usesFixedMode() ? 'Fixed mode' : 'Dynamic mode',
                    'class' => $profile->usesFixedMode()
                        ? 'bg-slate-100 text-slate-700'
                        : 'bg-emerald-100 text-emerald-700',
                ];

                if ($profile->fixedShiftType) {
                    $badges[] = [
                        'label' => 'Fixed shift '.$this->shiftLabel($profile->fixedShiftType),
                        'class' => 'bg-sky-100 text-sky-700',
                    ];
                }

                if ($profile->max_night_shifts_per_cycle !== null) {
                    $badges[] = [
                        'label' => 'Max '.$profile->max_night_shifts_per_cycle.' overnight shift(s)',
                        'class' => 'bg-violet-100 text-violet-700',
                    ];
                }
            } else {
                $badges[] = [
                    'label' => 'Dynamic mode',
                    'class' => 'bg-emerald-100 text-emerald-700',
                ];
            }

            foreach (($unavailabilitiesByStaff->get($staffAssignment->id) ?? collect()) as $unavailability) {
                $status = (string) $unavailability->status;
                $statusLabel = $unavailability->statusLabel();
                $badges[] = [
                    'label' => $statusLabel.' '.$this->unavailabilityRangeLabel($unavailability),
                    'class' => $this->unavailabilityBadgeClass($unavailability),
                ];

                $cursor = \Carbon\Carbon::parse($unavailability->starts_on)->startOfDay();
                $end = \Carbon\Carbon::parse($unavailability->ends_on ?: $unavailability->starts_on)->startOfDay();

                for (; $cursor->lte($end); $cursor->addDay()) {
                    $dateKey = $cursor->toDateString();
                    $current = $dateStatuses[$dateKey]['status'] ?? null;

                    if ($current === HrStaffUnavailability::STATUS_APPROVED) {
                        continue;
                    }

                    if ($status === HrStaffUnavailability::STATUS_APPROVED || $current === null) {
                        $dateStatuses[$dateKey] = $this->unavailabilityDateStatus($unavailability);
                    }
                }
            }

            $context[$staffAssignment->id] = [
                'badges' => $badges,
                'profile_summary' => $profileSummary,
                'date_statuses' => $dateStatuses,
            ];
        }

        return $context;
    }

    private function profileSummary(?HrStaffRosteringProfile $profile, Collection $shiftTypesById): string
    {
        if (! $profile || ! $profile->is_active) {
            return 'Dynamic rotation with no custom shift constraints.';
        }

        $parts = [];

        if ($profile->usesFixedMode() && $profile->fixedDays() !== []) {
            $parts[] = 'Fixed days: '.collect($profile->fixedDays())
                ->map(fn (int $day): string => $this->dayLabel($day))
                ->implode(', ');
        }

        $preferred = $this->shiftLabelsForIds($profile->preferredShiftIds(), $shiftTypesById);

        if ($preferred !== []) {
            $parts[] = 'Prefers '.implode(', ', $preferred);
        }

        $excluded = $this->shiftLabelsForIds($profile->excludedShiftIds(), $shiftTypesById);

        if ($excluded !== []) {
            $parts[] = 'Excludes '.implode(', ', $excluded);
        }

        if ($parts === []) {
            return $profile->usesFixedMode()
                ? 'Fixed mode with no extra shift filters.'
                : 'Dynamic rotation with no extra shift filters.';
        }

        return implode('. ', $parts).'.';
    }

    /**
     * @param array<int, int> $shiftIds
     * @return array<int, string>
     */
    private function shiftLabelsForIds(array $shiftIds, Collection $shiftTypesById): array
    {
        return collect($shiftIds)
            ->map(fn (int $shiftId): ?string => ($shiftType = $shiftTypesById->get((string) $shiftId))
                ? $this->shiftLabel($shiftType)
                : null)
            ->filter()
            ->values()
            ->all();
    }

    private function shiftLabel(ShiftType $shiftType): string
    {
        return $shiftType->code
            ? $shiftType->code.' - '.$shiftType->name
            : $shiftType->name;
    }

    private function dayLabel(int $day): string
    {
        return [
            0 => 'Sun',
            1 => 'Mon',
            2 => 'Tue',
            3 => 'Wed',
            4 => 'Thu',
            5 => 'Fri',
            6 => 'Sat',
        ][$day] ?? 'Day';
    }

    private function unavailabilityRangeLabel(HrStaffUnavailability $unavailability): string
    {
        $start = $unavailability->starts_on?->format('M j');
        $end = ($unavailability->ends_on ?: $unavailability->starts_on)?->format('M j');

        return $start === $end
            ? $start
            : $start.' to '.$end;
    }

    private function unavailabilityBadgeClass(HrStaffUnavailability $unavailability): string
    {
        if ($unavailability->allowsAttendanceBypass()) {
            return 'bg-sky-100 text-sky-700';
        }

        return $unavailability->status === HrStaffUnavailability::STATUS_APPROVED
            ? 'bg-rose-100 text-rose-700'
            : 'bg-amber-100 text-amber-700';
    }

    /**
     * @return array{status: string, label: string, cell_class: string, text_class: string}
     */
    private function unavailabilityDateStatus(HrStaffUnavailability $unavailability): array
    {
        if ($unavailability->allowsAttendanceBypass()) {
            return [
                'status' => (string) $unavailability->status,
                'label' => $unavailability->statusLabel(),
                'cell_class' => 'bg-sky-50',
                'text_class' => 'text-sky-700',
            ];
        }

        return [
            'status' => (string) $unavailability->status,
            'label' => $unavailability->statusLabel(),
            'cell_class' => $unavailability->status === HrStaffUnavailability::STATUS_APPROVED ? 'bg-rose-50' : 'bg-amber-50',
            'text_class' => $unavailability->status === HrStaffUnavailability::STATUS_APPROVED ? 'text-rose-700' : 'text-amber-700',
        ];
    }

    private function dateRange(?string $startDate, ?string $endDate): array
    {
        if (! $startDate || ! $endDate) {
            return [];
        }

        try {
            $start = \Carbon\Carbon::parse($startDate)->startOfDay();
            $end = \Carbon\Carbon::parse($endDate)->startOfDay();
        } catch (\Throwable) {
            return [];
        }

        if ($end->lt($start)) {
            return [];
        }

        $dates = [];

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $dates[] = $cursor->copy();
        }

        return $dates;
    }

    private function forwardValidationErrors(ValidationException $exception): void
    {
        foreach ($exception->errors() as $field => $messages) {
            foreach ((array) $messages as $message) {
                $this->addError($field, $message);
            }
        }
    }

    private function canSeeRosterModule(User $user): bool
    {
        return $user->is_hr_admin
            || $user->canViewHrStaff()
            || $user->canViewHrSetup()
            || $user->canManageAllApprovals();
    }
}
