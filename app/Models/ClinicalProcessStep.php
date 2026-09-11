<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalProcessStep extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_process_steps';

    const EFFECT_ALLOCATE_BED = 'ALLOCATE_BED';
    const EFFECT_RELEASE_BED = 'RELEASE_BED';

    protected $fillable = [
        'process_id',
        'step_order',
        'step_code',
        'step_name',
        'is_mandatory',
        'required_role',
        'side_effect',
    ];

    protected $casts = [
        'process_id' => 'integer',
        'step_order' => 'integer',
        'is_mandatory' => 'boolean',
    ];

    public function process()
    {
        return $this->belongsTo(ClinicalProcess::class, 'process_id');
    }
}
