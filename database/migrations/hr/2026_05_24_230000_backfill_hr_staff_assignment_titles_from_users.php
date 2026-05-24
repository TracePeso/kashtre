<?php

use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $usersByStaffUuid = User::query()
            ->with([
                'title:id,name',
                'department:id,name',
            ])
            ->whereNotNull('title_id')
            ->orWhereNotNull('department_id')
            ->get(['id', 'uuid', 'staff_uuid', 'title_id', 'department_id'])
            ->keyBy(fn (User $user): string => (string) ($user->staff_uuid ?: $user->uuid));

        if ($usersByStaffUuid->isEmpty()) {
            return;
        }

        StaffAssignment::query()
            ->where(function ($query): void {
                $query
                    ->whereNull('staff_title')
                    ->orWhere('staff_title', '')
                    ->orWhereNull('staff_department')
                    ->orWhere('staff_department', '');
            })
            ->orderBy('id')
            ->chunkById(200, function ($assignments) use ($usersByStaffUuid): void {
                foreach ($assignments as $assignment) {
                    $user = $usersByStaffUuid->get((string) $assignment->staff_uuid);

                    if (! $user) {
                        continue;
                    }

                    $assignment->forceFill([
                        'staff_title' => filled($assignment->staff_title)
                            ? $assignment->staff_title
                            : $user->title?->name,
                        'staff_department' => filled($assignment->staff_department)
                            ? $assignment->staff_department
                            : $user->department?->name,
                    ]);

                    if ($assignment->isDirty(['staff_title', 'staff_department'])) {
                        $assignment->save();
                    }
                }
            });
    }

    public function down(): void
    {
        // One-time data backfill for roster title visibility.
    }
};
