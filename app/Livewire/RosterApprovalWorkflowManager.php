<?php

namespace App\Livewire;

use App\Models\ApprovalWorkflow;
use App\Models\HrDutyRoster;
use App\Models\HrOrganizationalUnit;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Models\User;
use App\Services\KashApiService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class RosterApprovalWorkflowManager extends Component
{
    private const APPROVER_LEVELS = ['primary', 'secondary', 'tertiary'];
    private const MIN_APPROVERS_PER_LEVEL = 3;

    public $organizationId;
    public $rules = [];
    public array $staffOptions = [];
    public array $clientSpaceOptions = [];
    public array $existingRosters = [];
    public ?string $message = null;
    public bool $canDesignateRosterApprovers = false;

    public bool $showModal = false;
    public ?int $editingId = null;
    public ?int $clientSpaceId = null;
    public int $approvalLevelCount = 3;
    public array $approverUuids = [];

    public function mount(): void
    {
        $org = Organization::current();
        $this->organizationId = $org?->id;
        $this->canDesignateRosterApprovers = Auth::user()?->canDesignateHrRosterApprovers() ?? false;
        $this->loadStaffOptions();
        $this->loadClientSpaceOptions();
        $this->loadRules();
        $this->resetForm();
    }

    public function loadRules(): void
    {
        if (! $this->organizationId) {
            $this->rules = [];
            return;
        }

        $this->rules = ApprovalWorkflow::query()
            ->where('organization_id', $this->organizationId)
            ->where('approval_category', 'roster')
            ->whereNull('discipline_title')
            ->whereNotNull('organizational_unit_id')
            ->with(['organizationalUnit', 'approvers'])
            ->get()
            ->sortBy(fn (ApprovalWorkflow $workflow): string => sprintf(
                '%s',
                Str::lower((string) ($workflow->organizationalUnit?->name ?? ''))
            ))
            ->values()
            ->toArray();
    }

    public function loadStaffOptions(): void
    {
        $this->staffOptions = [];

        if ($this->organizationId) {
            $this->staffOptions = StaffAssignment::query()
                ->where('organization_id', $this->organizationId)
                ->where('status', 'active')
                ->orderBy('staff_name')
                ->pluck('staff_name', 'staff_uuid')
                ->toArray();
        }

        if (! empty($this->staffOptions)) {
            return;
        }

        try {
            $staffData = app(KashApiService::class)->getStaff(['per_page' => 100]);
            foreach (Arr::get($staffData, 'data', []) as $staff) {
                $uuid = $staff['uuid'] ?? $staff['id'] ?? null;
                $name = $staff['name'] ?? $staff['full_name'] ?? trim(($staff['first_name'] ?? '') . ' ' . ($staff['last_name'] ?? ''));

                if ($uuid && $name) {
                    $this->staffOptions[(string) $uuid] = $name;
                }
            }
        } catch (\Throwable) {
            $this->staffOptions = [];
        }
    }

    public function loadClientSpaceOptions(): void
    {
        if (! $this->organizationId) {
            $this->clientSpaceOptions = [];
            return;
        }

        $this->clientSpaceOptions = HrOrganizationalUnit::query()
            ->where('organization_id', $this->organizationId)
            ->clientSpaces()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function openCreateModal(): void
    {
        $this->authorizeRosterApproverDesignation();

        $this->resetForm();
        $this->message = null;
        $this->showModal = true;
    }

    public function updatedClientSpaceId($value): void
    {
        $clientSpaceId = is_numeric($value) ? (int) $value : null;
        $this->loadExistingRosters($clientSpaceId);
    }

    public function openEditModal(int $id): void
    {
        $this->authorizeRosterApproverDesignation();

        $workflow = ApprovalWorkflow::with('approvers')->find($id);

        if (! $workflow || $workflow->approval_category !== 'roster') {
            return;
        }

        $this->editingId = $workflow->id;
        $this->clientSpaceId = $workflow->organizational_unit_id;
        $this->loadExistingRosters($this->clientSpaceId);
        $this->approvalLevelCount = $this->inferApprovalLevelCount($workflow);
        $this->approverUuids = $this->defaultApproverSelections();

        foreach ($workflow->approvers->groupBy('approver_level') as $level => $approvers) {
            $this->approverUuids[$level] = $approvers
                ->pluck('approver_staff_uuid')
                ->map(fn ($uuid) => (string) $uuid)
                ->values()
                ->all();

            foreach ($approvers as $approver) {
                $this->staffOptions[$approver->approver_staff_uuid] = $approver->approver_name;
            }

            $this->ensureApproverSlotCount($level);
        }

        $this->message = null;
        $this->showModal = true;
    }

    public function saveRule(): void
    {
        $this->authorizeRosterApproverDesignation();

        $validated = $this->validate(array_merge([
            'clientSpaceId' => ['required', 'integer'],
            'approvalLevelCount' => ['required', 'integer', 'min:1', 'max:3'],
        ], $this->approverValidationRules()));

        $approverSelections = $this->normalizedApproverSelections();

        if ($this->hasDuplicateApproverSelections($approverSelections)) {
            return;
        }

        $duplicateQuery = ApprovalWorkflow::query()
            ->where('organization_id', $this->organizationId)
            ->where('approval_category', 'roster')
            ->whereNull('discipline_title')
            ->where('organizational_unit_id', $validated['clientSpaceId']);

        if ($this->editingId) {
            $duplicateQuery->whereKeyNot($this->editingId);
        }

        if ($duplicateQuery->exists()) {
            $this->addError('clientSpaceId', 'A roster approval rule already exists for this client-space scope.');
            return;
        }

        $payload = [
            'organization_id' => $this->organizationId,
            'organizational_unit_id' => $validated['clientSpaceId'] ?? null,
            'approval_category' => 'roster',
            'discipline_title' => null,
            'is_active' => true,
        ];

        $workflow = $this->persistWorkflow(
            $this->editingId ? ApprovalWorkflow::findOrFail($this->editingId) : null,
            $payload,
            $approverSelections,
            $this->selectedApproverLevels()
        );

        $leaveWorkflow = ApprovalWorkflow::query()
            ->where('organization_id', $this->organizationId)
            ->where('approval_category', 'leave')
            ->where('organizational_unit_id', $validated['clientSpaceId'])
            ->first();

        $this->persistWorkflow($leaveWorkflow, [
            'organization_id' => $this->organizationId,
            'organizational_unit_id' => $validated['clientSpaceId'],
            'approval_category' => 'leave',
            'discipline_title' => null,
            'is_active' => true,
        ], $approverSelections, $this->selectedApproverLevels());

        $this->showModal = false;
        $this->message = 'Roster approval rule saved.';
        $this->resetForm();
        $this->loadRules();
    }

    public function deleteRule(int $id): void
    {
        $this->authorizeRosterApproverDesignation();

        $workflow = ApprovalWorkflow::find($id);

        if (! $workflow || $workflow->approval_category !== 'roster') {
            return;
        }

        $workflow->update(['is_active' => false]);

        if ($workflow->organizational_unit_id) {
            ApprovalWorkflow::query()
                ->where('organization_id', $workflow->organization_id)
                ->where('approval_category', 'leave')
                ->where('organizational_unit_id', $workflow->organizational_unit_id)
                ->update(['is_active' => false]);
        }

        $this->message = 'Roster approval rule deactivated.';
        $this->loadRules();
    }

    public function resetForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->clientSpaceId = array_key_first($this->clientSpaceOptions);
        $this->loadExistingRosters($this->clientSpaceId);
        $this->approvalLevelCount = 3;
        $this->approverUuids = $this->defaultApproverSelections();
    }

    public function addApproverSlot(string $level): void
    {
        $this->authorizeRosterApproverDesignation();

        if (! in_array($level, $this->selectedApproverLevels(), true)) {
            return;
        }

        $this->approverUuids[$level][] = '';
    }

    public function removeApproverSlot(string $level, int $index): void
    {
        $this->authorizeRosterApproverDesignation();

        if (! in_array($level, $this->selectedApproverLevels(), true)) {
            return;
        }

        $slots = $this->approverUuids[$level] ?? [];

        if (count($slots) <= self::MIN_APPROVERS_PER_LEVEL || ! array_key_exists($index, $slots)) {
            return;
        }

        unset($slots[$index]);
        $this->approverUuids[$level] = array_values($slots);
    }

    public function render()
    {
        return view('livewire.roster-approval-workflow-manager');
    }

    private function loadExistingRosters(?int $clientSpaceId): void
    {
        $this->existingRosters = [];

        if (! $this->organizationId || ! $clientSpaceId) {
            return;
        }

        $this->existingRosters = HrDutyRoster::query()
            ->where('organization_id', $this->organizationId)
            ->where('organizational_unit_id', $clientSpaceId)
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'cadre_or_discipline',
                'discipline_titles',
                'start_date',
                'end_date',
                'status',
                'approval_status',
            ])
            ->map(fn (HrDutyRoster $roster): array => [
                'id' => $roster->id,
                'name' => $roster->name,
                'titles' => $roster->disciplineTitles(),
                'start_date' => $roster->start_date?->format('M j, Y'),
                'end_date' => $roster->end_date?->format('M j, Y'),
                'status' => $roster->status,
                'approval_status' => $roster->approval_status,
            ])
            ->values()
            ->all();
    }

    private function approverValidationRules(): array
    {
        $rules = [];

        foreach ($this->selectedApproverLevels() as $level) {
            $rules["approverUuids.$level"] = 'required|array|min:'.self::MIN_APPROVERS_PER_LEVEL;
            $rules["approverUuids.$level.*"] = 'required|string|distinct';
        }

        return $rules;
    }

    private function defaultApproverSelections(): array
    {
        return collect(self::APPROVER_LEVELS)
            ->mapWithKeys(fn (string $level): array => [
                $level => array_fill(0, self::MIN_APPROVERS_PER_LEVEL, ''),
            ])
            ->all();
    }

    private function ensureApproverSlotCount(string $level): void
    {
        $slots = array_values($this->approverUuids[$level] ?? []);

        while (count($slots) < self::MIN_APPROVERS_PER_LEVEL) {
            $slots[] = '';
        }

        $this->approverUuids[$level] = $slots;
    }

    private function normalizedApproverSelections(): array
    {
        $normalized = [];

        foreach ($this->selectedApproverLevels() as $level) {
            $normalized[$level] = collect($this->approverUuids[$level] ?? [])
                ->map(fn ($uuid): string => trim((string) $uuid))
                ->values()
                ->all();
        }

        return $normalized;
    }

    private function hasDuplicateApproverSelections(array $approverSelections): bool
    {
        $seen = [];
        $hasDuplicates = false;

        foreach ($this->selectedApproverLevels() as $level) {
            foreach ($approverSelections[$level] ?? [] as $index => $uuid) {
                if ($uuid === '') {
                    continue;
                }

                if (isset($seen[$uuid])) {
                    $this->addError("approverUuids.$level.$index", 'This staff member is already assigned to another approval slot.');
                    $hasDuplicates = true;
                    continue;
                }

                $seen[$uuid] = true;
            }
        }

        return $hasDuplicates;
    }

    private function authorizeRosterApproverDesignation(): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->canDesignateHrRosterApprovers(), 403);
    }

    private function selectedApproverLevels(): array
    {
        return array_slice(self::APPROVER_LEVELS, 0, max(1, min(3, $this->approvalLevelCount)));
    }

    private function inferApprovalLevelCount(ApprovalWorkflow $workflow): int
    {
        $presentLevels = $workflow->approvers
            ->pluck('approver_level')
            ->filter(fn ($level): bool => in_array($level, self::APPROVER_LEVELS, true))
            ->unique()
            ->values()
            ->all();

        if (in_array('tertiary', $presentLevels, true)) {
            return 3;
        }

        if (in_array('secondary', $presentLevels, true)) {
            return 2;
        }

        return 1;
    }

    private function persistWorkflow(
        ?ApprovalWorkflow $workflow,
        array $data,
        array $approverSelections,
        array $levels
    ): ApprovalWorkflow {
        $workflow ??= ApprovalWorkflow::create($data);

        if ($workflow->exists) {
            $workflow->update($data);
        }

        $workflow->approvers()->delete();

        foreach ($levels as $level) {
            $workflow->syncApprovers($level, collect($approverSelections[$level] ?? [])
                ->values()
                ->map(fn (string $uuid): array => [
                    'uuid' => $uuid,
                    'name' => $this->staffOptions[$uuid] ?? $uuid,
                ])
                ->all());
        }

        return $workflow;
    }
}
