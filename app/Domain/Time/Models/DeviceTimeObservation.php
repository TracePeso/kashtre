<?php

namespace App\Domain\Time\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DeviceTimeObservation extends Model
{
    protected $table = 'core_device_time_observations';

    protected $fillable = [
        'public_id',
        'tenant_key',
        'device_public_id',
        'raw_timestamp',
        'raw_timezone',
        'normalized_at_utc',
        'normalized_local_datetime',
        'status',
        'confidence',
        'quarantine_reason',
        'metadata',
    ];

    protected $casts = [
        'normalized_at_utc' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->public_id) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
