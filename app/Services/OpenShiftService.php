<?php

namespace App\Services;

use App\Models\HrApprovalRequest;
use App\Models\HrDutyRoster;
use App\Models\HrDutyRosterEntry;
use App\Models\HrOpenShift;
use App\Models\HrOpenShiftBid;
use App\Models\HrOrganizationalUnit;
use App\Models\ShiftType;
use App\Models\StaffAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OpenShiftService
{
    public function __construct(
        private readonly LocumDisciplineResolver $disciplineResolver,
        private readonly OpenShiftEligibilityService $eligibilityService,
        private readonly RosterPolicyValidator $rosterPolicyValidator,
    ) {
    }

    public function handleRosterEntriesRemoved(HrApprovalRequest $request, Collection $removedEntries): void
    {
        if ($removedEntries->isEmpty()) {
            return;
        }

        $removedEntries->loadMissing([
            'dutyRoster.organizationalUnit',
            'shiftType',
            'staffAssignment',
        ]);

        foreach ($removedEntries as $entry) {
            if (! $entry instanceof HrDutyRosterEntry || ! $entry->dutyRoster?->organizationalUnit) {
                continue;
            }

            $disciplineLabel = $this->disciplineResolver->entryLabel($entry);
            $disciplineKey = $this->disciplineResolver->key($disciplineLabel);
            $this->createReplacementOpenShift($request, $entry, $disciplineLabel, $disciplineKey);
        }
    }

    public function reconcileRoster(HrDutyRoster $roster): void
    {
        if ($roster->status === HrDutyRoster::STATUS_ARCHIVED || $roster->approval_status === HrDutyRoster::APPROVAL_REJECTED) {
            HrOpenShift::query()
                ->where('duty_roster_id', $roster->id)
                ->where('status', HrOpenShift::STATUS_OPEN)
                ->update(['status' => HrOpenShift::STATUS_CANCELLED]);
        }
    }

    public function submitBid(HrOpenShift $openShift, StaffAssignment $assignment, ?string $notes = null): HrOpenShiftBid
    {
        $openShift->loadMissing('bids');
        $this->ensureOpenShiftIsOpen($openShift);
        $this->eligibilityService->assertEligible($openShift, $assignment);

        return DB::transaction(function () use ($openShift, $assignment, $notes): HrOpenShiftBid {
            $lockedShift = HrOpenShift::query()
                ->lockForUpdate()
                ->findOrFail($openShift->id);

            $this->ensureOpenShiftIsOpen($lockedShift);

            $existingBid = HrOpenShiftBid::query()
                ->where('hr_open_shift_id', $lockedShift->id)
                ->where('staff_assignment_id', $assignment->id)
                ->whereIn('status', [
                    HrOpenShiftBid::STATUS_PENDING,
                    HrOpenShiftBid::STATUS_ACCEPTED,
                ])
                ->first();

            if ($existingBid) {
                return $existingBid;
            }

            return HrOpenShiftBid::create([
                'hr_open_shift_id' => $lockedShift->id,
                'staff_assignment_id' => $assignment->id,
                'bid_staff_uuid' => $assignment->staff_uuid,
                'status' => HrOpenShiftBid::STATUS_PENDING,
                'notes' => filled($notes) ? trim($notes) : null,
                'submitted_at' => now(),
            ]);
        });
    }

    public function rejectBid(HrOpenShiftBid $bid, User $actor): HrOpenShiftBid
    {
        return DB::transaction(function () use ($bid, $actor): HrOpenShiftBid {
            $lockedBid = HrOpenShiftBid::query()
                ->lockForUpdate()
                ->findOrFail($bid->id);

            if ($lockedBid->status !== HrOpenShiftBid::STATUS_PENDING) {
                return $lockedBid;
            }

            $lockedBid->update([
                'status' => HrOpenShiftBid::STATUS_REJECTED,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
            ]);

            return $lockedBid->fresh();
        });
    }

    public function acceptBid(HrOpenShiftBid $bid, User $actor): HrOpenShift
    {
        $bid->loadMissing(['openShift', 'staffAssignment']);

        if (! $bid->openShift || ! $bid->staffAssignment) {
            throw new RuntimeException('This locum bid is no longer valid.');
        }

        return $this->assignOpenShift($bid->openShift, $bid->staffAssignment, $actor, $bid);
    }

    public function assignOpenShift(HrOpenShift $openShift, StaffAssignment $assignment, User $actor, ?HrOpenShiftBid $acceptedBid = null): HrOpenShift
    {
        return DB::transaction(function () use ($openShift, $assignment, $actor, $acceptedBid): HrOpenShift {
            $lockedShift = HrOpenShift::query()
                ->with(['dutyRoster.organizationalUnit', 'bids'])
                ->lockForUpdate()
                ->findOrFail($openShift->id);

            $this->ensureOpenShiftIsOpen($lockedShift);
            $assignment = StaffAssignment::query()->findOrFail($assignment->id);
            $this->eligibilityService->assertEligible($lockedShift, $assignment);

            if (! $lockedShift->duty_roster_id) {
                throw ValidationException::withMessages([
                    'open_shift' => 'This open shift is not attached to a duty roster.',
                ]);
            }

            $roster = HrDutyRoster::query()
                ->with('organizationalUnit')
                ->lockForUpdate()
                ->find($lockedShift->duty_roster_id);

            if (! $roster || $roster->status === HrDutyRoster::STATUS_ARCHIVED || $roster->approval_status === HrDutyRoster::APPROVAL_REJECTED) {
                throw ValidationException::withMessages([
                    'open_shift' => 'This duty roster can no longer accept locum assignments.',
                ]);
            }

            $payload = [
                'organization_id' => $lockedShift->organization_id,
                'roster_date' => $lockedShift->roster_date->toDateString(),
                'staff_assignment_id' => $assignment->id,
                'staff_uuid' => $assignment->staff_uuid,
                'staff_name' => $assignment->staff_name,
                'staff_cadre' => $assignment->staff_cadre,
                'shift_type_id' => $lockedShift->shift_type_id,
                'notes' => 'Locum coverage assignment',
            ];

            $this->rosterPolicyValidator->validate($roster, [$payload]);

            $entry = HrDutyRosterEntry::query()
                ->where('duty_roster_id', $roster->id)
                ->where('staff_assignment_id', $assignment->id)
                ->whereDate('roster_date', $lockedShift->roster_date->toDateString())
                ->where('shift_type_id', $lockedShift->shift_type_id)
                ->first();

            if (! $entry) {
                $roster->entries()->create($payload);
            }

            $lockedShift->update([
                'status' => HrOpenShift::STATUS_FILLED,
                'filled_by_staff_assignment_id' => $assignment->id,
                'filled_by_user_id' => $actor->id,
                'filled_at' => now(),
            ]);

            $lockedShift->bids()
                ->whereIn('status', [HrOpenShiftBid::STATUS_PENDING, HrOpenShiftBid::STATUS_ACCEPTED])
                ->update([
                    'status' => HrOpenShiftBid::STATUS_REJECTED,
                    'decided_by_user_id' => $actor->id,
                    'decided_at' => now(),
                ]);

            if ($acceptedBid) {
                HrOpenShiftBid::query()
                    ->whereKey($acceptedBid->id)
                    ->update([
                        'status' => HrOpenShiftBid::STATUS_ACCEPTED,
                        'decided_by_user_id' => $actor->id,
                        'decided_at' => now(),
                    ]);
            }

            $this->reconcileRoster($roster);

            return $lockedShift->fresh([
                'clientSpace',
                'shiftType',
                'filledByStaffAssignment',
                'bids.staffAssignment',
            ]);
        });
    }

    private function createReplacementOpenShift(
        HrApprovalRequest $request,
        HrDutyRosterEntry $entry,
        string $disciplineLabel,
        string $disciplineKey,
    ): HrOpenShift {
        $sourceType = $request->approval_category === 'offsite_duty'
            ? HrOpenShift::SOURCE_OFFSITE_DUTY
            : HrOpenShift::SOURCE_LEAVE;

        return HrOpenShift::query()->firstOrCreate([
            'source_duty_roster_entry_id' => $entry->id,
        ], [
            'organization_id' => $entry->organization_id,
            'client_space_unit_id' => $entry->dutyRoster?->organizational_unit_id,
            'duty_roster_id' => $entry->duty_roster_id,
            'shift_type_id' => $entry->shift_type_id,
            'source_staff_assignment_id' => $entry->staff_assignment_id,
            'roster_date' => Carbon::parse($entry->roster_date)->toDateString(),
            'discipline_key' => $disciplineKey,
            'discipline_label' => $disciplineLabel,
            'expected_headcount' => 1,
            'source_type' => $sourceType,
            'status' => HrOpenShift::STATUS_OPEN,
            'source_reason' => $request->subject,
            'metadata' => [
                'approval_request_id' => $request->id,
            ],
        ]);
    }

    private function ensureOpenShiftIsOpen(HrOpenShift $openShift): void
    {
        if ($openShift->status === HrOpenShift::STATUS_OPEN) {
            return;
        }

        throw ValidationException::withMessages([
            'open_shift' => 'This open shift is no longer available.',
        ]);
    }

}
