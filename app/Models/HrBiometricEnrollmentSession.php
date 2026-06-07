<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class HrBiometricEnrollmentSession extends Model
{
    protected $fillable = [
        'uuid',
        'organization_id',
        'staff_assignment_id',
        'staff_uuid',
        'staff_name',
        'recipient_email',
        'purpose',
        'secret_code_hash',
        'secret_code_sent_at',
        'secret_code_expires_at',
        'confirmed_at',
        'capture_deadline_at',
        'completed_at',
        'invalidated_at',
        'authorized_by_user_id',
        'completed_by_user_id',
        'metadata',
    ];

    protected $casts = [
        'secret_code_sent_at' => 'datetime',
        'secret_code_expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'capture_deadline_at' => 'datetime',
        'completed_at' => 'datetime',
        'invalidated_at' => 'datetime',
        'metadata' => 'array',
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
        return $this->belongsTo(StaffAssignment::class);
    }

    public function authorizedBy()
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query
            ->whereNull('completed_at')
            ->whereNull('invalidated_at');
    }

    public function isPendingCode(): bool
    {
        return $this->confirmed_at === null
            && $this->completed_at === null
            && $this->invalidated_at === null;
    }

    public function isAuthorized(): bool
    {
        return $this->confirmed_at !== null
            && $this->completed_at === null
            && $this->invalidated_at === null
            && $this->capture_deadline_at instanceof Carbon
            && $this->capture_deadline_at->isFuture();
    }

    public function codeExpired(): bool
    {
        return $this->secret_code_expires_at instanceof Carbon
            && $this->secret_code_expires_at->isPast();
    }

    public function captureWindowExpired(): bool
    {
        return $this->capture_deadline_at instanceof Carbon
            && $this->capture_deadline_at->isPast();
    }
}
