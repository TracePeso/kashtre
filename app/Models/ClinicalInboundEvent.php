<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalInboundEvent extends Model
{
    protected $fillable = [
        'event_id',
        'fact_token',
        'business_id',
        'payload',
        'response',
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
    ];
}
