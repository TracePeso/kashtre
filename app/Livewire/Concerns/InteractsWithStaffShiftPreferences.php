<?php

namespace App\Livewire\Concerns;

use App\Models\HrStaffRosteringProfile;
use App\Models\Organization;
use App\Models\ShiftType;
use App\Models\StaffAssignment;
use Illuminate\Support\Facades\Auth;

trait InteractsWithStaffShiftPreferences
{
    /**
     * @return array<string, mixed>
     */
    protected function defaultShiftPreferenceFormState(): array
    {
        return [
            'rostering_mode' => HrStaffRosteringProfile::MODE_DYNAMIC,
            'fixed_shift_type_id' => null,
            'fixed_days_of_week' => [],
            'preferred_shift_type_ids' => [],
            'excluded_shift_type_ids' => [],
            'max_night_shifts_per_cycle' => null,
            'notes' => null,
            'is_active' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function staffShiftPreferenceFormData(StaffAssignment $record): array
    {
        $profile = $record->rosteringProfile;

        return [
            'rostering_mode' => $profile?->rostering_mode ?? HrStaffRosteringProfile::MODE_DYNAMIC,
            'fixed_shift_type_id' => $profile?->fixed_shift_type_id,
            'fixed_days_of_week' => $profile?->fixedDays() ?? [],
            'preferred_shift_type_ids' => $profile?->preferredShiftIds() ?? [],
            'excluded_shift_type_ids' => $profile?->excludedShiftIds() ?? [],
            'max_night_shifts_per_cycle' => $profile?->max_night_shifts_per_cycle,
            'notes' => $profile?->notes,
            'is_active' => $profile?->is_active ?? true,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function saveStaffShiftPreference(StaffAssignment $record, array $data): void
    {
        $record->rosteringProfile()->updateOrCreate(
            [],
            [
                'organization_id' => $record->organization_id,
                'rostering_mode' => $data['rostering_mode'] ?? HrStaffRosteringProfile::MODE_DYNAMIC,
                'fixed_shift_type_id' => $data['fixed_shift_type_id'] ?: null,
                'fixed_days_of_week' => array_values(array_map('intval', $data['fixed_days_of_week'] ?? [])),
                'preferred_shift_type_ids' => array_values(array_map('intval', $data['preferred_shift_type_ids'] ?? [])),
                'excluded_shift_type_ids' => array_values(array_map('intval', $data['excluded_shift_type_ids'] ?? [])),
                'max_night_shifts_per_cycle' => filled($data['max_night_shifts_per_cycle'] ?? null)
                    ? (int) $data['max_night_shifts_per_cycle']
                    : null,
                'notes' => $data['notes'] ?: null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]
        );
    }

    protected function shiftPreferenceSummary(StaffAssignment $record): string
    {
        $profile = $record->rosteringProfile;

        if (! $profile || ! $profile->is_active) {
            return 'No preference set';
        }

        if ($profile->fixedShiftType) {
            return 'Fixed: '.$this->shiftTypeLabel($profile->fixedShiftType);
        }

        $preferredLabels = collect($profile->preferredShiftIds())
            ->map(fn (int $shiftId): ?string => $this->shiftTypeOptions()[(string) $shiftId] ?? null)
            ->filter()
            ->values();

        if ($preferredLabels->isNotEmpty()) {
            return 'Preferred: '.$preferredLabels->implode(', ');
        }

        return $profile->rostering_mode === HrStaffRosteringProfile::MODE_FIXED
            ? 'Fixed pattern'
            : 'Dynamic rotation';
    }

    protected function userCanManageShiftPreferences(): bool
    {
        $user = Auth::user();

        return (bool) (
            $user?->canEditHrStaff()
            || $user?->canViewHrStaff()
            || $user?->canViewHrSetup()
        );
    }

    /**
     * @return array<int, string>
     */
    protected function shiftTypeOptions(): array
    {
        $organization = Organization::current();

        if (! $organization) {
            return [];
        }

        return ShiftType::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ShiftType $shiftType): array => [
                $shiftType->id => $this->shiftTypeLabel($shiftType),
            ])
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    protected function dayOfWeekOptions(): array
    {
        return [
            1 => 'Mon',
            2 => 'Tue',
            3 => 'Wed',
            4 => 'Thu',
            5 => 'Fri',
            6 => 'Sat',
            0 => 'Sun',
        ];
    }

    protected function shiftTypeLabel(ShiftType $shiftType): string
    {
        return $shiftType->code
            ? $shiftType->code.' - '.$shiftType->name
            : $shiftType->name;
    }
}
