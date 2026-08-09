<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalBillingEvent extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_billing_events';

    const STATUS_PENDING = 'PENDING';

    protected $fillable = [
        'business_id',
        'branch_id',
        'client_id',
        'visit_id',
        'consumption_event_id',
        'reason',
        'item_code',
        'quantity',
        'amount',
        'status',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'branch_id' => 'integer',
        'consumption_event_id' => 'integer',
        'quantity' => 'decimal:4',
        'amount' => 'decimal:2',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    public function consumptionEvent()
    {
        return $this->belongsTo(ClinicalConsumptionEvent::class, 'consumption_event_id');
    }
}
