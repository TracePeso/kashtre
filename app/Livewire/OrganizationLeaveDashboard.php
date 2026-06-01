<?php

namespace App\Livewire;

use App\Models\HrApprovalRequest;
use App\Models\LeaveType;
use App\Models\Organization;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use RuntimeException;

class OrganizationLeaveDashboard extends Component
{
    public ?int $organizationId = null;
    public ?string $currentStaffUuid = null;
    public bool $canApproveAnyRequest = false;
    public string $statusFilter = 'all';
    public string $search = '';
    public string $leaveTypeFilter = '';
    public array $approvalComments = [];
    public ?string $message = null;

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user?->canViewHrApprovals(), 403);

        $organization = Organization::current();

        $this->organizationId = $organization?->id;
        $this->currentStaffUuid = $user?->staff_uuid;
        $this->canApproveAnyRequest = $user?->canEditHrApprovals() ?? false;
    }

    public function approveRequest(int $requestId): void
    {
        try {
            $request = $this->requestAvailableForAction($requestId);
            $request->approveCurrentStep(null, $this->approvalComments[$requestId] ?? null, $this->currentStaffUuid);
        } catch (RuntimeException $exception) {
            $this->message = $exception->getMessage();

            return;
        }

        unset($this->approvalComments[$requestId]);

        $this->message = 'Approval recorded.';
    }

    public function rejectRequest(int $requestId): void
    {
        try {
            $request = $this->requestAvailableForAction($requestId);
            $request->rejectCurrentStep(null, $this->approvalComments[$requestId] ?? null, $this->currentStaffUuid);
        } catch (RuntimeException $exception) {
            $this->message = $exception->getMessage();

            return;
        }

        unset($this->approvalComments[$requestId]);

        $this->message = 'Rejection recorded.';
    }

    public function clearFilters(): void
    {
        $this->statusFilter = 'all';
        $this->search = '';
        $this->leaveTypeFilter = '';
    }

    public function render(): View
    {
        return view('livewire.organization-leave-dashboard', [
            'stats' => $this->summaryStats(),
            'requests' => $this->requests(),
            'leaveTypeOptions' => $this->leaveTypeOptions(),
        ]);
    }

    protected function requestAvailableForAction(int $requestId): HrApprovalRequest
    {
        $request = HrApprovalRequest::query()
            ->where('organization_id', $this->organizationId)
            ->where('approval_category', 'leave')
            ->with('steps')
            ->findOrFail($requestId);

        $currentSteps = $request->currentSteps();
        $currentStep = $currentSteps->first() ?? $request->currentStep();

        if (! $currentStep || $request->status !== 'pending') {
            throw new RuntimeException('This leave request is no longer pending approval.');
        }

        if (
            ! $this->canApproveAnyRequest
            && ! $currentSteps->contains(fn ($step) => $step->approver_staff_uuid === $this->currentStaffUuid)
        ) {
            throw new RuntimeException('This leave request is assigned to another approver.');
        }

        return $request;
    }

    /**
     * @return array<string, int|string>
     */
    private function summaryStats(): array
    {
        if (! $this->organizationId) {
            return [
                'pending_count' => 0,
                'approved_count' => 0,
                'pending_days' => '0',
                'away_today_count' => 0,
            ];
        }

        $baseQuery = $this->baseLeaveQuery();
        $today = now()->toDateString();

        return [
            'pending_count' => (clone $baseQuery)->where('status', 'pending')->count(),
            'approved_count' => (clone $baseQuery)->where('status', 'approved')->count(),
            'pending_days' => $this->formatDayValue((float) ((clone $baseQuery)->where('status', 'pending')->sum('requested_days'))),
            'away_today_count' => (clone $baseQuery)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function requests(): array
    {
        if (! $this->organizationId) {
            return [];
        }

        return $this->filteredLeaveQuery()
            ->with([
                'leaveType',
                'staffAssignment.organizationalUnit',
                'steps',
                'events' => fn ($query) => $query->latest()->limit(6),
            ])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'approved' THEN 1 ELSE 2 END")
            ->orderBy('start_date')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function (HrApprovalRequest $request): array {
                $currentSteps = $request->currentSteps();
                $currentStep = $currentSteps->first() ?? $request->currentStep();
                $currentApproverNames = $currentSteps->pluck('approver_name')->filter()->unique()->values();
                $waitingLabel = $currentApproverNames->isNotEmpty()
                    ? $currentApproverNames->join(', ', ' or ')
                    : ($currentStep?->approver_name ?? 'the next approver');
                $canAct = $request->status === 'pending'
                    && $currentStep
                    && (
                        $this->canApproveAnyRequest
                        || $currentSteps->contains(fn ($step) => $step->approver_staff_uuid === $this->currentStaffUuid)
                    );

                return [
                    'id' => $request->id,
                    'subject' => $request->subject,
                    'requester_name' => $request->requester_name,
                    'details' => $request->details,
                    'status' => $request->status,
                    'current_level' => $request->current_level,
                    'start_date' => $request->start_date?->toDateString(),
                    'end_date' => $request->end_date?->toDateString(),
                    'requested_days' => $request->requested_days !== null
                        ? $this->formatDayValue((float) $request->requested_days)
                        : null,
                    'submitted_at' => $request->submitted_at?->toDateTimeString(),
                    'leave_type' => [
                        'name' => $request->leaveType?->name,
                        'code' => $request->leaveType?->code,
                    ],
                    'staff_assignment' => [
                        'staff_name' => $request->staffAssignment?->staff_name,
                        'staff_title' => $request->staffAssignment?->staff_title,
                        'organizational_unit' => [
                            'name' => $request->staffAssignment?->organizationalUnit?->name,
                        ],
                    ],
                    'waiting_label' => $waitingLabel,
                    'can_act' => $canAct,
                    'steps' => $request->steps
                        ->map(fn ($step): array => [
                            'approver_level' => $step->approver_level,
                            'approver_name' => $step->approver_name,
                            'approver_staff_uuid' => $step->approver_staff_uuid,
                            'status' => $step->status,
                            'is_current' => (bool) $step->is_current,
                            'comments' => $step->comments,
                        ])
                        ->all(),
                    'events' => $request->events
                        ->map(fn ($event): array => [
                            'action' => $event->action,
                            'actor_name' => $event->actor_name,
                            'comments' => $event->comments,
                            'created_at' => $event->created_at?->toDateTimeString(),
                        ])
                        ->all(),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function leaveTypeOptions(): array
    {
        if (! $this->organizationId) {
            return [];
        }

        return LeaveType::query()
            ->where('organization_id', $this->organizationId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (LeaveType $leaveType): array => [
                $leaveType->id => sprintf('%s (%s)', $leaveType->name, $leaveType->code),
            ])
            ->toArray();
    }

    private function baseLeaveQuery(): Builder
    {
        return HrApprovalRequest::query()
            ->where('organization_id', $this->organizationId)
            ->where('approval_category', 'leave');
    }

    private function filteredLeaveQuery(): Builder
    {
        $query = $this->baseLeaveQuery()
            ->whereIn('status', ['pending', 'approved']);

        if (in_array($this->statusFilter, ['pending', 'approved'], true)) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->leaveTypeFilter !== '') {
            $query->where('leave_type_id', (int) $this->leaveTypeFilter);
        }

        $search = trim($this->search);

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $like = '%'.$search.'%';

                $searchQuery
                    ->where('subject', 'like', $like)
                    ->orWhere('requester_name', 'like', $like)
                    ->orWhereHas('leaveType', function (Builder $leaveTypeQuery) use ($like): void {
                        $leaveTypeQuery
                            ->where('name', 'like', $like)
                            ->orWhere('code', 'like', $like);
                    })
                    ->orWhereHas('staffAssignment', function (Builder $assignmentQuery) use ($like): void {
                        $assignmentQuery
                            ->where('staff_name', 'like', $like)
                            ->orWhere('staff_title', 'like', $like)
                            ->orWhereHas('organizationalUnit', function (Builder $unitQuery) use ($like): void {
                                $unitQuery->where('name', 'like', $like);
                            });
                    });
            });
        }

        return $query;
    }

    private function formatDayValue(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
