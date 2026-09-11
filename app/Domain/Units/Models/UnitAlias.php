<?php

namespace App\Domain\Units\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitAlias extends Model
{
    protected $table = 'core_unit_aliases';

    protected $fillable = [
        'public_id',
        'tenant_key',
        'unit_id',
        'alias',
        'locale',
        'scope_module',
        'is_preferred',
        'status',
    ];

    protected $casts = [
        'is_preferred' => 'boolean',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CoreUnit::class, 'unit_id');
    }
}
