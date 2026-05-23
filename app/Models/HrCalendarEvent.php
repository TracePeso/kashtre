<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class HrCalendarEvent extends Model
{
    use SoftDeletes;

    public const APPROVAL_PENDING = 'pending';
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_REJECTED = 'rejected';

    public const TYPE_PUBLIC_HOLIDAY = 'public_holiday';
    public const TYPE_OBSERVANCE = 'observance';
    public const TYPE_ORGANIZATION_EVENT = 'organization_event';
    public const TYPE_TRAINING_DAY = 'training_day';
    public const TYPE_PAYROLL_CUTOFF = 'payroll_cutoff';
    public const TYPE_LEAVE_BLACKOUT = 'leave_blackout';
    public const TYPE_OTHER = 'other';

    public const REWARD_LEAVE_DAY = 'leave_day';
    public const REWARD_MULTIPLIER_PAY = 'multiplier_pay';
    public const REWARD_NONE = 'none';

    protected $fillable = [
        'uuid',
        'organization_id',
        'created_by_user_id',
        'updated_by_user_id',
        'title',
        'event_type',
        'starts_on',
        'ends_on',
        'repeats_yearly',
        'affects_rosters',
        'reward_type',
        'blocks_rosters',
        'is_active',
        'approval_status',
        'approved_by_user_id',
        'approved_at',
        'description',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'repeats_yearly' => 'boolean',
        'affects_rosters' => 'boolean',
        'blocks_rosters' => 'boolean',
        'is_active' => 'boolean',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
        });
    }

    public static function eventTypeLabels(): array
    {
        return [
            self::TYPE_PUBLIC_HOLIDAY => 'Public Holiday',
            self::TYPE_OBSERVANCE => 'Observance',
            self::TYPE_ORGANIZATION_EVENT => 'Organization Event',
            self::TYPE_TRAINING_DAY => 'Training Day',
            self::TYPE_PAYROLL_CUTOFF => 'Payroll Cutoff',
            self::TYPE_LEAVE_BLACKOUT => 'Leave Blackout',
            self::TYPE_OTHER => 'Other',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', self::APPROVAL_APPROVED);
    }

    public function scopePublicHolidays(Builder $query): Builder
    {
        return $query->where('event_type', self::TYPE_PUBLIC_HOLIDAY);
    }

    public function occursOn(CarbonInterface $date): bool
    {
        $start = $this->starts_on;
        $end = $this->ends_on ?? $this->starts_on;

        if (! $this->repeats_yearly) {
            return $date->toDateString() >= $start->toDateString()
                && $date->toDateString() <= $end->toDateString();
        }

        $cursor = $start->copy();

        while ($cursor->toDateString() <= $end->toDateString()) {
            if ($cursor->format('m-d') === $date->format('m-d')) {
                return true;
            }

            $cursor->addDay();
        }

        return false;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === self::APPROVAL_APPROVED;
    }
}
