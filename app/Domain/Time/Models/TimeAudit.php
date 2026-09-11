<?php

namespace App\Domain\Time\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TimeAudit extends Model
{
    protected $table = 'core_time_audit';

    protected $fillable = [
        'event_id',
        'tenant_key',
        'actor_user_id',
        'action',
        'object_type',
        'object_public_id',
        'before',
        'after',
        'reason',
        'correlation_id',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->event_id) {
                $model->event_id = (string) Str::ulid();
            }
        });
    }
}
