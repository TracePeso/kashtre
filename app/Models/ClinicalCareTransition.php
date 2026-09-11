<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Local index of a Care Transition (v6.1 Volume 8) this business started —
 * see the migration for why this exists. Never the source of truth for
 * `status`; only a memory of `public_id` so the panel can re-fetch it.
 */
class ClinicalCareTransition extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'client_id',
        'visit_id',
        'public_id',
        'transition_type',
        'status',
        'planned_at',
        'effective_at',
        'completed_at',
        'record_version',
        'created_by_user_id',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'branch_id' => 'integer',
        'record_version' => 'integer',
        'created_by_user_id' => 'integer',
        'planned_at' => 'datetime',
        'effective_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
