<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalEscalationRule extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_escalation_rules';

    protected $fillable = [
        'business_id',
        'severity_tier',
        'color_hex',
        'auditory_signal',
        'screen_action',
        'target_roles',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'target_roles' => 'array',
    ];
}
