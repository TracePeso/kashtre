<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class HrClientSpace extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'organization_id',
        'organizational_unit_id',
        'external_uuid',
        'external_business_uuid',
        'external_branch_uuid',
        'source_name',
        'source_payload',
        'last_synced_at',
        'active',
    ];

    protected $casts = [
        'source_payload' => 'array',
        'last_synced_at' => 'datetime',
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function organizationalUnit()
    {
        return $this->belongsTo(HrOrganizationalUnit::class, 'organizational_unit_id');
    }
}
