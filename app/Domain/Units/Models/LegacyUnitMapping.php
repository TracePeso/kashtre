<?php

namespace App\Domain\Units\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyUnitMapping extends Model
{
    protected $table = 'core_legacy_unit_mappings';

    protected $fillable = [
        'tenant_key',
        'source_module',
        'source_table',
        'source_value',
        'unit_id',
        'match_method',
        'status',
        'reviewed_by',
        'reviewed_at',
        'notes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CoreUnit::class, 'unit_id');
    }
}
