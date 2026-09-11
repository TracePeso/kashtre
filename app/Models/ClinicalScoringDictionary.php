<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalScoringDictionary extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_scoring_dictionaries';

    protected $fillable = [
        'business_id',
        'score_code',
        'score_name',
        'matrix_payload',
        'version',
        'is_active',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'matrix_payload' => 'array',
        'is_active' => 'boolean',
    ];
}
