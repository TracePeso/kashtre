<?php

namespace App\Domain\Time\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ScheduleOccurrence extends Model
{
    protected $table = 'core_schedule_occurrences';

    protected $fillable = [
        'public_id',
        'schedule_definition_id',
        'occurred_at_utc',
        'occurred_local_datetime',
        'iana_id',
        'utc_offset_minutes',
        'materialization_note',
    ];

    protected $casts = [
        'occurred_at_utc' => 'datetime',
        'utc_offset_minutes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->public_id) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ScheduleDefinition::class, 'schedule_definition_id');
    }
}
