<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HrOrganizationTierLevel extends Model
{
    protected $fillable = [
        'uuid',
        'organization_id',
        'name',
        'level_order',
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

    public function units()
    {
        return $this->hasMany(HrOrganizationalUnit::class, 'tier_level_id');
    }
}
