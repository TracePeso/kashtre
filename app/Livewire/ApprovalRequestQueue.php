<?php

namespace App\Livewire;

use App\Models\ApprovalWorkflow;
use App\Models\HrClientSpaceStaffAssignment;
use App\Models\HrOrganizationalUnit;
use App\Models\HrApprovalRequest;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Services\KashApiService;
use App\Services\WorkingDayCalculator;
use App\Support\StaffRecordData;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class ApprovalRequestQueue extends Component
{
    public bool $leaveOnly = false;
    public ?int $organizationId = null;
    public array $requests = [];
    public array $workflowCategories = [];
    public array $staffOptions = [];
    public array $leaveTypeOptions = [];
    public array $leaveTypeClientConfig = [];
    public array $requesterAssignmentOptions = [];
    public array $leaveClientSpaceOptions = [];
    public array $leaveSummaryAssignmentOptions = [];
    public array $leaveWorkingDayPreview = [
        'weekendDays' => [0, 6],
        'holidayDates' => [],
        'recurringHolidayTokens' => [],
    ];
    public array $approvalComments = [];
    public ?string $message = null;
    public ?string $currentStaffUuid = null;
    public bool $canViewAllApprovals = false;
    public bool $canManageAllApprovals = false;
    public bool $canApproveAnyRequest = false;

    public bool $showCreateModal = false;
    public string $category = 'leave';
    public string $requesterUuid = '';
    public string $requesterName = '';
    public string $subject = '';
    public string $details = '';
    public ?int $leaveTypeId = null;
    public ?int $staffAssignmentId = null;
    public ?int $leaveClientSpaceId = null;
    public ?int $selectedLeaveSummaryAssignmentId = null;
    public ?string $leaveStartDate = null;
    public ?string $leaveEndDate = null;
    public ?string $requestedDays = null;

    public function mount(bool $leaveOnly = false): void
    {
        $this->leaveOnly = $leaveOnly;
        if ($this->leaveOnly) {
            $this->category = 'leave';
        }

        $org = Organization::current();
        $this->organizationId = $org?->id;
        $this->currentStaffUuid = Auth::user()?->staff_uuid;
        $this->canViewAllApprovals = Auth::user()?->canViewAllApprovals() ?? false;
        $this->canManageAllApprovals = Auth::user()?->canManageAllApprovals() ?? false;
        $this->canApproveAnyRequest = Auth::user()?->canEditHrApprovals() ?? false;
        $this->ensureCurrentStaffAssignment();
        $this->loadStaffOptions();
        $this->loadWorkflowCategories();
        $this->loadLeaveTypeOptions();
        $this->loadLeaveWorkingDayPreview();
        $this->syncLeaveSummaryAssignmentOptions();
        $this->loadRequests();
    }

    public function loadRequests(): void
    {
        if (!$this->organizationId) {
            $this->requests = [];
            return;
        }

        $this->requests = HrApprovalRequest::where('organization_id', $this->organizationId)
            ->when($this->leaveOnly, fn ($query) => $query->where('approval_category', 'leave'))
            ->when(!$this->canViewAllApprovals, function ($query) {
                $query->where(function ($requestQuery) {
                    $requestQuery
                        ->where('requester_staff_uuid', $this->currentStaffUuid)
                        ->orWhere(function ($approvalQuery) {
                            $approvalQuery->where('status', 'pending')
                                ->whereHas('steps', function ($stepQuery) {
                                    $stepQuery->where('status', 'pending')
                                        ->where('is_current', true)
                                        ->where('approver_staff_uuid', $this->currentStaffUuid);
                                });
                        });
                    });
            })
            ->with(['workflow', 'steps', 'events', 'leaveType', 'staffAssignment.organizationalUnit'])
            ->latest()
            ->limit(50)
            ->get()
            ->toArray();
    }

    public function loadWorkflowCategories(): void
    {
        if (!$this->organizationId) {
            $this->workflowCategories = [];
            return;
        }

        $query = ApprovalWorkflow::where('organization_id', $this->organizationId)
            ->where('is_active', true)
            ->whereHas('approvers')
            ->orderBy('approval_category');

        if ($this->leaveOnly) {
            $query->where('approval_category', 'leave');
        }

        $this->workflowCategories = $query
            ->pluck('approval_category')
            ->unique()
            ->values()
            ->all();

        if ($this->leaveOnly) {
            $this->category = 'leave';
            return;
        }

        if (!in_array($this->category, $this->workflowCategories, true) && !empty($this->workflowCategories)) {
            $this->category = $this->workflowCategories[0];
        }
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

    public function loadLeaveTypeOptions(): void
    {
        if (! $this->organizationId) {
            $this->leaveTypeOptions = [];
            $this->leaveTypeClientConfig = [];
            return;
        }

        $leaveTypes = LeaveType::query()
            ->where('organization_id', $this->organizationId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->values();

        $this->leaveTypeOptions = $leaveTypes
            ->mapWithKeys(fn (LeaveType $leaveType): array => [
                $leaveType->id => sprintf(
                    '%s (%s)%s',
                    $leaveType->name,
                    $leaveType->code,
                    $leaveType->session_type !== LeaveType::SESSION_FULL_DAY ? ' - '.$leaveType->sessionLabel() : ''
                ),
            ])
            ->toArray();

        $this->leaveTypeClientConfig = $leaveTypes
            ->mapWithKeys(fn (LeaveType $leaveType): array => [
                $leaveType->id => [
                    'daysPerWorkday' => $leaveType->deductionPerWorkday(),
                    'sessionLabel' => $leaveType->sessionLabel(),
                ],
            ])
            ->toArray();
    }

    public function openCreateModal(): void
    {
        $this->resetCreateForm();
        $this->loadWorkflowCategories();
        $this->loadLeaveTypeOptions();
        $this->loadLeaveWorkingDayPreview();

        $this->prefillRequesterContext();

        $this->showCreateModal = true;
    }

    public function submitRequest(): void
    {
        if ($this->leaveOnly) {
            $this->category = 'leave';
        }

        if ($this->leaveRequestsMustBelongToCurrentUser() && ! $this->currentStaffUuid) {
            $this->addError('requesterUuid', 'Your account must be linked to a staff UUID before you can apply for leave.');

            return;
        }

        $this->prefillRequesterContext();

        $rules = [
            'category' => 'required|in:leave,roster,coverage,offsite_duty',
            'requesterUuid' => 'required|string',
            'subject' => $this->category === 'leave' ? 'nullable|string|max:255' : 'required|string|max:255',
            'details' => 'nullable|string',
        ];

        if ($this->categoryUsesAssignmentDates()) {
            $rules['staffAssignmentId'] = 'required|integer';
            $rules['leaveStartDate'] = 'required|date';
            $rules['leaveEndDate'] = 'required|date|after_or_equal:leaveStartDate';
        }

        if ($this->categoryUsesWorkingDays()) {
            $rules['leaveTypeId'] = 'required|integer';
            $rules['leaveClientSpaceId'] = 'required|integer';
            $rules['requestedDays'] = 'required|numeric|min:0.25';
        }

        $this->validate($rules);

        if (!$this->organizationId) {
            $this->message = 'Create an organization before submitting approval requests.';
            return;
        }

        $leaveType = null;
        $staffAssignment = null;
        $selectedLeaveClientSpace = null;
        $approverOverrides = [];

        if ($this->categoryUsesAssignmentDates()) {
            $leaveType = LeaveType::query()
                ->where('organization_id', $this->organizationId)
                ->where('is_active', true)
                ->find($this->leaveTypeId);

            if ($this->categoryUsesWorkingDays() && ! $leaveType) {
                $this->addError('leaveTypeId', 'Select an active leave type for this organization.');
                return;
            }

            $staffAssignment = StaffAssignment::query()
                ->where('organization_id', $this->organizationId)
                ->where('staff_uuid', $this->requesterUuid)
                ->where('status', 'active')
                ->find($this->staffAssignmentId);

            if (! $staffAssignment) {
                $this->addError(
                    'staffAssignmentId',
                    $this->categoryUsesWorkingDays()
                        ? 'Select the requester assignment that this leave should block.'
                        : 'Select the requester assignment that this Official Workshop/Meeting request should cover.'
                );
                return;
            }

            if ($this->categoryUsesWorkingDays()) {
                $selectedLeaveClientSpace = $this->resolveSelectedLeaveClientSpace($staffAssignment);

                if (! $selectedLeaveClientSpace) {
                    $this->addError('leaveClientSpaceId', 'Select a valid client space for this leave request.');
                    return;
                }

                if (! ($staffAssignment->organizationalUnit?->isClientSpace() ?? false)) {
                    $primaryApprover = $this->directLeaveApproverForClientSpace($selectedLeaveClientSpace);

                    if (! $primaryApprover) {
                        $this->addError('leaveClientSpaceId', 'The selected client space does not have a direct superior approver yet.');
                        return;
                    }

                    $approverOverrides['primary'] = [$primaryApprover];
                }
            }
        }

        $workflow = ApprovalWorkflow::where('organization_id', $this->organizationId)
            ->where('approval_category', $this->category)
            ->where('is_active', true)
            ->when(
                $this->categoryUsesWorkingDays(),
                fn ($query) => $query->where('organizational_unit_id', $selectedLeaveClientSpace?->id),
                fn ($query) => $query
            )
            ->with('approvers')
            ->first();

        if (! $workflow) {
            $this->message = $this->categoryUsesWorkingDays()
                ? 'Configure an active leave approval workflow for the selected client space first.'
                : 'Configure an active approval workflow for this category first.';
            return;
        }

        $subject = $this->buildSubmissionSubject($leaveType);

        try {
            HrApprovalRequest::submitFromWorkflow($workflow, [
                'requester_staff_uuid' => $this->requesterUuid,
                'requester_name' => $this->staffOptions[$this->requesterUuid] ?? $this->requesterName ?: $this->requesterUuid,
                'subject' => $subject,
                'details' => $this->details ?: null,
                'leave_type_id' => $this->categoryUsesWorkingDays() ? $leaveType?->id : null,
                'staff_assignment_id' => $staffAssignment?->id,
                'start_date' => $this->categoryUsesAssignmentDates() ? $this->leaveStartDate : null,
                'end_date' => $this->categoryUsesAssignmentDates() ? $this->leaveEndDate : null,
                'requested_days' => $this->categoryUsesWorkingDays() ? $this->requestedDays : null,
                'approver_overrides' => $approverOverrides,
            ]);
        } catch (ValidationException $exception) {
            $fieldMap = [
                'leave_type_id' => 'leaveTypeId',
                'staff_assignment_id' => 'staffAssignmentId',
                'organizational_unit_id' => 'leaveClientSpaceId',
                'start_date' => 'leaveStartDate',
                'end_date' => 'leaveEndDate',
                'requested_days' => 'requestedDays',
            ];

            foreach ($exception->errors() as $field => $messages) {
                $field = $fieldMap[$field] ?? $field;

                foreach ((array) $messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        } catch (RuntimeException $exception) {
            $this->message = $exception->getMessage();
            return;
        }

        $this->showCreateModal = false;
        $this->resetCreateForm();
        $this->message = 'Approval request submitted.';
        $this->loadRequests();
    }

    public function approveRequest(int $requestId): void
    {
        try {
            $request = $this->requestAvailableForAction($requestId);
            $request->approveCurrentStep(null, $this->approvalComments[$requestId] ?? null, $this->currentStaffUuid);
        } catch (RuntimeException $exception) {
            $this->message = $exception->getMessage();
            $this->loadRequests();
            return;
        }

        unset($this->approvalComments[$requestId]);

        $this->message = 'Approval recorded.';
        $this->loadRequests();
    }

    public function rejectRequest(int $requestId): void
    {
        try {
            $request = $this->requestAvailableForAction($requestId);
            $request->rejectCurrentStep(null, $this->approvalComments[$requestId] ?? null, $this->currentStaffUuid);
        } catch (RuntimeException $exception) {
            $this->message = $exception->getMessage();
            $this->loadRequests();
            return;
        }

        unset($this->approvalComments[$requestId]);

        $this->message = 'Rejection recorded.';
        $this->loadRequests();
    }

    protected function requestAvailableForAction(int $requestId): HrApprovalRequest
    {
        $request = HrApprovalRequest::where('organization_id', $this->organizationId)->findOrFail($requestId);
        $currentSteps = $request->currentSteps();
        $currentStep = $currentSteps->first() ?? $request->currentStep();

        if (!$currentStep || $request->status !== 'pending') {
            throw new RuntimeException('This request is no longer pending approval.');
        }

        if (
            !$this->canApproveAnyRequest
            && !$currentSteps->contains(fn ($step) => $step->approver_staff_uuid === $this->currentStaffUuid)
        ) {
            throw new RuntimeException('This request is assigned to another approver.');
        }

        return $request;
    }

    public function updatedRequesterUuid(string $uuid): void
    {
        if ($this->leaveRequestsMustBelongToCurrentUser()) {
            $this->requesterUuid = $this->currentStaffUuid ?? '';
            $this->requesterName = $this->staffOptions[$this->requesterUuid] ?? Auth::user()?->name ?? '';
            $this->syncRequesterAssignmentOptions($this->requesterUuid);

            return;
        }

        $this->requesterName = $this->staffOptions[$uuid] ?? '';
        $this->syncRequesterAssignmentOptions($uuid);
    }

    public function updatedCategory(string $category): void
    {
        if ($this->leaveOnly) {
            $this->category = 'leave';
            return;
        }

        if ($category === 'leave') {
            $this->subject = '';
            $this->details = '';
        }

        if ($this->categoryUsesAssignmentDates($category)) {
            $requesterUuid = $this->leaveRequestsMustBelongToCurrentUser($category)
                ? $this->currentStaffUuid
                : ($this->requesterUuid ?: $this->currentStaffUuid);

            if ($requesterUuid) {
                if ($this->leaveRequestsMustBelongToCurrentUser($category)) {
                    $this->requesterUuid = $requesterUuid;
                    $this->requesterName = $this->staffOptions[$requesterUuid] ?? Auth::user()?->name ?? '';
                }
                $this->syncRequesterAssignmentOptions($requesterUuid);
            }

            return;
        }

        $this->leaveTypeId = null;
        $this->staffAssignmentId = null;
        $this->leaveClientSpaceId = null;
        $this->leaveStartDate = null;
        $this->leaveEndDate = null;
        $this->requestedDays = null;
        $this->requesterAssignmentOptions = [];
        $this->leaveClientSpaceOptions = [];
    }

    public function updatedLeaveStartDate(): void
    {
        $this->syncRequestedDaysFromDates();
    }

    public function updatedLeaveEndDate(): void
    {
        $this->syncRequestedDaysFromDates();
    }

    public function updatedLeaveTypeId(): void
    {
        $this->syncRequestedDaysFromDates();
    }

    public function updatedStaffAssignmentId($value): void
    {
        $this->staffAssignmentId = $value !== null && $value !== '' ? (int) $value : null;
        $this->syncLeaveClientSpaceOptions();
    }

    public function updatedSelectedLeaveSummaryAssignmentId(): void
    {
        if ($this->selectedLeaveSummaryAssignmentId !== null) {
            $this->selectedLeaveSummaryAssignmentId = (int) $this->selectedLeaveSummaryAssignmentId;
        }
    }

    public function resetCreateForm(): void
    {
        $this->category = $this->leaveOnly ? 'leave' : ($this->workflowCategories[0] ?? 'leave');
        $this->requesterUuid = '';
        $this->requesterName = '';
        $this->subject = '';
        $this->details = '';
        $this->leaveTypeId = null;
        $this->staffAssignmentId = null;
        $this->leaveClientSpaceId = null;
        $this->leaveStartDate = null;
        $this->leaveEndDate = null;
        $this->requestedDays = null;
        $this->requesterAssignmentOptions = [];
        $this->leaveClientSpaceOptions = [];
        $this->message = null;
    }

    public function render(): View
    {
        return view('livewire.approval-request-queue', [
            'selectedLeaveTypeSummary' => $this->selectedLeaveTypeSummary(),
            'individualLeaveSummary' => $this->individualLeaveSummary(),
        ]);
    }

    private function loadLeaveWorkingDayPreview(): void
    {
        $this->leaveWorkingDayPreview = [
            'weekendDays' => [0, 6],
            'holidayDates' => [],
            'recurringHolidayTokens' => [],
        ];

        if (! $this->organizationId) {
            return;
        }

        $organization = Organization::query()->find($this->organizationId);

        if (! $organization) {
            return;
        }

        $this->leaveWorkingDayPreview = app(WorkingDayCalculator::class)->previewConfig($organization);
    }

    private function buildSubmissionSubject(?LeaveType $leaveType): string
    {
        if ($this->category !== 'leave') {
            return trim($this->subject);
        }

        $leaveTypeLabel = trim($leaveType?->name ?? 'Leave');
        $startDate = $this->leaveStartDate ? Carbon::parse($this->leaveStartDate)->format('M j, Y') : null;
        $endDate = $this->leaveEndDate ? Carbon::parse($this->leaveEndDate)->format('M j, Y') : null;

        if ($startDate && $endDate) {
            $dateLabel = $startDate === $endDate
                ? $startDate
                : "{$startDate} to {$endDate}";

            return "{$leaveTypeLabel}: {$dateLabel}";
        }

        return $leaveTypeLabel;
    }

    private function prefillRequesterContext(): void
    {
        if ($this->leaveRequestsMustBelongToCurrentUser()) {
            $this->requesterUuid = $this->currentStaffUuid ?? '';
            $this->requesterName = $this->staffOptions[$this->requesterUuid] ?? Auth::user()?->name ?? '';
        } elseif (! $this->canManageAllApprovals && $this->currentStaffUuid) {
            $this->requesterUuid = $this->currentStaffUuid;
            $this->requesterName = $this->staffOptions[$this->currentStaffUuid] ?? Auth::user()?->name ?? '';
        }

        if ($this->categoryUsesAssignmentDates() && $this->requesterUuid) {
            $this->syncRequesterAssignmentOptions($this->requesterUuid);
        }
    }

    private function ensureCurrentStaffAssignment(): void
    {
        if (! $this->organizationId || ! $this->currentStaffUuid) {
            return;
        }

        $user = Auth::user();

        $assignment = StaffAssignment::query()
            ->where('organization_id', $this->organizationId)
            ->where('staff_uuid', $this->currentStaffUuid)
            ->first();

        if ($assignment) {
            if ($assignment->status === 'pending_routing') {
                $assignment->forceFill(['status' => 'active'])->save();
            }

            return;
        }

        try {
            $staff = app(KashApiService::class)->getStaffByUuid($this->currentStaffUuid);
        } catch (\Throwable) {
            $staff = null;
        }

        $staffName = is_array($staff)
            ? (StaffRecordData::name($staff) ?? $user?->name)
            : $user?->name;

        if (! $staffName) {
            return;
        }

        StaffAssignment::query()->create([
            'organization_id' => $this->organizationId,
            'staff_uuid' => $this->currentStaffUuid,
            'staff_name' => $staffName,
            'staff_cadre' => is_array($staff) ? StaffRecordData::cadre($staff) : null,
            'staff_department' => is_array($staff) ? StaffRecordData::department($staff) : null,
            'staff_title' => is_array($staff) ? StaffRecordData::title($staff) : null,
            'home_branch_external_id' => is_array($staff) ? (StaffRecordData::branchExternalId($staff) ?? '') : '',
            'home_branch_name' => is_array($staff) ? StaffRecordData::branchName($staff) : null,
            'assignment_type' => 'primary',
            'status' => 'active',
            'assigned_at' => now(),
        ]);
    }

    private function syncRequesterAssignmentOptions(?string $staffUuid): void
    {
        $this->requesterAssignmentOptions = [];

        if (! $this->organizationId || ! $staffUuid) {
            $this->staffAssignmentId = null;
            $this->leaveClientSpaceId = null;
            $this->leaveClientSpaceOptions = [];
            return;
        }

        $options = StaffAssignment::query()
            ->where('organization_id', $this->organizationId)
            ->where('staff_uuid', $staffUuid)
            ->where('status', 'active')
            ->with('organizationalUnit')
            ->orderBy('staff_name')
            ->get()
            ->mapWithKeys(fn (StaffAssignment $assignment): array => [
                $assignment->id => $this->staffAssignmentOptionLabel($assignment),
            ])
            ->toArray();

        $this->requesterAssignmentOptions = $options;

        if (! array_key_exists((int) $this->staffAssignmentId, $options)) {
            $this->staffAssignmentId = count($options) === 1
                ? (int) array_key_first($options)
                : null;
        }

        $this->syncLeaveClientSpaceOptions();
    }

    private function syncLeaveClientSpaceOptions(): void
    {
        $this->leaveClientSpaceOptions = [];

        if (! $this->categoryUsesWorkingDays() || ! $this->organizationId || ! $this->staffAssignmentId) {
            $this->leaveClientSpaceId = null;
            return;
        }

        $assignment = StaffAssignment::query()
            ->where('organization_id', $this->organizationId)
            ->with([
                'organizationalUnit.linkedClientSpaces',
                'organizationalUnit.parent',
                'clientSpaceStaffAssignments' => fn ($query) => $query
                    ->where('status', HrClientSpaceStaffAssignment::STATUS_ACTIVE)
                    ->with('clientSpace'),
            ])
            ->find($this->staffAssignmentId);

        if (! $assignment) {
            $this->leaveClientSpaceId = null;
            return;
        }

        $options = $this->leaveClientSpacesForAssignment($assignment)
            ->mapWithKeys(fn (HrOrganizationalUnit $clientSpace): array => [$clientSpace->id => $clientSpace->name])
            ->toArray();

        $this->leaveClientSpaceOptions = $options;

        if (! array_key_exists((int) $this->leaveClientSpaceId, $options)) {
            $this->leaveClientSpaceId = count($options) === 1
                ? (int) array_key_first($options)
                : null;
        }
    }

    private function leaveClientSpacesForAssignment(StaffAssignment $assignment): Collection
    {
        $clientSpaces = collect();
        $unit = $assignment->organizationalUnit;

        if ($unit?->isClientSpace()) {
            $clientSpaces->push($unit);
        }

        if ($unit?->isRoutingNode()) {
            $linkedClientSpaces = $unit->relationLoaded('linkedClientSpaces')
                ? $unit->linkedClientSpaces
                : $unit->linkedClientSpaces()->get();

            $clientSpaces = $clientSpaces->merge($linkedClientSpaces);
        }

        $activeClientSpaceAssignments = $assignment->relationLoaded('clientSpaceStaffAssignments')
            ? $assignment->clientSpaceStaffAssignments
            : $assignment->clientSpaceStaffAssignments()
                ->where('status', HrClientSpaceStaffAssignment::STATUS_ACTIVE)
                ->with('clientSpace')
                ->get();

        return $clientSpaces
            ->merge($activeClientSpaceAssignments->pluck('clientSpace'))
            ->filter(fn ($clientSpace): bool => $clientSpace instanceof HrOrganizationalUnit && $clientSpace->isClientSpace())
            ->unique('id')
            ->sortBy(fn (HrOrganizationalUnit $clientSpace): string => mb_strtolower($clientSpace->name ?? ''))
            ->values();
    }

    private function resolveSelectedLeaveClientSpace(StaffAssignment $assignment): ?HrOrganizationalUnit
    {
        if (! $this->leaveClientSpaceId) {
            return null;
        }

        return $this->leaveClientSpacesForAssignment($assignment)
            ->first(fn (HrOrganizationalUnit $clientSpace): bool => (int) $clientSpace->id === (int) $this->leaveClientSpaceId);
    }

    private function directLeaveApproverForClientSpace(HrOrganizationalUnit $clientSpace): ?array
    {
        $clientSpace->loadMissing([
            'parent.staffAssignments',
            'routingParents.staffAssignments',
        ]);

        $superiorUnit = $clientSpace->parent;

        if (! $superiorUnit && $clientSpace->relationLoaded('routingParents')) {
            $superiorUnit = $clientSpace->routingParents
                ->first(fn (HrOrganizationalUnit $unit): bool => $unit->isRoutingNode());
        }

        if (! $superiorUnit) {
            return null;
        }

        if ($superiorUnit->head_staff_uuid) {
            return [
                'uuid' => (string) $superiorUnit->head_staff_uuid,
                'name' => $superiorUnit->head_name ?: (string) $superiorUnit->head_staff_uuid,
            ];
        }

        $fallbackAssignment = $superiorUnit->relationLoaded('staffAssignments')
            ? $superiorUnit->staffAssignments
                ->whereNotIn('status', ['inactive', 'orphaned'])
                ->filter(fn (StaffAssignment $assignment): bool => filled($assignment->staff_uuid))
                ->sortBy(fn (StaffAssignment $assignment): string => mb_strtolower($assignment->staff_name ?? ''))
                ->first()
            : $superiorUnit->staffAssignments()
                ->whereNotIn('status', ['inactive', 'orphaned'])
                ->whereNotNull('staff_uuid')
                ->orderBy('staff_name')
                ->first();

        if (! $fallbackAssignment?->staff_uuid) {
            return null;
        }

        return [
            'uuid' => (string) $fallbackAssignment->staff_uuid,
            'name' => $fallbackAssignment->staff_name ?: (string) $fallbackAssignment->staff_uuid,
        ];
    }

    private function syncLeaveSummaryAssignmentOptions(): void
    {
        $this->leaveSummaryAssignmentOptions = [];

        if (! $this->leaveOnly || ! $this->organizationId || ! $this->currentStaffUuid) {
            $this->selectedLeaveSummaryAssignmentId = null;
            return;
        }

        $options = StaffAssignment::query()
            ->where('organization_id', $this->organizationId)
            ->where('staff_uuid', $this->currentStaffUuid)
            ->where('status', 'active')
            ->with('organizationalUnit')
            ->orderBy('staff_name')
            ->get()
            ->mapWithKeys(fn (StaffAssignment $assignment): array => [
                $assignment->id => $this->staffAssignmentOptionLabel($assignment),
            ])
            ->toArray();

        $this->leaveSummaryAssignmentOptions = $options;

        if ($this->selectedLeaveSummaryAssignmentId === null || ! array_key_exists((int) $this->selectedLeaveSummaryAssignmentId, $options)) {
            $this->selectedLeaveSummaryAssignmentId = count($options) > 0
                ? (int) array_key_first($options)
                : null;
        }
    }

    private function syncRequestedDaysFromDates(): void
    {
        if (! $this->categoryUsesWorkingDays()) {
            $this->requestedDays = null;
            return;
        }

        if (! $this->leaveStartDate || ! $this->leaveEndDate) {
            return;
        }

        try {
            $start = Carbon::parse($this->leaveStartDate)->startOfDay();
            $end = Carbon::parse($this->leaveEndDate)->startOfDay();
        } catch (\Throwable) {
            return;
        }

        if ($end->lt($start)) {
            return;
        }

        $organization = $this->organizationId
            ? Organization::query()->find($this->organizationId)
            : null;

        if (! $organization) {
            return;
        }

        $days = (float) app(WorkingDayCalculator::class)->count($organization, $start, $end);
        $leaveType = $this->selectedLeaveType();
        $requestedDays = $leaveType?->requestedDaysForWorkingDays($days) ?? $days;
        $this->requestedDays = $this->formatDayValue($requestedDays);
    }

    private function staffAssignmentOptionLabel(StaffAssignment $assignment): string
    {
        return collect([
            $assignment->staff_name ?: $assignment->staff_uuid,
            $assignment->staff_title ?: null,
            $assignment->organizationalUnit?->name ?: null,
        ])->filter()->implode(' / ');
    }

    private function categoryUsesAssignmentDates(?string $category = null): bool
    {
        return in_array($category ?? $this->category, ['leave', 'offsite_duty'], true);
    }

    private function categoryUsesWorkingDays(?string $category = null): bool
    {
        return ($category ?? $this->category) === 'leave';
    }

    private function leaveRequestsMustBelongToCurrentUser(?string $category = null): bool
    {
        return $this->categoryUsesWorkingDays($category);
    }

    private function selectedLeaveType(): ?LeaveType
    {
        if (! $this->organizationId || ! $this->leaveTypeId) {
            return null;
        }

        return LeaveType::query()
            ->where('organization_id', $this->organizationId)
            ->where('is_active', true)
            ->find($this->leaveTypeId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function selectedLeaveTypeSummary(): ?array
    {
        if (! $this->categoryUsesWorkingDays()) {
            return null;
        }

        $leaveType = $this->selectedLeaveType();

        if (! $leaveType) {
            return null;
        }

        $requestedDays = $this->requestedDays !== null ? (float) $this->requestedDays : null;
        $entitledDays = $leaveType->max_days_per_year !== null
            ? $this->formatDayValue((float) $leaveType->max_days_per_year)
            : null;
        $usedDays = $this->usedDaysForSelectedLeaveType($leaveType);
        $remainingDays = null;

        if ($leaveType->tracks_balance && $leaveType->max_days_per_year !== null && $usedDays !== null) {
            $remainingDays = max(0, (float) $leaveType->max_days_per_year - $usedDays - (float) ($requestedDays ?? 0));
        }

        return [
            'code' => $leaveType->code,
            'session_label' => $leaveType->sessionLabel(),
            'deduction_label' => $this->formatDayValue($leaveType->deductionPerWorkday()).' day(s) per working day',
            'is_paid' => $leaveType->is_paid,
            'tracks_balance' => $leaveType->tracks_balance,
            'entitled_days' => $entitledDays,
            'requested_days' => $requestedDays !== null ? $this->formatDayValue($requestedDays) : null,
            'remaining_days' => $remainingDays !== null ? $this->formatDayValue($remainingDays) : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function individualLeaveSummary(): ?array
    {
        if (! $this->leaveOnly || ! $this->organizationId || ! $this->selectedLeaveSummaryAssignmentId) {
            return null;
        }

        $assignment = StaffAssignment::query()
            ->where('organization_id', $this->organizationId)
            ->with('organizationalUnit')
            ->find($this->selectedLeaveSummaryAssignmentId);

        if (! $assignment) {
            return null;
        }

        $leaveTypes = LeaveType::query()
            ->where('organization_id', $this->organizationId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $leaveRequests = HrApprovalRequest::query()
            ->where('organization_id', $this->organizationId)
            ->where('approval_category', 'leave')
            ->where('staff_assignment_id', $assignment->id)
            ->whereIn('status', ['pending', 'approved'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        $rows = $leaveTypes->map(function (LeaveType $leaveType) use ($leaveRequests, $assignment): array {
            $requestsForType = $leaveRequests->where('leave_type_id', $leaveType->id)->values();
            $latestRequest = $requestsForType->first();
            $year = $latestRequest?->start_date?->year ?? now()->year;
            $usedDays = $this->usedDaysForLeaveType(
                $leaveType,
                (int) $assignment->id,
                (int) $year
            );
            $latestRequestedDays = $latestRequest ? (float) $latestRequest->requested_days : null;
            $entitledDays = null;
            $balanceDays = null;

            if ($leaveType->tracks_balance && $leaveType->max_days_per_year !== null) {
                $balanceDays = max(0, (float) $leaveType->max_days_per_year - $usedDays);
                $entitledDays = $latestRequestedDays !== null
                    ? min((float) $leaveType->max_days_per_year, $balanceDays + $latestRequestedDays)
                    : max(0, (float) $leaveType->max_days_per_year - $usedDays);
            }

            return [
                'leave_type' => $leaveType->name,
                'leave_type_classes' => $this->leaveTypeToneClasses($leaveType),
                'code' => $leaveType->code,
                'start_date' => $latestRequest?->start_date?->format('d/m/Y'),
                'end_date' => $latestRequest?->end_date?->format('d/m/Y'),
                'entitled_days' => $entitledDays !== null ? $this->formatDayValue($entitledDays) : '',
                'requested_days' => $latestRequestedDays !== null ? $this->formatDayValue($latestRequestedDays) : '',
                'balance_days' => $balanceDays !== null ? $this->formatDayValue($balanceDays) : '',
            ];
        })->all();

        return [
            'staff_name' => $assignment->staff_name,
            'assignment_label' => $this->staffAssignmentOptionLabel($assignment),
            'rows' => $rows,
        ];
    }

    private function usedDaysForSelectedLeaveType(LeaveType $leaveType): ?float
    {
        if (! $this->organizationId || ! $this->staffAssignmentId) {
            return null;
        }

        $year = null;

        if ($this->leaveStartDate) {
            try {
                $year = Carbon::parse($this->leaveStartDate)->year;
            } catch (\Throwable) {
                $year = null;
            }
        }

        $year ??= now()->year;

        return $this->usedDaysForLeaveType($leaveType, (int) $this->staffAssignmentId, (int) $year);
    }

    private function usedDaysForLeaveType(LeaveType $leaveType, int $staffAssignmentId, int $year): float
    {
        return (float) HrApprovalRequest::query()
            ->where('organization_id', $this->organizationId)
            ->where('approval_category', 'leave')
            ->whereIn('leave_type_id', $leaveType->groupedLeaveTypeIds())
            ->where('staff_assignment_id', $staffAssignmentId)
            ->whereIn('status', ['pending', 'approved'])
            ->whereYear('start_date', $year)
            ->sum('requested_days');
    }

    private function formatDayValue(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /**
     * @return array{cell: string, text: string}
     */
    private function leaveTypeToneClasses(LeaveType $leaveType): array
    {
        $name = strtolower($leaveType->name);
        $code = strtolower($leaveType->code);

        if (str_contains($name, 'annual') || str_starts_with($code, 'l')) {
            return ['cell' => 'bg-emerald-500', 'text' => 'text-white'];
        }

        if (str_contains($name, 'sick') || str_starts_with($code, 's')) {
            return ['cell' => 'bg-orange-500', 'text' => 'text-white'];
        }

        if (
            str_contains($name, 'maternity')
            || str_contains($name, 'paternity')
            || str_contains($name, 'compassion')
            || str_contains($name, 'upa')
            || in_array($code, ['p', 'c', 'u'], true)
        ) {
            return ['cell' => 'bg-sky-500', 'text' => 'text-white'];
        }

        if (str_contains($name, 'work from home') || $code === 'w') {
            return ['cell' => 'bg-amber-300', 'text' => 'text-gray-900'];
        }

        if (
            str_contains($name, 'without pay')
            || str_contains($name, 'unpaid')
            || str_starts_with($code, 'r')
        ) {
            return ['cell' => 'bg-violet-600', 'text' => 'text-white'];
        }

        return ['cell' => 'bg-gray-300', 'text' => 'text-gray-900'];
    }
}

