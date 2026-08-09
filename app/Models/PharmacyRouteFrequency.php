<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PharmacyRouteFrequency extends Model
{
    protected $connection = 'clinical';

    protected $table = 'pharmacy_route_frequency_master';

    protected $fillable = [
        'business_id',
        'code',
        'type',
        'display_label',
        'minute_interval',
        'is_active',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'minute_interval' => 'integer',
        'is_active' => 'boolean',
    ];
}
