<?php

namespace App\Domain\Units\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversionRuleContext extends Model
{
    protected $table = 'core_conversion_rule_contexts';

    protected $fillable = [
        'conversion_rule_id',
        'context_type',
        'context_public_id',
        'parameters',
    ];

    protected $casts = [
        'parameters' => 'array',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ConversionRule::class, 'conversion_rule_id');
    }
}
