<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContrastAdministration extends Model
{
    use HasFactory;

    protected $fillable = [
        'imaging_study_id',
        'contrast_vial_id',
        'contrast_agent_name',
        'volume_ml',
        'injection_time',
        'adverse_reactions',
        'administered_by_user_id',
    ];

    protected $casts = [
        'volume_ml' => 'decimal:2',
        'injection_time' => 'datetime',
    ];

    public function imagingStudy()
    {
        return $this->belongsTo(ImagingStudy::class);
    }

    public function contrastVial()
    {
        return $this->belongsTo(ContrastVial::class);
    }

    public function resolveAdministeredBy(): ?User
    {
        return User::find($this->administered_by_user_id);
    }
}
