<?php

namespace App\Domain\Units\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuantityKind extends Model
{
    protected $table = 'core_quantity_kinds';

    protected $fillable = [
        'public_id',
        'tenant_key',
        'code',
        'name',
        'description',
        'dimension_vector',
        'is_system',
        'status',
        'effective_from',
        'effective_to',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'dimension_vector' => 'array',
        'is_system' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function units(): HasMany
    {
        return $this->hasMany(CoreUnit::class, 'quantity_kind_id');
    }
}
