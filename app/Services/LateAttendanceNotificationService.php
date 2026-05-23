<?php

namespace App\Services;

use App\Models\HrAttendanceLedger;
use App\Models\StaffAssignment;
use App\Models\User;
use App\Notifications\RepeatedLateClockInNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class LateAttendanceNotificationService
{
    public function notifyIfNeeded(HrAttendanceLedger $ledger, int $lateCount): void
    {
        $ledger->loadMissing('organization', 'staffAssignment', 'shiftType');

        if (! $ledger->isLateClockIn() || ! $ledger->is_late_flagged) {
            return;
        }

        $alreadyNotifiedForCount = (int) data_get($ledger->metadata, 'late_warning_notified_for_count', 0);

        if ($alreadyNotifiedForCount >= $lateCount) {
            return;
        }

        $employee = $this->employeeRecipient($ledger);
        $managerRecipients = $this->managerRecipients($ledger, $employee?->id);

        if ($employee) {
            $employee->notify(new RepeatedLateClockInNotification($ledger, $lateCount, 'employee'));
        }

        if ($managerRecipients->isNotEmpty()) {
            Notification::send($managerRecipients, new RepeatedLateClockInNotification($ledger, $lateCount, 'manager'));
        }

        $metadata = array_merge($ledger->metadata ?? [], [
            'late_warning_notified_for_count' => $lateCount,
            'late_warning_notified_at' => now()->toDateTimeString(),
            'late_warning_employee_user_id' => $employee?->id,
            'late_warning_manager_user_ids' => $managerRecipients->pluck('id')->values()->all(),
        ]);

        $ledger->forceFill(['metadata' => $metadata])->save();
    }

    private function employeeRecipient(HrAttendanceLedger $ledger): ?User
    {
        if (! filled($ledger->staff_uuid)) {
            return null;
        }

        return User::query()
            ->where('staff_uuid', $ledger->staff_uuid)
            ->first();
    }

    /**
     * @return Collection<int, User>
     */
    private function managerRecipients(HrAttendanceLedger $ledger, ?int $excludeUserId = null): Collection
    {
        return User::query()
            ->get()
            ->filter(function (User $user) use ($ledger, $excludeUserId): bool {
                if ($excludeUserId !== null && $user->id === $excludeUserId) {
                    return false;
                }

                if (! ($user->is_hr_admin || $user->canManageHrBiometrics())) {
                    return false;
                }

                if ($user->is_hr_admin && ! $user->staff_uuid) {
                    return true;
                }

                if (! $user->staff_uuid) {
                    return false;
                }

                return StaffAssignment::query()
                    ->where('organization_id', $ledger->organization_id)
                    ->where('staff_uuid', $user->staff_uuid)
                    ->exists();
            })
            ->values();
    }
}
