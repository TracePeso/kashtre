<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HrClientSpaceRoute extends Model
{
    protected $fillable = [
        'uuid',
        'organization_id',
        'client_space_unit_id',
        'routing_unit_id',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function clientSpaceUnit()
    {
        return $this->belongsTo(HrOrganizationalUnit::class, 'client_space_unit_id');
    }

    public function routingUnit()
    {
        return $this->belongsTo(HrOrganizationalUnit::class, 'routing_unit_id');
    }
}
