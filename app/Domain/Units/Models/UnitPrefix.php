<?php

namespace App\Domain\Units\Models;

use Illuminate\Database\Eloquent\Model;

class UnitPrefix extends Model
{
    protected $table = 'core_unit_prefixes';

    protected $fillable = [
        'public_id',
        'tenant_key',
        'code',
        'name',
        'symbol',
        'radix',
        'exponent',
        'factor_decimal',
        'is_binary',
        'is_system',
        'status',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'is_binary' => 'boolean',
        'is_system' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
