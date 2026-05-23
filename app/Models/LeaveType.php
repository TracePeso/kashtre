<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LeaveType extends Model
{
    use SoftDeletes;

    protected $table = 'hr_leave_types';

    public const SESSION_FULL_DAY = 'full_day';
    public const SESSION_MORNING_ABSENT = 'morning_absent';
    public const SESSION_AFTERNOON_ABSENT = 'afternoon_absent';

    protected $fillable = [
        'uuid',
        'organization_id',
        'name',
        'code',
        'session_type',
        'days_deducted_per_workday',
        'max_days_per_year',
        'tracks_balance',
        'is_paid',
        'requires_approval',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_approval' => 'boolean',
        'max_days_per_year' => 'integer',
        'tracks_balance' => 'boolean',
        'is_paid' => 'boolean',
        'days_deducted_per_workday' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
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

    /**
     * @return array<string, string>
     */
    public static function sessionOptions(): array
    {
        return [
            self::SESSION_FULL_DAY => 'Full Day',
            self::SESSION_MORNING_ABSENT => 'Morning Absent',
            self::SESSION_AFTERNOON_ABSENT => 'Afternoon Absent',
        ];
    }

    public function sessionLabel(): string
    {
        return self::sessionOptions()[$this->session_type] ?? 'Custom';
    }

    public function deductionPerWorkday(): float
    {
        $value = (float) ($this->days_deducted_per_workday ?? 1);

        return $value > 0 ? $value : 1.0;
    }

    public function requestedDaysForWorkingDays(float $workingDays): float
    {
        return round($workingDays * $this->deductionPerWorkday(), 2);
    }
}
