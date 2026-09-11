<?php

namespace App\Domain\Units\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversionRule extends Model
{
    protected $table = 'core_conversion_rules';

    protected $fillable = [
        'public_id',
        'tenant_key',
        'from_unit_id',
        'to_unit_id',
        'rule_type',
        'scale_decimal',
        'offset_decimal',
        'named_algorithm',
        'is_bidirectional',
        'calculation_scale',
        'display_precision',
        'rounding_mode',
        'min_input_decimal',
        'max_input_decimal',
        'version_no',
        'status',
        'effective_from',
        'effective_to',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected $casts = [
        'is_bidirectional' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(CoreUnit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(CoreUnit::class, 'to_unit_id');
    }

    public function contexts(): HasMany
    {
        return $this->hasMany(ConversionRuleContext::class, 'conversion_rule_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'ACTIVE');
    }
}
