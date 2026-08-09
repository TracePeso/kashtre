<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalEntitlement extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_entitlements';

    protected $fillable = [
        'business_id',
        'client_id',
        'package_id',
        'service_code',
        'allocated_qty',
        'used_qty',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'allocated_qty' => 'integer',
        'used_qty' => 'integer',
        'remaining_qty' => 'integer',
    ];
}
