<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * "A pharmacy/ward staff member has seen this Clinical-chart floor-stock
 * line and dealt with it" — nothing more. See the migration for why this
 * exists and what it deliberately does not do.
 */
class ClinicalFloorStockReview extends Model
{
    protected $fillable = [
        'business_id',
        'clinical_event_id',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'reviewed_by_user_id' => 'integer',
        'reviewed_at' => 'datetime',
    ];
}
