<?php

namespace App\Livewire;

use App\Models\ApprovalWorkflow;
use App\Models\HrOrganizationalUnit;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Services\DutyRosterService;
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
    public array $disciplineOptions = [];
    public ?string $message = null;
    public bool $canAddSetup = false;
    public bool $canEditSetup = false;

    public bool $showModal = false;
    public ?int $editingId = null;
    public ?int $clientSpaceId = null;
    public string $disciplineTitle = '';
    public array $approverUuids = [];

    public function mount(): void
    {
        $org = Organization::current();
        $this->organizationId = $org?->id;
        $this->canAddSetup = Auth::user()?->canAddHrSetup() ?? false;
        $this->canEditSetup = Auth::user()?->canEditHrSetup() ?? false;
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
            ->with(['organizationalUnit', 'approvers'])
            ->get()
            ->sortBy(fn (ApprovalWorkflow $workflow): string => sprintf(
                '%d|%s|%d|%s',
                $workflow->organizational_unit_id === null ? 1 : 0,
                Str::lower((string) ($workflow->organizationalUnit?->name ?? '')),
                blank($workflow->discipline_title) ? 1 : 0,
                Str::lower((string) ($workflow->discipline_title ?? ''))
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

    public function updatedClientSpaceId($value): void
    {
        $this->disciplineTitle = '';
        $this->loadDisciplineOptions((int) $value);
    }

    public function openCreateModal(): void
    {
        if (! $this->canAddSetup) {
            $this->message = 'You do not have permission to manage HR setup.';
            return;
        }

        $this->resetForm();
        $this->message = null;
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        if (! $this->canEditSetup) {
            $this->message = 'You do not have permission to manage HR setup.';
            return;
        }

        $workflow = ApprovalWorkflow::with('approvers')->find($id);

        if (! $workflow || $workflow->approval_category !== 'roster') {
            return;
        }

        $this->editingId = $workflow->id;
        $this->clientSpaceId = $workflow->organizational_unit_id;
        $this->loadDisciplineOptions($workflow->organizational_unit_id);
        $this->disciplineTitle = (string) ($workflow->discipline_title ?? '');
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
        if (($this->editingId && ! $this->canEditSetup) || (! $this->editingId && ! $this->canAddSetup)) {
            $this->message = 'You do not have permission to manage HR setup.';
            return;
        }

        $validated = $this->validate(array_merge([
            'clientSpaceId' => ['nullable', 'integer'],
        ], $this->approverValidationRules()));

        $approverSelections = $this->normalizedApproverSelections();

        if ($this->hasDuplicateApproverSelections($approverSelections)) {
            return;
        }

        $disciplineTitle = Str::of($this->disciplineTitle)->squish()->trim()->toString();

        if (($validated['clientSpaceId'] ?? null) === null && $disciplineTitle !== '') {
            $this->addError('clientSpaceId', 'Select a client space before choosing a title-specific roster rule.');
            return;
        }

        $duplicateQuery = ApprovalWorkflow::query()
            ->where('organization_id', $this->organizationId)
            ->where('approval_category', 'roster')
            ->when(
                ($validated['clientSpaceId'] ?? null) === null,
                fn ($query) => $query->whereNull('organizational_unit_id'),
                fn ($query) => $query->where('organizational_unit_id', $validated['clientSpaceId'])
            )
            ->when(
                $disciplineTitle === '',
                fn ($query) => $query->whereNull('discipline_title'),
                fn ($query) => $query->whereRaw('LOWER(discipline_title) = ?', [Str::lower($disciplineTitle)])
            );

        if ($this->editingId) {
            $duplicateQuery->whereKeyNot($this->editingId);
        }

        if ($duplicateQuery->exists()) {
            $key = $disciplineTitle === '' ? 'clientSpaceId' : 'disciplineTitle';
            $this->addError($key, 'A roster approval rule already exists for this client space and title scope.');
            return;
        }

        $payload = [
            'organization_id' => $this->organizationId,
            'organizational_unit_id' => $validated['clientSpaceId'] ?? null,
            'approval_category' => 'roster',
            'discipline_title' => $disciplineTitle !== '' ? $disciplineTitle : null,
            'is_active' => true,
        ];

        if ($this->editingId) {
            $workflow = ApprovalWorkflow::findOrFail($this->editingId);
            $workflow->update($payload);
        } else {
            $workflow = ApprovalWorkflow::create($payload);
        }

        $workflow->approvers()->delete();

        foreach (self::APPROVER_LEVELS as $level) {
            $workflow->syncApprovers($level, collect($approverSelections[$level] ?? [])
                ->values()
                ->map(fn (string $uuid): array => [
                    'uuid' => $uuid,
                    'name' => $this->staffOptions[$uuid] ?? $uuid,
                ])
                ->all());
        }

        $this->showModal = false;
        $this->message = 'Roster approval rule saved.';
        $this->resetForm();
        $this->loadRules();
    }

    public function deleteRule(int $id): void
    {
        if (! $this->canEditSetup) {
            $this->message = 'You do not have permission to manage HR setup.';
            return;
        }

        $workflow = ApprovalWorkflow::find($id);

        if (! $workflow || $workflow->approval_category !== 'roster') {
            return;
        }

        $workflow->update(['is_active' => false]);
        $this->message = 'Roster approval rule deactivated.';
        $this->loadRules();
    }

    public function resetForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->clientSpaceId = array_key_first($this->clientSpaceOptions);
        $this->disciplineTitle = '';
        $this->approverUuids = $this->defaultApproverSelections();
        $this->loadDisciplineOptions($this->clientSpaceId);
    }

    public function addApproverSlot(string $level): void
    {
        if (! in_array($level, self::APPROVER_LEVELS, true)) {
            return;
        }

        $this->approverUuids[$level][] = '';
    }

    public function removeApproverSlot(string $level, int $index): void
    {
        if (! in_array($level, self::APPROVER_LEVELS, true)) {
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

    private function loadDisciplineOptions(?int $clientSpaceId): void
    {
        $this->disciplineOptions = [];

        if (! $clientSpaceId) {
            return;
        }

        $clientSpace = HrOrganizationalUnit::query()
            ->where('organization_id', $this->organizationId)
            ->clientSpaces()
            ->find($clientSpaceId);

        if (! $clientSpace) {
            return;
        }

        $this->disciplineOptions = app(DutyRosterService::class)
            ->availableDisciplines($clientSpace)
            ->pluck('label')
            ->values()
            ->all();
    }

    private function approverValidationRules(): array
    {
        $rules = [];

        foreach (self::APPROVER_LEVELS as $level) {
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

        foreach (self::APPROVER_LEVELS as $level) {
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

        foreach (self::APPROVER_LEVELS as $level) {
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
}
