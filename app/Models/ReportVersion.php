<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'imaging_report_id',
        'modifier_user_id',
        'status',
        'historical_payload_snapshot',
        'amendment_justification_reason',
    ];

    protected $casts = [
        'historical_payload_snapshot' => 'array',
    ];

    public function imagingReport()
    {
        return $this->belongsTo(ImagingReport::class);
    }

    public function modifier()
    {
        return $this->belongsTo(User::class, 'modifier_user_id');
    }
}
