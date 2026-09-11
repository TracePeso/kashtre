<?php

namespace App\Domain\Units\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoreUnit extends Model
{
    protected $table = 'core_units';

    protected $fillable = [
        'public_id',
        'tenant_key',
        'code',
        'unit_class',
        'canonical_name',
        'symbol',
        'ascii_symbol',
        'standard_system_uri',
        'ucum_code',
        'ucum_version',
        'standard_verification_status',
        'quantity_kind_id',
        'dimension_vector',
        'allows_prefix',
        'allows_composition',
        'is_system',
        'status',
        'replaced_by_unit_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'dimension_vector' => 'array',
        'allows_prefix' => 'boolean',
        'allows_composition' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function quantityKind(): BelongsTo
    {
        return $this->belongsTo(QuantityKind::class, 'quantity_kind_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(UnitVersion::class, 'unit_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(UnitAlias::class, 'unit_id');
    }

    public function scopeForTenant(Builder $query, string $tenantKey): Builder
    {
        return $query->where(function (Builder $q) use ($tenantKey) {
            $q->where('tenant_key', $tenantKey)
                ->orWhere('tenant_key', config('units.system_tenant_key', 'SYSTEM'));
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'ACTIVE');
    }
}
