<?php

namespace App\Livewire;

use App\Models\HrOpenShift;
use App\Models\HrOpenShiftBid;
use App\Models\Organization;
use App\Models\User;
use App\Services\OpenShiftEligibilityService;
use App\Services\OpenShiftService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class OpenShiftBoard extends Component
{
    public array $assignmentSelections = [];
    public array $staffBidAssignmentSelections = [];
    public array $bidNotes = [];
    public ?string $message = null;

    public function assignShift(int $openShiftId): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $this->canManageCoverage($user), 403);

        $openShift = $this->findOpenShift($openShiftId);

        if (! $openShift) {
            abort(404);
        }

        $assignmentId = (int) ($this->assignmentSelections[$openShiftId] ?? 0);

        if ($assignmentId <= 0) {
            $this->addError("assignmentSelections.$openShiftId", 'Select a qualified staff member to cover this shift.');
            return;
        }

        $assignment = $this->eligibilityService()
            ->eligibleAssignments($openShift)
            ->firstWhere('id', $assignmentId);

        if (! $assignment) {
            $this->addError("assignmentSelections.$openShiftId", 'The selected staff member is not eligible for this open shift.');
            return;
        }

        try {
            $this->openShiftService()->assignOpenShift($openShift, $assignment, $user);
        } catch (ValidationException $exception) {
            $this->forwardValidationErrors($exception);
            return;
        } catch (RuntimeException $exception) {
            $this->message = $exception->getMessage();
            return;
        }

        unset($this->assignmentSelections[$openShiftId], $this->bidNotes[$openShiftId]);
        $this->message = 'Open shift assigned.';
    }

    public function submitBid(int $openShiftId): void
    {
        $organization = Organization::current();
        $user = Auth::user();

        abort_unless($organization && $user instanceof User && $this->canAccess($user), 403);

        $openShift = $this->findOpenShift($openShiftId);

        if (! $openShift) {
            abort(404);
        }

        $assignment = $this->eligibilityService()->resolveBidAssignmentForUser(
            $openShift,
            $user,
            isset($this->staffBidAssignmentSelections[$openShiftId])
                ? (int) $this->staffBidAssignmentSelections[$openShiftId]
                : null
        );

        if (! $assignment) {
            $this->addError("staffBidAssignmentSelections.$openShiftId", 'You do not have an eligible staff assignment for this locum opportunity.');
            return;
        }

        try {
            $this->openShiftService()->submitBid($openShift, $assignment, $this->bidNotes[$openShiftId] ?? null);
        } catch (ValidationException $exception) {
            $this->forwardValidationErrors($exception);
            return;
        } catch (RuntimeException $exception) {
            $this->message = $exception->getMessage();
            return;
        }

        unset($this->bidNotes[$openShiftId]);
        $this->message = 'Locum bid submitted.';
    }

    public function acceptBid(int $bidId): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $this->canManageCoverage($user), 403);

        $bid = $this->findBid($bidId);

        if (! $bid) {
            abort(404);
        }

        try {
            $this->openShiftService()->acceptBid($bid, $user);
        } catch (ValidationException $exception) {
            $this->forwardValidationErrors($exception);
            return;
        } catch (RuntimeException $exception) {
            $this->message = $exception->getMessage();
            return;
        }

        $this->message = 'Locum bid accepted and shift filled.';
    }

    public function rejectBid(int $bidId): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $this->canManageCoverage($user), 403);

        $bid = $this->findBid($bidId);

        if (! $bid) {
            abort(404);
        }

        $this->openShiftService()->rejectBid($bid, $user);
        $this->message = 'Locum bid rejected.';
    }

    public function render(): View
    {
        $organization = Organization::current();
        $user = Auth::user();

        abort_unless($organization && $user instanceof User, 403);
        abort_unless($this->canAccess($user), 403);

        $allOpenShifts = HrOpenShift::query()
            ->where('organization_id', $organization->id)
            ->where('status', HrOpenShift::STATUS_OPEN)
            ->with([
                'clientSpace.clientSpace',
                'dutyRoster',
                'shiftType',
                'sourceStaffAssignment.organizationalUnit',
                'bids.staffAssignment.organizationalUnit',
            ])
            ->orderBy('roster_date')
            ->orderBy('shift_type_id')
            ->get();

        $canManageCoverage = $this->canManageCoverage($user);
        $eligibleAssignmentsByShift = collect();
        $myEligibleAssignmentsByShift = collect();
        $visibleOpenShifts = $allOpenShifts;

        if ($canManageCoverage) {
            $eligibleAssignmentsByShift = $allOpenShifts->mapWithKeys(
                fn (HrOpenShift $openShift): array => [$openShift->id => $this->eligibilityService()->eligibleAssignments($openShift)]
            );
        } else {
            $myEligibleAssignmentsByShift = $allOpenShifts->mapWithKeys(
                fn (HrOpenShift $openShift): array => [$openShift->id => $this->eligibilityService()->eligibleAssignmentsForUser($openShift, $user)]
            );
            $visibleOpenShifts = $allOpenShifts
                ->filter(fn (HrOpenShift $openShift): bool => ($myEligibleAssignmentsByShift[$openShift->id] ?? collect())->isNotEmpty())
                ->values();
        }

        return view('livewire.open-shift-board', [
            'openShifts' => $visibleOpenShifts,
            'canManageCoverage' => $canManageCoverage,
            'eligibleAssignmentsByShift' => $eligibleAssignmentsByShift,
            'myEligibleAssignmentsByShift' => $myEligibleAssignmentsByShift,
            'currentStaffUuid' => $user->staff_uuid,
        ]);
    }

    private function canAccess(User $user): bool
    {
        return $this->canManageCoverage($user) || (bool) $user->staff_uuid;
    }

    private function canManageCoverage(User $user): bool
    {
        return $user->is_hr_admin || $user->canViewHrStaff() || $user->canViewHrSetup() || $user->canManageAllApprovals();
    }

    private function findOpenShift(int $openShiftId): ?HrOpenShift
    {
        $organization = Organization::current();

        if (! $organization) {
            return null;
        }

        return HrOpenShift::query()
            ->where('organization_id', $organization->id)
            ->with([
                'clientSpace.clientSpace',
                'dutyRoster.organizationalUnit',
                'shiftType',
                'bids.staffAssignment',
            ])
            ->find($openShiftId);
    }

    private function findBid(int $bidId): ?HrOpenShiftBid
    {
        $organization = Organization::current();

        if (! $organization) {
            return null;
        }

        return HrOpenShiftBid::query()
            ->whereHas('openShift', fn ($query) => $query->where('organization_id', $organization->id))
            ->with(['openShift.dutyRoster.organizationalUnit', 'staffAssignment'])
            ->find($bidId);
    }

    private function openShiftService(): OpenShiftService
    {
        return app(OpenShiftService::class);
    }

    private function eligibilityService(): OpenShiftEligibilityService
    {
        return app(OpenShiftEligibilityService::class);
    }

    private function forwardValidationErrors(ValidationException $exception): void
    {
        foreach ($exception->errors() as $field => $messages) {
            foreach ((array) $messages as $message) {
                $this->addError($field, $message);
            }
        }
    }
}
