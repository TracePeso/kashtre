<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalConsumptionException extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_consumption_exceptions';

    protected $fillable = [
        'business_id',
        'client_id',
        'item_code',
        'exception_reason',
        'resolved',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'resolved' => 'boolean',
        'resolved_by_user_id' => 'integer',
        'resolved_at' => 'datetime',
    ];

    public function scopeUnresolved($query)
    {
        return $query->where('resolved', false);
    }
}
