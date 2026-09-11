<?php

namespace App\Domain\Units\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleUnitPolicy extends Model
{
    protected $table = 'core_module_unit_policies';

    protected $fillable = [
        'public_id',
        'tenant_key',
        'module_code',
        'domain_object_type',
        'domain_object_public_id',
        'unit_id',
        'usage_role',
        'display_precision',
        'status',
        'effective_from',
        'effective_to',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CoreUnit::class, 'unit_id');
    }
}
