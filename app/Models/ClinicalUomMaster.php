<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalUomMaster extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_uom_master';

    protected $fillable = [
        'business_id',
        'unit_label',
        'ucum_code',
        'category',
        'description',
        'is_active',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'is_active' => 'boolean',
    ];
}
