<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalUomConversion extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_uom_conversions_master';

    protected $fillable = [
        'business_id',
        'cde_code',
        'from_unit_id',
        'to_unit_id',
        'conversion_type',
        'factor',
        'formula_expression',
        'decimal_precision',
        'is_active',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'factor' => 'decimal:8',
        'decimal_precision' => 'integer',
        'is_active' => 'boolean',
    ];

    public function fromUnit()
    {
        return $this->belongsTo(ClinicalUomMaster::class, 'from_unit_id');
    }

    public function toUnit()
    {
        return $this->belongsTo(ClinicalUomMaster::class, 'to_unit_id');
    }
}
