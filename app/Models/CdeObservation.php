<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CdeObservation extends Model
{
    protected $connection = 'clinical';

    protected $table = 'cde_observations';

    const METHOD_MANUAL = 'MANUAL';
    const METHOD_VOICE_DICTATION = 'VOICE_DICTATION';
    const METHOD_DEVICE_IMPORT = 'DEVICE_IMPORT';
    const METHOD_CALCULATED = 'CALCULATED';
    const METHOD_IMPORTED_DATA = 'IMPORTED_DATA';

    public $timestamps = false;

    protected $fillable = [
        'business_id',
        'branch_id',
        'client_id',
        'visit_id',
        'cde_code',
        'captured_value_numeric',
        'captured_value_text',
        'captured_value_json',
        'input_uom_id',
        'base_uom_id',
        'base_value_numeric',
        'capture_method',
        'validation_status',
        'validated_by_user_id',
        'captured_at',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'branch_id' => 'integer',
        'captured_value_numeric' => 'decimal:4',
        'captured_value_json' => 'array',
        'input_uom_id' => 'integer',
        'base_uom_id' => 'integer',
        'base_value_numeric' => 'decimal:4',
        'validated_by_user_id' => 'integer',
        'captured_at' => 'datetime',
    ];

    protected $attributes = [
        'capture_method' => self::METHOD_MANUAL,
        'validation_status' => 'VALIDATED',
    ];

    public function inputUnit()
    {
        return $this->belongsTo(ClinicalUomMaster::class, 'input_uom_id');
    }

    public function baseUnit()
    {
        return $this->belongsTo(ClinicalUomMaster::class, 'base_uom_id');
    }
}
