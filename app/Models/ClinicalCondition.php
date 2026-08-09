<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalCondition extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_conditions';

    public $timestamps = false;

    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_RESOLVED = 'RESOLVED';
    const STATUS_INACTIVE = 'INACTIVE';

    protected $fillable = [
        'business_id',
        'branch_id',
        'client_id',
        'visit_id',
        'icd11_code',
        'description',
        'clinical_status',
        'recorded_by_user_id',
        'recorded_at',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'branch_id' => 'integer',
        'recorded_by_user_id' => 'integer',
        'recorded_at' => 'datetime',
    ];

    protected $attributes = [
        'clinical_status' => self::STATUS_ACTIVE,
    ];

    protected static function booted(): void
    {
        static::creating(function (ClinicalCondition $condition) {
            $condition->recorded_at ??= now();
        });
    }
}
