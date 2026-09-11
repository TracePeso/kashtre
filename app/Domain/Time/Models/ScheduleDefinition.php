<?php

namespace App\Domain\Time\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ScheduleDefinition extends Model
{
    protected $table = 'core_schedule_definitions';

    protected $fillable = [
        'public_id',
        'tenant_key',
        'code',
        'name',
        'iana_id',
        'local_time',
        'recurrence',
        'recurrence_rules',
        'dst_gap_policy',
        'dst_overlap_policy',
        'local_start_date',
        'local_end_date',
        'status',
    ];

    protected $casts = [
        'recurrence_rules' => 'array',
        'local_start_date' => 'date',
        'local_end_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->public_id) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(ScheduleOccurrence::class, 'schedule_definition_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
