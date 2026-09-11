<?php

namespace App\Domain\Units\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitVersion extends Model
{
    protected $table = 'core_unit_versions';

    protected $fillable = [
        'public_id',
        'unit_id',
        'version_no',
        'scale_decimal',
        'offset_decimal',
        'reference_unit_public_id',
        'named_algorithm',
        'calculation_scale',
        'display_precision',
        'rounding_mode',
        'localized_labels',
        'metadata',
        'effective_from',
        'effective_to',
        'status',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected $casts = [
        'localized_labels' => 'array',
        'metadata' => 'array',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CoreUnit::class, 'unit_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(UnitComponent::class, 'unit_version_id');
    }
}
