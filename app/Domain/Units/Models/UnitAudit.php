<?php

namespace App\Domain\Units\Models;

use Illuminate\Database\Eloquent\Model;

class UnitAudit extends Model
{
    protected $table = 'core_unit_audit';

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
        'ip_address',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];
}
