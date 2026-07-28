<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImagingConsumption extends Model
{
    use HasFactory;

    protected $fillable = [
        'imaging_study_id',
        'item_id',
        'inventory_sku',
        'quantity_used',
        'consumption_type',
    ];

    protected $casts = [
        'quantity_used' => 'decimal:4',
    ];

    const TYPE_PATIENT_PROCEDURE = 'PATIENT_PROCEDURE';
    const TYPE_WASTAGE = 'WASTAGE';
    const TYPE_CALIBRATION_RUN = 'CALIBRATION_RUN';

    public function imagingStudy()
    {
        return $this->belongsTo(ImagingStudy::class);
    }

    public function resolveItem(): ?Item
    {
        return $this->item_id ? Item::find($this->item_id) : null;
    }
}
