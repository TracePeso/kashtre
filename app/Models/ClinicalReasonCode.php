<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalReasonCode extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_reason_codes_master';

    protected $fillable = [
        'business_id',
        'category_code',
        'reason_code',
        'display_label',
        'requires_free_text',
        'is_active',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'requires_free_text' => 'boolean',
        'is_active' => 'boolean',
    ];
}
