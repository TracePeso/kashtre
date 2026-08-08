<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PatientApprovedPoolLine extends Model
{
    protected $fillable = [
        'uuid',
        'business_id',
        'client_id',
        'item_id',
        'source_fulfillment_line_id',
        'invoice_id',
        'quantity_original',
        'quantity_remaining',
    ];

    protected $casts = [
        'quantity_original' => 'decimal:2',
        'quantity_remaining' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $line) {
            if (empty($line->uuid)) {
                $line->uuid = (string) Str::uuid();
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function sourceFulfillmentLine(): BelongsTo
    {
        return $this->belongsTo(InventoryFulfillmentLine::class, 'source_fulfillment_line_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
