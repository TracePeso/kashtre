<?php

namespace App\Livewire;

use App\Models\ApprovalWorkflow;
use App\Models\HrOrganizationalUnit;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Services\KashApiService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ApprovalWorkflowManager extends Component
{
    private const GENERIC_CATEGORIES = ['leave', 'coverage', 'offsite_duty'];
    private const CLIENT_SPACE_SCOPED_CATEGORIES = ['leave', 'offsite_duty'];
    private const APPROVER_LEVELS = ['primary', 'secondary', 'tertiary'];
    private const MIN_APPROVERS_PER_LEVEL = 3;

    public $organizationId;
    public $workflows = [];
    public array $staffOptions = [];
    public array $clientSpaceOptions = [];
    public array $configuredCategories = [];
    public ?string $message = null;
    public bool $canAddSetup = false;
    public bool $canEditSetup = false;

    // Form state
    public bool $showModal = false;
    public ?int $editingId = null;
    public string $category = 'leave';
    public ?int $clientSpaceId = null;
    public int $approvalLevelCount = 3;
    public array $approverUuids = [];

    public function mount()
    {
        $org = Organization::current();
        $this->organizationId = $org?->id;
        $this->canAddSetup = Auth::user()?->canAddHrSetup() ?? false;
        $this->canEditSetup = Auth::user()?->canEditHrSetup() ?? false;
        $this->loadStaffOptions();
        $this->loadClientSpaceOptions();
        $this->loadWorkflows();
        $this->resetForm();
    }

    public function loadWorkflows()
    {
        if (!$this->organizationId) {
            $this->workflows = [];
            return;
        }

        $this->workflows = ApprovalWorkflow::where('organization_id', $this->organizationId)
            ->whereIn('approval_category', self::GENERIC_CATEGORIES)
            ->with(['approvers', 'organizationalUnit'])
            ->get()
            ->sortBy(fn (ApprovalWorkflow $workflow): string => sprintf(
                '%s|%s',
                $workflow->approval_category,
                strtolower((string) ($workflow->organizationalUnit?->name ?? ''))
            ))
            ->values()
            ->toArray();

        $this->configuredCategories = collect($this->workflows)
            ->pluck('approval_category')
            ->all();
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

    public function loadStaffOptions(): void
    {
        $this->staffOptions = [];

        if ($this->organizationId) {
            $this->staffOptions = StaffAssignment::where('organization_id', $this->organizationId)
                ->where('status', 'active')
                ->orderBy('staff_name')
                ->pluck('staff_name', 'staff_uuid')
                ->toArray();
        }

        if (!empty($this->staffOptions)) {
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

    public function openCreateModal()
    {
        if (!$this->canAddSetup) {
            $this->message = 'You do not have permission to manage HR setup.';
            return;
        }

        $this->resetForm();
        $this->message = null;
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        if (!$this->canEditSetup) {
            $this->message = 'You do not have permission to manage HR setup.';
            return;
        }

        $wf = ApprovalWorkflow::with('approvers')->find($id);
        if (!$wf) return;

        $this->editingId = $wf->id;
        $this->category = $wf->approval_category;
        $this->clientSpaceId = $wf->organizational_unit_id;
        $this->approvalLevelCount = $this->inferApprovalLevelCount($wf);
        $this->approverUuids = $this->defaultApproverSelections();

        foreach ($wf->approvers->groupBy('approver_level') as $level => $approvers) {
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

    public function saveWorkflow()
    {
        if (($this->editingId && !$this->canEditSetup) || (!$this->editingId && !$this->canAddSetup)) {
            $this->message = 'You do not have permission to manage HR setup.';
            return;
        }

        $this->validate(array_merge([
            'category' => [
                'required',
                'in:leave,coverage,offsite_duty',
            ],
            'clientSpaceId' => ['nullable', 'integer'],
            'approvalLevelCount' => ['required', 'integer', 'min:1', 'max:3'],
        ], $this->approverValidationRules()));

        $approverSelections = $this->normalizedApproverSelections();
        $clientSpaceId = $this->categoryRequiresClientSpace()
            ? $this->clientSpaceId
            : null;

        if ($this->hasDuplicateApproverSelections($approverSelections)) {
            return;
        }

        if ($this->categoryRequiresClientSpace() && ! $clientSpaceId) {
            $this->addError('clientSpaceId', sprintf(
                'Select a client space for %s approval workflows.',
                $this->categoryLabel($this->category)
            ));
            return;
        }

        $duplicateQuery = ApprovalWorkflow::query()
            ->where('organization_id', $this->organizationId)
            ->where('approval_category', $this->category)
            ->when(
                $clientSpaceId === null,
                fn ($query) => $query->whereNull('organizational_unit_id'),
                fn ($query) => $query->where('organizational_unit_id', $clientSpaceId)
            );

        if ($this->editingId) {
            $duplicateQuery->whereKeyNot($this->editingId);
        }

        if ($duplicateQuery->exists()) {
            $this->addError('clientSpaceId', 'An approval workflow already exists for this category and client-space scope.');
            return;
        }

        $data = [
            'organization_id' => $this->organizationId,
            'approval_category' => $this->category,
            'organizational_unit_id' => $clientSpaceId,
            'is_active' => true,
        ];

        $wf = $this->persistWorkflow(
            $this->editingId ? ApprovalWorkflow::find($this->editingId) : null,
            $data,
            $approverSelections,
            $this->selectedApproverLevels()
        );

        if ($this->category === 'leave' && $clientSpaceId) {
            $rosterWorkflow = ApprovalWorkflow::query()
                ->where('organization_id', $this->organizationId)
                ->where('approval_category', 'roster')
                ->where('organizational_unit_id', $clientSpaceId)
                ->whereNull('discipline_title')
                ->first();

            $this->persistWorkflow($rosterWorkflow, [
                'organization_id' => $this->organizationId,
                'approval_category' => 'roster',
                'organizational_unit_id' => $clientSpaceId,
                'discipline_title' => null,
                'is_active' => true,
            ], $approverSelections, $this->selectedApproverLevels());
        }

        $this->showModal = false;
        $this->message = 'Approval workflow saved.';
        $this->resetForm();
        $this->loadWorkflows();
    }

    public function deleteWorkflow($id)
    {
        if (!$this->canEditSetup) {
            $this->message = 'You do not have permission to manage HR setup.';
            return;
        }

        $workflow = ApprovalWorkflow::find($id);

        if (! $workflow) {
            return;
        }

        $workflow->update(['is_active' => false]);

        if ($workflow->approval_category === 'leave' && $workflow->organizational_unit_id) {
            ApprovalWorkflow::query()
                ->where('organization_id', $workflow->organization_id)
                ->where('approval_category', 'roster')
                ->where('organizational_unit_id', $workflow->organizational_unit_id)
                ->whereNull('discipline_title')
                ->update(['is_active' => false]);
        }

        $this->message = 'Approval workflow deactivated.';
        $this->loadWorkflows();
    }

    public function resetForm()
    {
        $this->editingId = null;
        $this->category = 'leave';
        $this->clientSpaceId = null;
        $this->approvalLevelCount = 3;
        $this->approverUuids = $this->defaultApproverSelections();
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
        return view('livewire.approval-workflow-manager');
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

    public function categoryRequiresClientSpace(?string $category = null): bool
    {
        return in_array($category ?? $this->category, self::CLIENT_SPACE_SCOPED_CATEGORIES, true);
    }

    public function categoryLabel(?string $category = null): string
    {
        return match ($category ?? $this->category) {
            'leave' => 'leave',
            'offsite_duty' => 'official workshop/meeting',
            'coverage' => 'coverage',
            default => 'this',
        };
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
