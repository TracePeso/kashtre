<?php

namespace App\Domain\Units\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitComponent extends Model
{
    protected $table = 'core_unit_components';

    protected $fillable = [
        'unit_version_id',
        'sequence',
        'operator',
        'component_unit_id',
        'prefix_id',
        'exponent',
        'scalar_decimal',
        'annotation',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(UnitVersion::class, 'unit_version_id');
    }

    public function componentUnit(): BelongsTo
    {
        return $this->belongsTo(CoreUnit::class, 'component_unit_id');
    }
}
