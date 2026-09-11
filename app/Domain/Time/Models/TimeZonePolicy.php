<?php

namespace App\Domain\Time\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TimeZonePolicy extends Model
{
    protected $table = 'core_time_zone_policies';

    protected $fillable = [
        'public_id',
        'tenant_key',
        'scope_type',
        'subject_public_id',
        'purpose',
        'iana_id',
        'version_no',
        'status',
        'effective_from',
        'effective_to',
        'reason',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'approved_at' => 'datetime',
        'version_no' => 'integer',
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
