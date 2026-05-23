<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class HrBiometricProfile extends Model
{
    use SoftDeletes;

    public const MODALITY_FINGERPRINT = 'fingerprint';
    public const MODALITY_FACE = 'face';

    protected $fillable = [
        'uuid',
        'organization_id',
        'staff_assignment_id',
        'staff_uuid',
        'staff_name',
        'modality',
        'label',
        'provider',
        'device_id',
        'external_reference',
        'template_digest',
        'template_payload',
        'face_descriptor',
        'quality_score',
        'verification_threshold',
        'status',
        'enrolled_by_user_id',
        'enrolled_at',
        'last_verified_at',
        'metadata',
    ];

    protected $hidden = [
        'template_payload',
        'face_descriptor',
    ];

    protected $casts = [
        'template_payload' => 'encrypted',
        'face_descriptor' => 'encrypted:array',
        'quality_score' => 'float',
        'verification_threshold' => 'float',
        'enrolled_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'metadata' => 'array',
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

    public function staffAssignment()
    {
        return $this->belongsTo(StaffAssignment::class);
    }

    public function enrolledBy()
    {
        return $this->belongsTo(User::class, 'enrolled_by_user_id');
    }

    public function verifications()
    {
        return $this->hasMany(HrBiometricVerification::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForModality(Builder $query, string $modality): Builder
    {
        return $query->where('modality', $modality);
    }

    public function isFingerprint(): bool
    {
        return $this->modality === self::MODALITY_FINGERPRINT;
    }

    public function isFace(): bool
    {
        return $this->modality === self::MODALITY_FACE;
    }
}
