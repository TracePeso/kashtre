<?php

namespace App\Domain\Time\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CoreTimeZone extends Model
{
    protected $table = 'core_time_zones';

    protected $fillable = [
        'public_id',
        'iana_id',
        'display_name',
        'region_code',
        'canonical_iana_id',
        'status',
        'tzdb_release',
        'is_fixed_offset',
    ];

    protected $casts = [
        'is_fixed_offset' => 'boolean',
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

    public function canonicalId(): string
    {
        return $this->canonical_iana_id ?: $this->iana_id;
    }
}
