<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalModuleAlias extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_module_aliases';

    protected $fillable = [
        'business_id',
        'module_code',
        'display_name',
        'is_active',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'is_active' => 'boolean',
    ];
}
