<?php

namespace App\Models;

use Carbon\CarbonImmutable;
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
    public const NOTICE_PRE = 'pre';
    public const NOTICE_POST = 'post';

    protected $fillable = [
        'uuid',
        'organization_id',
        'name',
        'code',
        'balance_group_code',
        'session_type',
        'days_deducted_per_workday',
        'advance_notice_timing',
        'advance_notice_days',
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
        'advance_notice_days' => 'integer',
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

    /**
     * @return array<string, string>
     */
    public static function noticeTimingOptions(): array
    {
        return [
            self::NOTICE_PRE => 'Before leave starts',
            self::NOTICE_POST => 'After leave starts',
        ];
    }

    public function noticeTimingLabel(): string
    {
        return self::noticeTimingOptions()[$this->advance_notice_timing] ?? 'Before leave starts';
    }

    public function requiredAdvanceNoticeDays(): int
    {
        return max(0, (int) ($this->advance_notice_days ?? 0));
    }

    public function advanceNoticeSummary(): string
    {
        $days = $this->requiredAdvanceNoticeDays();

        if ($days === 0) {
            return 'No notice requirement';
        }

        $timing = $this->advance_notice_timing === self::NOTICE_POST ? 'after leave starts' : 'before leave starts';

        return sprintf('%d day(s) %s', $days, $timing);
    }

    public function advanceNoticeValidationMessage(
        CarbonImmutable $submissionDate,
        CarbonImmutable $leaveStartDate
    ): ?string {
        $days = $this->requiredAdvanceNoticeDays();

        if ($days === 0) {
            return null;
        }

        $submissionDate = $submissionDate->startOfDay();
        $leaveStartDate = $leaveStartDate->startOfDay();

        if ($this->advance_notice_timing === self::NOTICE_POST) {
            $oldestAllowedStartDate = $submissionDate->subDays($days);

            return $leaveStartDate->lt($oldestAllowedStartDate)
                ? "This leave type must be reported within {$days} day(s) after the leave start date."
                : null;
        }

        $earliestAllowedStartDate = $submissionDate->addDays($days);

        return $leaveStartDate->lt($earliestAllowedStartDate)
            ? "This leave type requires at least {$days} day(s) notice before the leave start date."
            : null;
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

    public function balanceGroupCode(): string
    {
        $groupCode = strtoupper(trim((string) $this->balance_group_code));

        if ($groupCode !== '') {
            return $groupCode;
        }

        return 'LEAVE_TYPE_'.$this->getKey();
    }

    /**
     * @return array<int, int>
     */
    public function groupedLeaveTypeIds(): array
    {
        $query = self::query()
            ->where('organization_id', $this->organization_id);

        if (filled($this->balance_group_code)) {
            $query->where('balance_group_code', $this->balanceGroupCode());
        } else {
            $query->whereKey($this->getKey());
        }

        return $query
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
