<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HrStaffRosteringProfile extends Model
{
    use SoftDeletes;

    public const MODE_DYNAMIC = 'dynamic';
    public const MODE_FIXED = 'fixed';

    protected $table = 'hr_staff_rostering_profiles';

    protected $fillable = [
        'uuid',
        'organization_id',
        'staff_assignment_id',
        'rostering_mode',
        'fixed_shift_type_id',
        'fixed_days_of_week',
        'preferred_shift_type_ids',
        'excluded_shift_type_ids',
        'max_night_shifts_per_cycle',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'fixed_days_of_week' => 'array',
        'preferred_shift_type_ids' => 'array',
        'excluded_shift_type_ids' => 'array',
        'max_night_shifts_per_cycle' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
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

    public function staffAssignment()
    {
        return $this->belongsTo(StaffAssignment::class, 'staff_assignment_id');
    }

    public function fixedShiftType()
    {
        return $this->belongsTo(ShiftType::class, 'fixed_shift_type_id');
    }

    /**
     * @return array<int, int>
     */
    public function fixedDays(): array
    {
        return $this->normalizeIntegerList($this->fixed_days_of_week)
            ->filter(fn (int $day): bool => $day >= 0 && $day <= 6)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function preferredShiftIds(): array
    {
        return $this->normalizeIntegerList($this->preferred_shift_type_ids)->all();
    }

    public function preferredShiftIdsForPrompt(?int $regularWorkingHoursShiftId = null): array
    {
        $preferredShiftIds = $this->preferredShiftIds();

        if ($preferredShiftIds !== [] || ! $this->is_active || $this->fixed_shift_type_id) {
            return $preferredShiftIds;
        }

        return $regularWorkingHoursShiftId ? [$regularWorkingHoursShiftId] : [];
    }

    /**
     * @return array<int, int>
     */
    public function excludedShiftIds(): array
    {
        return $this->normalizeIntegerList($this->excluded_shift_type_ids)->all();
    }

    public function usesFixedMode(): bool
    {
        return $this->is_active && $this->rostering_mode === self::MODE_FIXED;
    }

    public function hasExplicitShiftPreference(): bool
    {
        return (bool) $this->fixed_shift_type_id || $this->preferredShiftIds() !== [];
    }

    public function allowsDate(CarbonInterface $date): bool
    {
        if (! $this->usesFixedMode()) {
            return true;
        }

        $fixedDays = $this->fixedDays();

        if ($fixedDays === []) {
            return true;
        }

        return in_array((int) $date->dayOfWeek, $fixedDays, true);
    }

    public function defaultsToRegularWorkingHours(?ShiftType $shiftType): bool
    {
        return $this->is_active
            && $shiftType !== null
            && ! $this->hasExplicitShiftPreference()
            && $shiftType->isRegularWorkingHoursDefault();
    }

    public function prefersShift(?ShiftType $shiftType): bool
    {
        if (! $shiftType || ! $this->is_active) {
            return false;
        }

        return (int) $this->fixed_shift_type_id === (int) $shiftType->id
            || in_array((int) $shiftType->id, $this->preferredShiftIds(), true)
            || $this->defaultsToRegularWorkingHours($shiftType);
    }

    public function excludesShift(?ShiftType $shiftType): bool
    {
        if (! $shiftType || ! $this->is_active) {
            return false;
        }

        return in_array((int) $shiftType->id, $this->excludedShiftIds(), true);
    }

    /**
     * @return Collection<int, int>
     */
    private function normalizeIntegerList(mixed $values): Collection
    {
        return collect(is_array($values) ? $values : [])
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->values();
    }
}
