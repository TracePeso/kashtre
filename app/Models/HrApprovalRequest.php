<?php

namespace App\Models;

use App\Services\OpenShiftService;
use App\Services\WorkingDayCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use RuntimeException;

class HrApprovalRequest extends Model
{
    use SoftDeletes;

    protected $table = 'hr_approval_requests';

    protected $fillable = [
        'uuid',
        'organization_id',
        'approval_workflow_id',
        'approval_category',
        'linked_roster_id',
        'leave_type_id',
        'staff_assignment_id',
        'requester_staff_uuid',
        'requester_name',
        'subject',
        'details',
        'start_date',
        'end_date',
        'requested_days',
        'status',
        'current_level',
        'submitted_at',
        'completed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'requested_days' => 'decimal:2',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
            $model->submitted_at = $model->submitted_at ?? now();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function linkedRoster()
    {
        return $this->belongsTo(HrDutyRoster::class, 'linked_roster_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function staffAssignment()
    {
        return $this->belongsTo(StaffAssignment::class, 'staff_assignment_id');
    }

    public function linkedUnavailability()
    {
        return $this->hasOne(HrStaffUnavailability::class, 'approval_request_id');
    }

    public function steps()
    {
        return $this->hasMany(HrApprovalStep::class, 'approval_request_id')
            ->orderByRaw("CASE approver_level WHEN 'primary' THEN 1 WHEN 'secondary' THEN 2 WHEN 'tertiary' THEN 3 ELSE 4 END")
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function events()
    {
        return $this->hasMany(HrApprovalEvent::class, 'approval_request_id')->latest();
    }

    public function pendingSteps()
    {
        return $this->steps()->where('status', 'pending');
    }

    public function currentStep()
    {
        return $this->pendingSteps()->where('is_current', true)->first()
            ?? $this->pendingSteps()->first();
    }

    public function currentSteps()
    {
        return $this->pendingSteps()->where('is_current', true)->get();
    }

    public static function submitFromWorkflow(ApprovalWorkflow $workflow, array $data): self
    {
        $approverOverrides = is_array($data['approver_overrides'] ?? null)
            ? $data['approver_overrides']
            : [];
        $validated = self::validateSubmissionData($workflow, $data);

        return DB::transaction(function () use ($workflow, $validated, $approverOverrides) {
            $workflowApprovers = $workflow->approvers()->get();

            if ($workflowApprovers->isEmpty()) {
                throw new RuntimeException('The selected workflow has no approvers.');
            }

            $invalidLevels = collect(['primary', 'secondary', 'tertiary'])
                ->filter(function (string $level) use ($workflowApprovers, $approverOverrides): bool {
                    $overrideCount = collect($approverOverrides[$level] ?? [])
                        ->filter(fn ($approver): bool => filled($approver['uuid'] ?? null))
                        ->count();

                    if ($level === 'primary' && $overrideCount > 0) {
                        return $overrideCount < 1;
                    }

                    return $workflowApprovers->where('approver_level', $level)->count() < 3;
                })
                ->values();

            if ($invalidLevels->isNotEmpty()) {
                throw new RuntimeException('The selected workflow must include at least 3 approvers at each approval level, unless the primary level is being overridden by the direct-superior leave rule.');
            }

            $request = self::create([
                'organization_id' => $workflow->organization_id,
                'approval_workflow_id' => $workflow->id,
                'approval_category' => $workflow->approval_category,
                'linked_roster_id' => $validated['linked_roster_id'] ?? null,
                'leave_type_id' => $validated['leave_type_id'] ?? null,
                'staff_assignment_id' => $validated['staff_assignment_id'] ?? null,
                'requester_staff_uuid' => $validated['requester_staff_uuid'],
                'requester_name' => $validated['requester_name'],
                'subject' => $validated['subject'],
                'details' => $validated['details'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'requested_days' => $validated['requested_days'] ?? null,
                'status' => 'pending',
                'current_level' => 'primary',
            ]);

            foreach (['primary', 'secondary', 'tertiary'] as $level) {
                $levelApprovers = collect($approverOverrides[$level] ?? [])
                    ->filter(fn ($approver): bool => filled($approver['uuid'] ?? null))
                    ->values();

                if ($levelApprovers->isEmpty()) {
                    $levelApprovers = $workflowApprovers
                        ->where('approver_level', $level)
                        ->values()
                        ->map(fn ($approver): array => [
                            'approver_level' => $approver->approver_level,
                            'approver_staff_uuid' => $approver->approver_staff_uuid,
                            'approver_name' => $approver->approver_name,
                        ]);
                }

                foreach ($levelApprovers->values() as $index => $approver) {
                    $request->steps()->create([
                        'approver_level' => $level,
                        'approver_staff_uuid' => $approver['approver_staff_uuid'] ?? $approver['uuid'],
                        'approver_name' => $approver['approver_name'] ?? $approver['name'],
                        'is_current' => $level === 'primary',
                        'sort_order' => $index,
                    ]);
                }
            }

            $request->recordEvent('submitted', null, [
                'actor_staff_uuid' => $request->requester_staff_uuid,
                'actor_name' => $request->requester_name,
                'to_status' => 'pending',
                'comments' => 'Request submitted for approval.',
            ]);

            $request->syncCategoryArtifactsForSubmission();

            return $request->refresh();
        });
    }

    public function approveCurrentStep(?int $stepId = null, ?string $comments = null, ?string $actorStaffUuid = null): void
    {
        DB::transaction(function () use ($stepId, $comments, $actorStaffUuid) {
            $request = self::whereKey($this->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== 'pending') {
                throw new RuntimeException('Only pending requests can be approved.');
            }

            $step = $request->resolveActionableCurrentStep($stepId, $actorStaffUuid);
            $currentLevel = $step->approver_level;

            $step->update([
                'status' => 'approved',
                'is_current' => false,
                'acted_at' => now(),
                'comments' => $comments,
            ]);

            $request->pendingSteps()
                ->where('approver_level', $currentLevel)
                ->whereKeyNot($step->id)
                ->update([
                    'status' => 'skipped',
                    'is_current' => false,
                    'acted_at' => now(),
                ]);

            $request->recordEvent('approved', $step, [
                'actor_staff_uuid' => $step->approver_staff_uuid,
                'actor_name' => $step->approver_name,
                'from_status' => 'pending',
                'to_status' => 'pending',
                'comments' => $comments ?: "{$step->approver_level} approval completed.",
            ]);

            $nextLevel = $request->pendingSteps()->value('approver_level');

            if ($nextLevel) {
                $request->pendingSteps()
                    ->where('approver_level', $nextLevel)
                    ->update(['is_current' => true]);

                $request->update(['current_level' => $nextLevel]);
                $request->recordEvent('advanced', null, [
                    'actor_staff_uuid' => $step->approver_staff_uuid,
                    'actor_name' => $step->approver_name,
                    'from_status' => 'pending',
                    'to_status' => 'pending',
                    'comments' => "Moved to {$nextLevel} approval.",
                ]);
            } else {
                $request->update([
                    'status' => 'approved',
                    'current_level' => null,
                    'completed_at' => now(),
                ]);
                $request->handleWorkflowCompletion('approved');
                $request->recordEvent('approved', $step, [
                    'actor_staff_uuid' => $step->approver_staff_uuid,
                    'actor_name' => $step->approver_name,
                    'from_status' => 'pending',
                    'to_status' => 'approved',
                    'comments' => 'Request fully approved.',
                ]);
            }

            $this->refresh();
        });
    }

    public function rejectCurrentStep(?int $stepId = null, ?string $comments = null, ?string $actorStaffUuid = null): void
    {
        DB::transaction(function () use ($stepId, $comments, $actorStaffUuid) {
            $request = self::whereKey($this->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== 'pending') {
                throw new RuntimeException('Only pending requests can be rejected.');
            }

            $step = $request->resolveActionableCurrentStep($stepId, $actorStaffUuid);

            $step->update([
                'status' => 'rejected',
                'is_current' => false,
                'acted_at' => now(),
                'comments' => $comments,
            ]);

            $request->pendingSteps()
                ->whereKeyNot($step->id)
                ->update(['status' => 'skipped', 'is_current' => false]);

            $request->update([
                'status' => 'rejected',
                'current_level' => null,
                'completed_at' => now(),
            ]);
            $request->handleWorkflowCompletion('rejected');

            $request->recordEvent('rejected', $step, [
                'actor_staff_uuid' => $step->approver_staff_uuid,
                'actor_name' => $step->approver_name,
                'from_status' => 'pending',
                'to_status' => 'rejected',
                'comments' => $comments ?: 'Request rejected.',
            ]);

            $this->refresh();
        });
    }

    public function recordEvent(string $action, ?HrApprovalStep $step = null, array $data = []): HrApprovalEvent
    {
        return $this->events()->create([
            'approval_step_id' => $step?->id,
            'actor_staff_uuid' => $data['actor_staff_uuid'] ?? null,
            'actor_name' => $data['actor_name'] ?? null,
            'action' => $action,
            'from_status' => $data['from_status'] ?? null,
            'to_status' => $data['to_status'] ?? null,
            'comments' => $data['comments'] ?? null,
            'meta' => $data['meta'] ?? null,
        ]);
    }

    public function isLeaveRequest(): bool
    {
        return $this->approval_category === 'leave';
    }

    public function isOffsiteDutyRequest(): bool
    {
        return $this->approval_category === 'offsite_duty';
    }

    private function resolveActionableCurrentStep(?int $stepId = null, ?string $actorStaffUuid = null): HrApprovalStep
    {
        $currentSteps = $this->currentSteps();

        if ($currentSteps->isEmpty()) {
            $currentStep = $this->currentStep();

            if (! $currentStep) {
                throw new RuntimeException('There is no pending approval step to act on.');
            }

            $currentSteps = collect([$currentStep]);
        }

        if ($stepId !== null) {
            $step = $currentSteps->firstWhere('id', $stepId);

            if (! $step) {
                throw new RuntimeException('Only the current approval step can be acted on.');
            }

            return $step;
        }

        if ($actorStaffUuid !== null) {
            $step = $currentSteps->firstWhere('approver_staff_uuid', $actorStaffUuid);

            if ($step) {
                return $step;
            }
        }

        return $currentSteps->first();
    }

    private static function validateSubmissionData(ApprovalWorkflow $workflow, array $data): array
    {
        $rules = [
            'linked_roster_id' => [
                'nullable',
                'integer',
                Rule::exists('hr_duty_rosters', 'id')->where(fn ($query) => $query->where('organization_id', $workflow->organization_id)),
            ],
            'requester_staff_uuid' => ['required', 'string', 'max:255'],
            'requester_name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string'],
            'leave_type_id' => ['nullable', 'integer'],
            'staff_assignment_id' => ['nullable', 'integer'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'requested_days' => ['nullable', 'numeric', 'min:0.25'],
        ];

        if (in_array($workflow->approval_category, ['leave', 'offsite_duty'], true)) {
            $rules['staff_assignment_id'][] = Rule::exists('hr_staff_assignments', 'id')->where(
                fn ($query) => $query
                    ->where('organization_id', $workflow->organization_id)
                    ->whereNull('deleted_at')
            );
            $rules['staff_assignment_id'][0] = 'required';
            $rules['start_date'][0] = 'required';
            $rules['end_date'][0] = 'required';
        }

        if ($workflow->approval_category === 'leave') {
            $rules['leave_type_id'][] = Rule::exists('hr_leave_types', 'id')->where(
                fn ($query) => $query
                    ->where('organization_id', $workflow->organization_id)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
            );
            $rules['leave_type_id'][0] = 'required';
            $rules['requested_days'][0] = 'required';
        }

        $validator = Validator::make($data, $rules);

        $validator->after(function ($validator) use ($workflow, $data): void {
            if (! in_array($workflow->approval_category, ['leave', 'offsite_duty'], true)) {
                return;
            }

            $assignmentId = $data['staff_assignment_id'] ?? null;

            if (! $assignmentId) {
                return;
            }

            $assignment = StaffAssignment::query()
                ->where('organization_id', $workflow->organization_id)
                ->find($assignmentId);

            if (! $assignment) {
                return;
            }

            if ($assignment->staff_uuid && ! hash_equals((string) $assignment->staff_uuid, (string) ($data['requester_staff_uuid'] ?? ''))) {
                $validator->errors()->add('staff_assignment_id', 'The selected staff assignment does not belong to the requester.');
            }

            $startDate = $data['start_date'] ?? null;
            $endDate = $data['end_date'] ?? null;

            if ($startDate && $endDate) {
                $overlapExists = HrStaffUnavailability::query()
                    ->where('organization_id', $workflow->organization_id)
                    ->where('staff_assignment_id', $assignment->id)
                    ->whereIn('status', [
                        HrStaffUnavailability::STATUS_PENDING,
                        HrStaffUnavailability::STATUS_APPROVED,
                    ])
                    ->whereDate('starts_on', '<=', $endDate)
                    ->whereDate('ends_on', '>=', $startDate)
                    ->exists();

                if ($overlapExists) {
                    $validator->errors()->add('start_date', 'This staff assignment already has a pending or approved blocked request in the selected date range.');
                }
            }

            if ($workflow->approval_category !== 'leave') {
                return;
            }

            if (! $startDate || ! $endDate) {
                return;
            }

            $leaveTypeId = $data['leave_type_id'] ?? null;
            $leaveType = $leaveTypeId
                ? LeaveType::query()
                    ->where('organization_id', $workflow->organization_id)
                    ->where('is_active', true)
                    ->find($leaveTypeId)
                : null;

            try {
                $start = CarbonImmutable::parse($startDate)->startOfDay();
                $end = CarbonImmutable::parse($endDate)->startOfDay();
            } catch (\Throwable) {
                return;
            }

            if ($start->year !== $end->year) {
                $validator->errors()->add('leave_type_id', 'Leave requests must stay within one January to December leave year.');
                return;
            }

            $workingDays = app(WorkingDayCalculator::class)->count($workflow->organization, $start, $end);

            if ($workingDays < 1) {
                $validator->errors()->add('requested_days', 'The selected leave dates do not include any working days.');
                return;
            }

            $expectedRequestedDays = $leaveType?->requestedDaysForWorkingDays((float) $workingDays) ?? (float) $workingDays;
            $requestedDays = isset($data['requested_days']) ? (float) $data['requested_days'] : null;

            if ($requestedDays === null || abs($requestedDays - $expectedRequestedDays) > 0.0001) {
                $validator->errors()->add(
                    'requested_days',
                    "Leave days must match the configured leave-type deduction of {$expectedRequestedDays} for {$workingDays} working day(s)."
                );
            }

            if ($leaveType) {
                $noticeValidationMessage = $leaveType->advanceNoticeValidationMessage(
                    CarbonImmutable::now(),
                    $start
                );

                if ($noticeValidationMessage !== null) {
                    $validator->errors()->add('start_date', $noticeValidationMessage);
                }
            }

            if (! $leaveType?->tracks_balance || ! $leaveType?->max_days_per_year) {
                return;
            }

            $usedDays = (float) self::query()
                ->where('organization_id', $workflow->organization_id)
                ->where('approval_category', 'leave')
                ->whereIn('leave_type_id', $leaveType->groupedLeaveTypeIds())
                ->where('staff_assignment_id', $assignment->id)
                ->whereIn('status', ['pending', 'approved'])
                ->whereYear('start_date', $start->year)
                ->sum('requested_days');

            if (($usedDays + $expectedRequestedDays) > ((float) $leaveType->max_days_per_year + 0.0001)) {
                $remainingDays = max(0, (float) $leaveType->max_days_per_year - $usedDays);
                $validator->errors()->add(
                    'requested_days',
                    "This request exceeds the {$leaveType->max_days_per_year}-day annual limit for {$leaveType->name}. Remaining days this year: {$remainingDays}."
                );
            }
        });

        return $validator->validate();
    }

    private function syncCategoryArtifactsForSubmission(): void
    {
        if ($this->isLeaveRequest()) {
            $this->syncLeaveUnavailability(HrStaffUnavailability::STATUS_PENDING);
        } elseif ($this->isOffsiteDutyRequest()) {
            $this->syncOffsiteDutyUnavailability(HrStaffUnavailability::STATUS_PENDING);
        }
    }

    private function handleWorkflowCompletion(string $finalStatus): void
    {
        if ($finalStatus === 'approved') {
            $linkedRoster = $this->linkedRoster()->first();

            if ($linkedRoster) {
                $linkedRoster->update([
                    'status' => HrDutyRoster::STATUS_PUBLISHED,
                    'approval_status' => HrDutyRoster::APPROVAL_APPROVED,
                    'approval_request_id' => $this->id,
                    'published_at' => now(),
                    'rejected_at' => null,
                ]);
            }

            if ($this->isLeaveRequest()) {
                $this->syncLeaveUnavailability(HrStaffUnavailability::STATUS_APPROVED);
                $this->removeBlockedRosterEntries();
            } elseif ($this->isOffsiteDutyRequest()) {
                $this->syncOffsiteDutyUnavailability(HrStaffUnavailability::STATUS_APPROVED);
                $this->removeBlockedRosterEntries();
            }

            return;
        }

        $linkedRoster = $this->linkedRoster()->first();

        if ($linkedRoster) {
            $linkedRoster->update([
                'status' => HrDutyRoster::STATUS_DRAFT,
                'approval_status' => HrDutyRoster::APPROVAL_REJECTED,
                'approval_request_id' => $this->id,
                'rejected_at' => now(),
            ]);
        }

        if ($this->isLeaveRequest()) {
            $this->syncLeaveUnavailability(HrStaffUnavailability::STATUS_CANCELLED);
        } elseif ($this->isOffsiteDutyRequest()) {
            $this->syncOffsiteDutyUnavailability(HrStaffUnavailability::STATUS_CANCELLED);
        }
    }

    private function syncLeaveUnavailability(string $status): void
    {
        $this->syncRequestUnavailability(
            HrStaffUnavailability::REASON_LEAVE,
            $status,
            $this->leaveType?->name ?: $this->subject
        );
    }

    private function syncOffsiteDutyUnavailability(string $status): void
    {
        $this->syncRequestUnavailability(
            HrStaffUnavailability::REASON_OFFSITE_DUTY,
            $status,
            $this->subject ?: 'Official Workshop/Meeting'
        );
    }

    private function syncRequestUnavailability(string $reasonType, string $status, string $defaultTitle): void
    {
        if (! $this->staff_assignment_id || ! $this->start_date) {
            return;
        }

        $unavailability = HrStaffUnavailability::withTrashed()
            ->firstOrNew(['approval_request_id' => $this->id]);

        if ($unavailability->trashed()) {
            $unavailability->restore();
        }

        $unavailability->fill([
            'organization_id' => $this->organization_id,
            'staff_assignment_id' => $this->staff_assignment_id,
            'leave_type_id' => $reasonType === HrStaffUnavailability::REASON_LEAVE ? $this->leave_type_id : null,
            'approval_request_id' => $this->id,
            'reason_type' => $reasonType,
            'title' => $defaultTitle,
            'starts_on' => $this->start_date,
            'ends_on' => $this->end_date ?: $this->start_date,
            'status' => $status,
            'blocks_rosters' => in_array($status, [
                HrStaffUnavailability::STATUS_PENDING,
                HrStaffUnavailability::STATUS_APPROVED,
            ], true),
            'notes' => $this->details,
        ]);

        $unavailability->save();
    }

    private function removeBlockedRosterEntries(): void
    {
        if (! $this->staff_assignment_id || ! $this->start_date) {
            return;
        }

        $entries = HrDutyRosterEntry::query()
            ->where('organization_id', $this->organization_id)
            ->where('staff_assignment_id', $this->staff_assignment_id)
            ->whereDate('roster_date', '>=', $this->start_date->toDateString())
            ->whereDate('roster_date', '<=', ($this->end_date ?: $this->start_date)->toDateString())
            ->whereHas('dutyRoster', function ($query): void {
                $query
                    ->where('status', '!=', HrDutyRoster::STATUS_ARCHIVED)
                    ->where('approval_status', '!=', HrDutyRoster::APPROVAL_REJECTED);
            })
            ->with(['dutyRoster.organizationalUnit', 'staffAssignment', 'shiftType'])
            ->get();

        if ($entries->isEmpty()) {
            return;
        }

        HrDutyRosterEntry::query()
            ->whereKey($entries->pluck('id'))
            ->delete();

        app(OpenShiftService::class)->handleRosterEntriesRemoved($this, $entries);
    }
}
