<div @if(($selectedRoster ?? null)?->hasActiveAiGeneration()) wire:poll.5s="refreshAiGeneration" @endif>
    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="text-2xl font-semibold text-gray-900">Duty Rosters</h2>
            <p class="mt-1 text-sm text-gray-500">Build single-title or multi-title rosters inside a shared client space, then submit them for approval or publish them directly if you manage approvals.</p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="min-w-[18rem]">
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Client Space</label>
                <select wire:model.live="selectedClientSpaceId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    @if($clientSpaces->isEmpty())
                        <option value="">No client spaces available</option>
                    @endif
                    @foreach($clientSpaces as $clientSpace)
                        <option value="{{ $clientSpace->id }}">{{ $clientSpace->name }} ({{ (int) $clientSpace->active_staff_count + (int) ($clientSpace->secondary_staff_count ?? 0) }} active staff)</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-[20rem]">
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Existing Roster</label>
                <select wire:model.live="selectedRosterId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    <option value="">Select a roster</option>
                    @foreach($rosters as $roster)
                        <option value="{{ $roster->id }}">
                            {{ $roster->name }} | {{ $roster->cadre_or_discipline }} | {{ $this->rosterStatusLabel($roster) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="button" wire:click="openCreateModal" @disabled($clientSpaces->isEmpty()) class="inline-flex items-center justify-center rounded-md border border-transparent bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50">
                New Roster
            </button>
        </div>
    </div>

    @if($message)
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ $message }}
        </div>
    @endif

    @error('roster')
        <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ $message }}
        </div>
    @enderror
    @error('entries')
        <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ $message }}
        </div>
    @enderror

    @if($clientSpaces->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-10 text-center">
            <h3 class="text-lg font-semibold text-gray-900">No client spaces available yet</h3>
            <p class="mt-2 text-sm text-gray-500">Import or configure client spaces first, then come back here to build duty rosters for each title.</p>
        </div>
    @else
    <div class="min-w-0">
            @if(!$selectedRoster)
                <div class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-10 text-center">
                    <h3 class="text-lg font-semibold text-gray-900">Open or create a roster</h3>
                    <p class="mt-2 text-sm text-gray-500">Pick a roster from the dropdown above or create a new one for the selected client space and title set.</p>
                </div>
            @else
                @php
                    $aiGenerationInProgress = $selectedRoster->hasActiveAiGeneration();
                    $autoGenerationInProgress = $selectedRoster->hasActiveAutoGeneration();
                    $generationRunningLabel = $autoGenerationInProgress ? 'Generating...' : 'Gemini Running...';
                    $stopGenerationLabel = $autoGenerationInProgress ? 'Stop Generation' : 'Stop Gemini';
                    $stopGenerationConfirm = $autoGenerationInProgress
                        ? 'Stop automatic roster generation for this draft?'
                        : 'Stop Gemini roster generation for this draft?';
                    $deleteConfirm = $autoGenerationInProgress
                        ? 'Delete this roster and stop automatic generation?'
                        : ($aiGenerationInProgress ? 'Delete this roster and stop Gemini?' : 'Delete this roster?');
                    $generationStatusTitle = $selectedRoster->ai_generation_status === \App\Models\HrDutyRoster::AI_GENERATION_COMPLETED
                        ? ($selectedRoster->activeGenerationSource() === \App\Models\HrDutyRoster::AI_GENERATION_SOURCE_AUTO ? 'Automatic generation complete' : 'Gemini complete')
                        : ($selectedRoster->ai_generation_status === \App\Models\HrDutyRoster::AI_GENERATION_FAILED
                            ? ($selectedRoster->activeGenerationSource() === \App\Models\HrDutyRoster::AI_GENERATION_SOURCE_AUTO ? 'Automatic generation failed' : 'Gemini failed')
                            : ($selectedRoster->activeGenerationSource() === \App\Models\HrDutyRoster::AI_GENERATION_SOURCE_AUTO ? 'Automatic generation running' : 'Gemini running'));
                @endphp
                @php
                    $activeTeamNames = $selectedRoster->isEditable()
                        ? collect($editingTeamNames)
                            ->map(fn ($team): string => trim((string) $team))
                            ->filter()
                            ->unique(fn (string $team): string => \Illuminate\Support\Str::lower($team))
                            ->values()
                            ->all()
                        : $selectedRoster->teamNames();
                    $teamsEnabledForView = $selectedRoster->isEditable()
                        ? $editingUsesTeams && $activeTeamNames !== []
                        : $selectedRoster->usesTeams();
                @endphp
                <div
                    class="rounded-lg border border-gray-200 bg-white"
                    x-data="{
                        aiDraftVisible: false,
                        aiDraftTitle: '',
                        aiDraftStep: '',
                        aiDraftElapsed: 0,
                        aiDraftProgress: 0,
                        aiDraftStartedAt: null,
                        aiDraftTimer: null,
                        startAiDraft() {
                            this.stopAiDraftTimer();
                            this.aiDraftVisible = true;
                            this.aiDraftTitle = 'Generating draft with Gemini';
                            this.aiDraftStep = 'Preparing roster data and shift constraints...';
                            this.aiDraftElapsed = 0;
                            this.aiDraftProgress = 8;
                            this.aiDraftStartedAt = Date.now();
                            this.aiDraftTimer = setInterval(() => {
                                const elapsed = Math.max(0, Math.floor((Date.now() - this.aiDraftStartedAt) / 1000));
                                this.aiDraftElapsed = elapsed;

                                if (elapsed >= 12) {
                                    this.aiDraftStep = 'Applying returned shift assignments to the draft...';
                                    this.aiDraftProgress = 88;
                                } else if (elapsed >= 6) {
                                    this.aiDraftStep = 'Waiting for Gemini to return shift assignments...';
                                    this.aiDraftProgress = 64;
                                } else if (elapsed >= 2) {
                                    this.aiDraftStep = 'Sending roster rules and staff context to Gemini...';
                                    this.aiDraftProgress = 32;
                                }
                            }, 250);
                        },
                        stopAiDraftTimer() {
                            if (this.aiDraftTimer) {
                                clearInterval(this.aiDraftTimer);
                                this.aiDraftTimer = null;
                            }
                        },
                        finishAiDraft(detail) {
                            this.stopAiDraftTimer();
                            if (this.aiDraftStartedAt !== null) {
                                this.aiDraftElapsed = Math.max(this.aiDraftElapsed, Math.floor((Date.now() - this.aiDraftStartedAt) / 1000));
                            }

                            const generated = Number(detail?.generated ?? 0);
                            const status = detail?.status ?? 'completed';
                            const message = detail?.message ?? '';

                            this.aiDraftVisible = true;
                            this.aiDraftProgress = 100;

                            if (status === 'failed') {
                                this.aiDraftTitle = 'Gemini generation failed';
                                this.aiDraftStep = message !== '' ? message : 'The roster draft could not be generated.';
                            } else if (generated > 0) {
                                this.aiDraftTitle = 'Gemini draft ready';
                                this.aiDraftStep = generated === 1
                                    ? '1 shift assignment was applied to the draft.'
                                    : generated + ' shift assignments were applied to the draft.';
                            } else {
                                this.aiDraftTitle = 'Gemini returned no assignments';
                                this.aiDraftStep = message !== '' ? message : 'Gemini finished, but the draft is still empty.';
                            }

                            setTimeout(() => {
                                this.aiDraftVisible = false;
                            }, status === 'failed' || generated === 0 ? 9000 : 5000);
                        }
                    }"
                    x-on:roster-ai-request-finished.window="finishAiDraft($event.detail)"
                >
                    <div class="border-b border-gray-200 px-6 py-5">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-lg font-semibold text-gray-900">{{ $selectedRoster->name }}</h3>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $this->rosterStatusClasses($selectedRoster) }}">
                                        {{ $this->rosterStatusLabel($selectedRoster) }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-gray-500">{{ $selectedRoster->organizationalUnit->name }} / {{ $selectedRoster->cadre_or_discipline }}</p>
                                @if($teamsEnabledForView)
                                    <p class="mt-2 text-xs text-gray-500">Teams: {{ implode(', ', $activeTeamNames) }}</p>
                                @endif
                                @if($selectedRoster->approvalRequest)
                                    <p class="mt-2 text-xs text-gray-500">
                                        Current approval request: {{ $selectedRoster->approvalRequest->subject }}
                                    </p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                @if($aiGenerationInProgress)
                                    <button type="button" disabled class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-3 py-2 text-sm font-semibold text-white opacity-70">
                                        {{ $generationRunningLabel }}
                                    </button>
                                    <button type="button" wire:click="cancelAiGeneration" wire:confirm="{{ $stopGenerationConfirm }}" class="inline-flex items-center justify-center rounded-md border border-amber-300 bg-white px-3 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-50">
                                        {{ $stopGenerationLabel }}
                                    </button>
                                @elseif($selectedRoster->isEditable())
                                    <button type="button" x-on:click="startAiDraft()" wire:click="generateRosterWithAi" wire:loading.attr="disabled" wire:target="generateRosterWithAi" class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                                        <span wire:loading.remove wire:target="generateRosterWithAi">Auto-Generate</span>
                                        <span wire:loading wire:target="generateRosterWithAi">Generating...</span>
                                    </button>
                                    <button type="button" wire:click="saveRoster" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                        Save Draft
                                    </button>
                                    <button type="button" wire:click="submitRoster" class="inline-flex items-center justify-center rounded-md border border-transparent bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                                        {{ $canDirectPublish ? 'Publish Now' : 'Submit for Approval' }}
                                    </button>
                                @endif

                                @if($canArchiveRosters && $selectedRoster->status === \App\Models\HrDutyRoster::STATUS_PUBLISHED)
                                    <button type="button" wire:click="unpublishRoster" wire:confirm="Unpublish this roster and return it to draft?" class="inline-flex items-center justify-center rounded-md border border-amber-300 bg-white px-3 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-50">
                                        Unpublish
                                    </button>
                                    <button type="button" wire:click="archiveRoster" wire:confirm="Archive this published roster?" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                        Archive
                                    </button>
                                @endif

                                @if($selectedRoster->status !== \App\Models\HrDutyRoster::STATUS_PUBLISHED && $selectedRoster->approval_status !== \App\Models\HrDutyRoster::APPROVAL_PENDING)
                                    <button type="button" wire:click="deleteRoster" wire:confirm="{{ $deleteConfirm }}" class="inline-flex items-center justify-center rounded-md border border-rose-300 bg-white px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                                        Delete Roster
                                    </button>
                                @endif
                            </div>

                            @if($selectedRoster->ai_generation_status)
                                <div class="mt-3 w-full rounded-md border {{ $selectedRoster->ai_generation_status === \App\Models\HrDutyRoster::AI_GENERATION_FAILED ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-blue-200 bg-blue-50 text-blue-800' }} px-3 py-2 text-sm">
                                    <p class="font-semibold">
                                        {{ $generationStatusTitle }}
                                    </p>
                                    @if($selectedRoster->ai_generation_message)
                                        <p class="mt-1 text-xs">{{ $selectedRoster->ai_generation_message }}</p>
                                    @endif
                                    @if($selectedRoster->hasActiveAiGeneration())
                                        <p class="mt-2 text-xs {{ $this->generationHeartbeatClasses($selectedRoster) }}">
                                            {{ $this->generationHeartbeatMessage($selectedRoster) }}
                                        </p>
                                    @endif
                                </div>
                            @endif

                            <div x-show="aiDraftVisible" class="mt-3 w-full rounded-md border border-sky-200 bg-sky-50 px-3 py-3 text-sm text-sky-900">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="font-semibold" x-text="aiDraftTitle"></p>
                                        <p class="mt-1 text-xs text-sky-800" x-text="aiDraftStep"></p>
                                    </div>
                                    <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-sky-700" x-text="aiDraftElapsed + 's'"></span>
                                </div>
                                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-sky-100">
                                    <div class="h-full rounded-full bg-sky-500 transition-all duration-500" :style="'width: ' + aiDraftProgress + '%'"></div>
                                </div>
                                <p class="mt-2 text-xs text-sky-800">
                                    Preparing roster data, asking Gemini for assignments, then applying the returned shifts to this draft.
                                </p>
                            </div>

                            @if($selectedRoster->isEditable())
                                <div class="mt-3 w-full space-y-2">
                                    <p wire:loading wire:target="generateRosterWithAi" class="text-xs font-medium text-blue-700">
                                        Gemini is generating shift assignments for this roster.
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Auto-generation uses Gemini and writes the returned shift assignments directly into this draft.
                                    </p>
                                    @error('roster')
                                        <div class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            @endif
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Roster Name</label>
                                <input type="text" wire:model="editingName" @disabled(!$selectedRoster->isEditable()) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                                @error('name') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <div class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Teams</label>
                                            <p class="mt-1 text-xs text-gray-500">Define team names and adjust staff-to-team assignments for this roster draft.</p>
                                        </div>
                                        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                                            <input type="checkbox" wire:model.live="editingUsesTeams" @disabled(!$selectedRoster->isEditable()) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:bg-gray-100">
                                            Group staff by teams
                                        </label>
                                    </div>

                                    @if($editingUsesTeams)
                                        <div class="mt-4 space-y-3">
                                            @foreach($editingTeamNames as $index => $teamName)
                                                <div class="flex items-center gap-2">
                                                    <input type="text" wire:model="editingTeamNames.{{ $index }}" @disabled(!$selectedRoster->isEditable()) class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm" placeholder="Team name">
                                                    @if($selectedRoster->isEditable())
                                                        <button type="button" wire:click="removeEditingTeam({{ $index }})" class="inline-flex items-center justify-center rounded-md border border-rose-200 bg-white px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                                            Remove
                                                        </button>
                                                    @endif
                                                </div>
                                            @endforeach

                                            @if($selectedRoster->isEditable())
                                                <button type="button" wire:click="addEditingTeam" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                    Add Team
                                                </button>
                                            @endif
                                        </div>
                                        @error('team_names') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                        @error('team_assignments') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                    @endif
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Start Date</label>
                                <input type="date" wire:model="editingStartDate" @disabled(!$selectedRoster->isEditable()) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                                <p class="mt-1 text-[11px] text-gray-500">Display guide: mm/dd/yyyy</p>
                                @error('start_date') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">End Date</label>
                                <input type="date" wire:model="editingEndDate" @disabled(!$selectedRoster->isEditable()) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                                <p class="mt-1 text-[11px] text-gray-500">Display guide: mm/dd/yyyy</p>
                                @error('end_date') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="mt-4 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                            Auto-generation and AI use the staff rows below, active shift types, leave and unavailability checks, roster policy validation, and any fixed or dynamic rostering settings. You can still change or clear any assignment manually before approval.
                        </div>
                    </div>

                    @if($shiftTypes->isEmpty())
                        <div class="mx-6 mt-5 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            Add at least one active shift type before filling this roster.
                        </div>
                    @endif

                    @if(!$selectedRoster->approvalRequest)
                        <div class="mx-6 mt-5 rounded-md border {{ $resolvedApprovalWorkflow ? 'border-sky-200 bg-sky-50' : 'border-amber-200 bg-amber-50' }} px-4 py-4">
                            <h4 class="text-sm font-semibold {{ $resolvedApprovalWorkflow ? 'text-sky-900' : 'text-amber-900' }}">Resolved Approval Flow</h4>
                            @if($resolvedApprovalWorkflow)
                                <p class="mt-1 text-sm {{ $resolvedApprovalWorkflow ? 'text-sky-800' : 'text-amber-800' }}">
                                    Using the same approver chain configured for leave in {{ $resolvedApprovalWorkflow->organizationalUnit?->name ?? 'this client space' }}.
                                </p>
                                <div class="mt-3 grid gap-3 md:grid-cols-3">
                                    @foreach($resolvedApprovalWorkflow->approvers as $approver)
                                        <div class="rounded-md border border-sky-200 bg-white px-3 py-3">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">{{ ucfirst($approver->approver_level) }}</p>
                                            <p class="mt-1 text-sm font-medium text-gray-900">{{ $approver->approver_name }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-1 text-sm text-amber-800">
                                    No synced leave or roster approver rule matches this client space yet. Configure leave approvers for this client space before submitting this roster.
                                </p>
                            @endif
                        </div>
                    @endif

                    @if($selectedRoster->approvalRequest)
                        <div class="mx-6 mt-5 rounded-md border border-gray-200 bg-gray-50 px-4 py-4">
                            <h4 class="text-sm font-semibold text-gray-900">Approval Flow</h4>
                            <div class="mt-3 grid gap-3 md:grid-cols-3">
                                @foreach($selectedRoster->approvalRequest->steps as $step)
                                    <div class="rounded-md border border-gray-200 bg-white px-3 py-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ucfirst($step->approver_level) }}</p>
                                        <p class="mt-1 text-sm font-medium text-gray-900">{{ $step->approver_name }}</p>
                                        <p class="mt-2 text-xs text-gray-500">{{ ucfirst($step->status) }}</p>
                                        @if($step->comments)
                                            <p class="mt-2 text-xs text-gray-500">{{ $step->comments }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="mt-5 overflow-x-auto border-t border-gray-200">
                        @if($staffRows->isEmpty())
                            <div class="px-6 py-8 text-sm text-gray-500">
                                No active staff remain in the selected title set for this client space.
                            </div>
                        @else
                            @php
                                $shiftTypesById = $shiftTypes->keyBy(fn ($shiftType): string => (string) $shiftType->id);
                                $periodStart = $editorDates[0] ?? $selectedRoster->start_date;
                                $monthLabel = $periodStart?->format('F') ?? 'Selected period';
                                $yearLabel = $periodStart?->format('Y') ?? '';
                                $statusLegend = [
                                    ['label' => 'Primary assignment', 'symbol' => 'P'],
                                    ['label' => 'Additional assignment', 'symbol' => 'A'],
                                    ['label' => 'Approved leave', 'symbol' => 'H'],
                                    ['label' => 'Off / Unassigned', 'symbol' => 'X'],
                                ];
                                $availableStaffIds = $staffRows
                                    ->pluck('id')
                                    ->mapWithKeys(fn ($id): array => [(string) $id => true]);
                                $activeTeamAssignments = $teamsEnabledForView
                                    ? collect($selectedRoster->isEditable() ? ($editingTeamAssignments ?? []) : $selectedRoster->teamAssignments())
                                        ->mapWithKeys(function ($team, $staffAssignmentId) use ($availableStaffIds, $activeTeamNames): array {
                                            $staffKey = trim((string) $staffAssignmentId);
                                            $teamName = trim((string) $team);

                                            if (
                                                $staffKey === ''
                                                || $teamName === ''
                                                || ! $availableStaffIds->has($staffKey)
                                                || ! in_array($teamName, $activeTeamNames, true)
                                            ) {
                                                return [];
                                            }

                                            return [$staffKey => $teamName];
                                        })
                                        ->all()
                                    : [];
                                $teamGroups = collect();
                                if ($teamsEnabledForView) {
                                    foreach ($activeTeamNames as $teamName) {
                                        $members = $staffRows
                                            ->filter(fn ($staffAssignment) => ($activeTeamAssignments[(string) $staffAssignment->id] ?? null) === $teamName)
                                            ->values();

                                        if ($members->isNotEmpty()) {
                                            $teamGroups->put($teamName, $members);
                                        }
                                    }

                                    $unassignedMembers = $staffRows
                                        ->filter(fn ($staffAssignment) => ! ($activeTeamAssignments[(string) $staffAssignment->id] ?? null))
                                        ->values();

                                    if ($unassignedMembers->isNotEmpty()) {
                                        $teamGroups->put('Unassigned', $unassignedMembers);
                                    }
                                } else {
                                    $teamGroups->put($selectedRoster->organizationalUnit->name, $staffRows->values());
                                }
                            @endphp

                            <div class="border-b border-gray-200 px-6 py-3 text-xs text-gray-500">
                                Staff totals show net rostered hours for the selected date range.
                            </div>

                            <div class="overflow-x-auto bg-stone-100/60 px-4 py-5">
                                <div class="min-w-[1120px] rounded-lg border border-stone-300 bg-white shadow-sm">
                                    <div class="border-b border-stone-300 px-4 py-2 text-center text-sm font-semibold uppercase tracking-[0.35em] text-stone-700">
                                        {{ \Illuminate\Support\Str::upper($selectedRoster->organizationalUnit->name) }} Duty Rota
                                    </div>

                                    <div class="grid gap-px border-b border-stone-300 bg-stone-300 lg:grid-cols-[1.2fr_0.9fr_1fr]">
                                        <div class="bg-white">
                                            <div class="grid grid-cols-[1.2fr_1fr] text-xs">
                                                <div class="border-b border-stone-300 px-3 py-2 font-semibold uppercase tracking-wide text-stone-600">Ward Name</div>
                                                <div class="border-b border-l border-stone-300 bg-amber-100 px-3 py-2 font-semibold text-stone-900">{{ $selectedRoster->organizationalUnit->name }}</div>
                                            </div>
                                            <div class="grid grid-cols-[1.2fr_1fr] text-xs font-semibold uppercase tracking-wide text-stone-600">
                                                <div class="px-3 py-2">Shift</div>
                                                <div class="border-l border-stone-300 px-3 py-2 text-center">Symbol</div>
                                            </div>
                                            @foreach($shiftTypes as $shiftType)
                                                @php
                                                    $shiftSymbol = $shiftType->code ?: \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($shiftType->name, 0, 2));
                                                @endphp
                                                <div class="grid grid-cols-[1.2fr_1fr] border-t border-stone-200 text-sm text-stone-800">
                                                    <div class="px-3 py-1.5">{{ $shiftType->name }}</div>
                                                    <div class="border-l border-stone-300 px-3 py-1.5 text-center font-semibold uppercase">{{ $shiftSymbol }}</div>
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="bg-white">
                                            <div class="grid grid-cols-[1.3fr_0.7fr] text-xs font-semibold uppercase tracking-wide text-stone-600">
                                                <div class="px-3 py-2">Status</div>
                                                <div class="border-l border-stone-300 px-3 py-2 text-center">Symbol</div>
                                            </div>
                                            @foreach($statusLegend as $legendRow)
                                                <div class="grid grid-cols-[1.3fr_0.7fr] border-t border-stone-200 text-sm text-stone-800">
                                                    <div class="px-3 py-1.5">{{ $legendRow['label'] }}</div>
                                                    <div class="border-l border-stone-300 px-3 py-1.5 text-center font-semibold uppercase">{{ $legendRow['symbol'] }}</div>
                                                </div>
                                            @endforeach
                                            @if($teamsEnabledForView)
                                                <div class="border-t border-stone-200 px-3 py-2 text-xs text-stone-600">
                                                    Teams: {{ implode(', ', $activeTeamNames) }}
                                                </div>
                                            @endif
                                        </div>

                                        <div class="bg-white">
                                            <div class="grid grid-cols-[0.8fr_1.2fr] border-b border-stone-300 text-sm">
                                                <div class="px-3 py-2 font-semibold text-stone-600">Month</div>
                                                <div class="border-l border-stone-300 px-3 py-2 font-semibold text-stone-900">{{ $monthLabel }}</div>
                                            </div>
                                            <div class="grid grid-cols-[0.8fr_1.2fr] border-b border-stone-200 text-sm">
                                                <div class="px-3 py-2 font-semibold text-stone-600">Year</div>
                                                <div class="border-l border-stone-300 px-3 py-2 font-semibold text-stone-900">{{ $yearLabel }}</div>
                                            </div>
                                            <div class="grid grid-cols-[0.8fr_1.2fr] border-b border-stone-200 text-sm">
                                                <div class="px-3 py-2 font-semibold text-stone-600">Roster</div>
                                                <div class="border-l border-stone-300 px-3 py-2 text-stone-900">{{ $selectedRoster->name }}</div>
                                            </div>
                                            <div class="grid grid-cols-[0.8fr_1.2fr] text-sm">
                                                <div class="px-3 py-2 font-semibold text-stone-600">Title Set</div>
                                                <div class="border-l border-stone-300 px-3 py-2 text-stone-900">{{ $selectedRoster->cadre_or_discipline }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <table class="min-w-full border-collapse">
                                        <thead class="bg-stone-100">
                                            <tr>
                                                <th class="border border-stone-300 px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.25em] text-stone-600">Team</th>
                                                <th class="border border-stone-300 px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.25em] text-stone-600">Name</th>
                                                <th class="border border-stone-300 px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.25em] text-stone-600">Cadre</th>
                                                @foreach($editorDates as $date)
                                                    @php
                                                        $dateEvents = $rosterEvents->filter(
                                                            fn ($event) => $date->betweenIncluded($event->starts_on, $event->ends_on)
                                                        );
                                                    @endphp
                                                    <th class="border border-stone-300 px-1 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-stone-600">
                                                        <div>{{ $date->format('D') }}</div>
                                                        <div class="mt-1 text-sm font-bold text-stone-900">{{ $date->format('j') }}</div>
                                                        @if($dateEvents->isNotEmpty())
                                                            <div class="mt-1 text-[9px] font-semibold text-blue-700">{{ $dateEvents->count() }}</div>
                                                        @endif
                                                    </th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white">
                                            @foreach($teamGroups as $teamLabel => $teamMembers)
                                                <tr class="bg-rose-50/40">
                                                    <td colspan="{{ 3 + count($editorDates) }}" class="border-y border-stone-300 px-3 py-2 text-left text-sm font-semibold uppercase tracking-[0.3em] text-rose-500">
                                                        {{ $teamLabel }}
                                                    </td>
                                                </tr>

                                                @foreach($teamMembers as $staffAssignment)
                                                    @php
                                                        $staffContext = $staffUiContext[$staffAssignment->id] ?? ['date_statuses' => []];
                                                        $cadreLabel = $staffAssignment->staff_title ?: $staffAssignment->staff_cadre ?: 'Staff';
                                                    @endphp
                                                    <tr>
                                                        <td class="border border-stone-300 px-3 py-2 text-xs uppercase tracking-[0.2em] text-stone-400">
                                                            @if($teamsEnabledForView)
                                                                @if($selectedRoster->isEditable())
                                                                    <select wire:model.live="editingTeamAssignments.{{ $staffAssignment->id }}" class="w-full rounded border-stone-300 bg-white px-2 py-1 text-[11px] font-semibold normal-case tracking-normal text-stone-700 focus:border-blue-500 focus:ring-blue-500">
                                                                        <option value="">Unassigned</option>
                                                                        @foreach($activeTeamNames as $teamName)
                                                                            <option value="{{ $teamName }}">{{ $teamName }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                @else
                                                                    {{ $activeTeamAssignments[(string) $staffAssignment->id] ?? 'Unassigned' }}
                                                                @endif
                                                            @else
                                                                Roster
                                                            @endif
                                                        </td>
                                                        <td class="border border-stone-300 px-3 py-2 align-top">
                                                            <div class="text-sm font-semibold text-stone-900">{{ $staffAssignment->staff_name }}</div>
                                                            <div class="mt-1 text-[11px] text-stone-500">{{ $staffScheduledHours[$staffAssignment->id] ?? '0.0h' }} rostered</div>
                                                        </td>
                                                        <td class="border border-stone-300 px-3 py-2 text-sm text-stone-800">
                                                            {{ $cadreLabel }}
                                                        </td>
                                                        @foreach($editorDates as $date)
                                                            @php
                                                                $dateKey = $date->toDateString();
                                                                $dateStatus = $staffContext['date_statuses'][$dateKey] ?? null;
                                                                $selectedShiftId = (string) ($entrySelections[$staffAssignment->id][$dateKey] ?? '');
                                                                $selectedShift = $selectedShiftId !== '' ? $shiftTypesById->get($selectedShiftId) : null;
                                                                $isApprovedUnavailable = ($dateStatus['status'] ?? null) === \App\Models\HrStaffUnavailability::STATUS_APPROVED;
                                                                $cellSymbol = $isApprovedUnavailable
                                                                    ? 'H'
                                                                    : ($selectedShift
                                                                        ? ($selectedShift->code ?: \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($selectedShift->name, 0, 2)))
                                                                        : 'X');
                                                                $cellTone = $isApprovedUnavailable
                                                                    ? 'bg-rose-50 text-rose-700'
                                                                    : ($selectedShift ? 'bg-white text-stone-900' : 'bg-stone-50 text-rose-500');
                                                            @endphp
                                                            <td class="border border-stone-300 px-0.5 py-0.5 text-center {{ $cellTone }}">
                                                                @if($selectedRoster->isEditable() && $shiftTypes->isNotEmpty() && ! $isApprovedUnavailable)
                                                                    <select wire:model="entrySelections.{{ $staffAssignment->id }}.{{ $dateKey }}" class="h-8 w-full min-w-[2.8rem] border-0 bg-transparent px-0 text-center text-xs font-semibold uppercase tracking-[0.2em] focus:bg-white focus:ring-1 focus:ring-blue-500">
                                                                        <option value="">X</option>
                                                                        @foreach($shiftTypes as $shiftType)
                                                                            @php
                                                                                $shiftSymbol = $shiftType->code ?: \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($shiftType->name, 0, 2));
                                                                            @endphp
                                                                            <option value="{{ $shiftType->id }}">{{ $shiftSymbol }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                @else
                                                                    <div class="flex h-8 min-w-[2.8rem] items-center justify-center text-xs font-semibold uppercase tracking-[0.22em]">
                                                                        {{ $cellSymbol }}
                                                                    </div>
                                                                @endif
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
    </div>
    @endif

    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-500 bg-opacity-75 px-4 py-6 sm:items-center">
            <div class="w-full max-w-4xl max-h-[calc(100vh-3rem)] overflow-y-auto rounded-md bg-white px-4 pb-4 pt-5 shadow-xl sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900">Create Duty Roster</h3>
                <p class="mt-2 text-sm text-gray-500">Create a new roster draft for the selected client space and choose one or more titles.</p>

                <form wire:submit.prevent="createRoster" class="mt-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Roster Name</label>
                        <input type="text" wire:model="newRosterName" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        @error('name') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Titles</label>
                        <div class="mt-2 max-h-60 space-y-2 overflow-y-auto rounded-md border border-gray-200 bg-gray-50 p-3">
                            @forelse($disciplineOptions as $discipline)
                                <label class="flex items-start gap-3 rounded-md border border-gray-200 bg-white px-3 py-2">
                                    <input type="checkbox" wire:model="newRosterDisciplines" value="{{ $discipline['label'] }}" class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-gray-900">{{ $discipline['label'] }}</span>
                                        <span class="block text-xs text-gray-500">{{ $discipline['count'] }} roster-eligible staff</span>
                                    </span>
                                </label>
                            @empty
                                <div class="rounded-md border border-dashed border-gray-300 bg-white px-3 py-4 text-sm text-gray-500">
                                    No active titles are available in this client space.
                                </div>
                            @endforelse
                        </div>
                        <p class="mt-2 text-xs text-gray-500">Use more than one title only when you want one shared roster across those staff groups.</p>
                        @error('cadre_or_discipline') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="rounded-md border border-gray-200 bg-gray-50 px-4 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Teams</label>
                                <p class="mt-1 text-xs text-gray-500">Define team names here, then assign eligible client-space staff to those teams before creating the draft.</p>
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                                <input type="checkbox" wire:model.live="newRosterUsesTeams" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                Group staff by teams
                            </label>
                        </div>

                        @if($newRosterUsesTeams)
                            <div class="mt-4 space-y-3">
                                @foreach($newRosterTeamNames as $index => $teamName)
                                    <div class="flex items-center gap-2">
                                        <input type="text" wire:model="newRosterTeamNames.{{ $index }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" placeholder="Team name">
                                        <button type="button" wire:click="removeNewRosterTeam({{ $index }})" class="inline-flex items-center justify-center rounded-md border border-rose-200 bg-white px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                            Remove
                                        </button>
                                    </div>
                                @endforeach

                                <button type="button" wire:click="addNewRosterTeam" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                    Add Team
                                </button>
                            </div>
                            @php
                                $newRosterActiveTeamNames = collect($newRosterTeamNames)
                                    ->map(fn ($team) => trim((string) $team))
                                    ->filter()
                                    ->unique(fn ($team) => \Illuminate\Support\Str::lower($team))
                                    ->values()
                                    ->all();
                            @endphp
                            @if($newRosterActiveTeamNames !== [])
                                <div class="mt-4 rounded-md border border-gray-200 bg-white p-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <h4 class="text-sm font-semibold text-gray-900">Assign Staff to Teams</h4>
                                            <p class="mt-1 text-xs text-gray-500">Choose the team for each roster-eligible staff member now so the draft opens with the correct grouping.</p>
                                        </div>
                                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">
                                            {{ $newRosterStaffRows->count() }} staff
                                        </span>
                                    </div>

                                    @if($newRosterStaffRows->isEmpty())
                                        <div class="mt-3 rounded-md border border-dashed border-gray-300 bg-gray-50 px-3 py-4 text-sm text-gray-500">
                                            No roster-eligible staff match the selected title set yet.
                                        </div>
                                    @else
                                        <div class="mt-3 max-h-72 overflow-y-auto rounded-md border border-gray-200">
                                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Staff</th>
                                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Title</th>
                                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Team</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200 bg-white">
                                                    @foreach($newRosterStaffRows as $staffAssignment)
                                                        @php
                                                            $staffCadreLabel = $staffAssignment->staff_title ?: $staffAssignment->staff_cadre ?: 'Staff';
                                                        @endphp
                                                        <tr>
                                                            <td class="px-3 py-2 align-top">
                                                                <div class="font-medium text-gray-900">{{ $staffAssignment->staff_name }}</div>
                                                                @if($staffAssignment->staff_uuid)
                                                                    <div class="mt-0.5 text-[11px] text-gray-500">{{ $staffAssignment->staff_uuid }}</div>
                                                                @endif
                                                            </td>
                                                            <td class="px-3 py-2 text-gray-700">{{ $staffCadreLabel }}</td>
                                                            <td class="px-3 py-2">
                                                                <select wire:model.live="newRosterTeamAssignments.{{ $staffAssignment->id }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                                                    <option value="">Unassigned</option>
                                                                    @foreach($newRosterActiveTeamNames as $teamName)
                                                                        <option value="{{ $teamName }}">{{ $teamName }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            @endif
                            @error('team_names') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            @error('team_assignments') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        @endif
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Duration</label>
                            <select wire:model="newRosterDurationPreset" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="monthly">Monthly</option>
                                <option value="custom">Custom</option>
                            </select>
                            <p class="mt-2 text-xs text-gray-500">Monthly is the default. Choose Custom when you want to set the end date yourself.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Start Date</label>
                            <input type="date" wire:model="newRosterStartDate" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <p class="mt-1 text-[11px] text-gray-500">Display guide: mm/dd/yyyy</p>
                            @error('start_date') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">End Date</label>
                            <input type="date" wire:model="newRosterEndDate" @disabled($newRosterDurationPreset !== 'custom') class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                            <p class="mt-1 text-[11px] text-gray-500">Display guide: mm/dd/yyyy</p>
                            @error('end_date') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showCreateModal', false)" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex items-center justify-center rounded-md border border-transparent bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                            Create Draft
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
