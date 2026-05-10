<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;

class MoneyTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'from_account_id',
        'to_account_id',
        'amount',
        'currency',
        'status',
        'type',
        'transfer_type',
        'package_tracking_number',
        'invoice_id',
        'client_id',
        'item_id',
        'package_usage_id',
        'reference',
        'description',
        'metadata',
        'processed_at',
        'money_moved_to_final_account',
        'moved_to_final_at',
        'final_movement_notes',
        'source',
        'destination'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'processed_at' => 'datetime',
        'money_moved_to_final_account' => 'boolean',
        'moved_to_final_at' => 'datetime',
    ];

    // Relationships
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function fromAccount()
    {
        return $this->belongsTo(MoneyAccount::class, 'from_account_id');
    }

    public function toAccount()
    {
        return $this->belongsTo(MoneyAccount::class, 'to_account_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function packageUsage()
    {
        return $this->belongsTo(PackageUsage::class);
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('transfer_type', $type);
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function scopeForInvoice($query, $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    // Methods
    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now()
        ]);
        return $this;
    }

    public function markAsFailed()
    {
        $this->update(['status' => 'failed']);
        return $this;
    }

    public function markAsCancelled()
    {
        $this->update(['status' => 'cancelled']);
        return $this;
    }

    public function markMoneyMovedToFinalAccount($notes = null)
    {
        $this->update([
            'money_moved_to_final_account' => true,
            'moved_to_final_at' => now(),
            'final_movement_notes' => $notes
        ]);
        
        // NO automatic debit creation - we'll handle this manually in the service
        
        return $this;
    }

    public function hasMoneyMovedToFinalAccount()
    {
        return $this->money_moved_to_final_account;
    }

    public function resetMoneyMovementToFinal()
    {
        $this->update([
            'money_moved_to_final_account' => false,
            'moved_to_final_at' => null,
            'final_movement_notes' => null
        ]);
        return $this;
    }

    /**
     * Create a corresponding debit record when money moves out of suspense
     * This creates a debit record that reverses the original credit transfer
     */
    public function createDebitRecord($notes = null)
    {
        return self::create([
            'business_id' => $this->business_id,
            'from_account_id' => $this->to_account_id,    // Reverse: from destination
            'to_account_id' => $this->from_account_id,   // Reverse: to source
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => 'completed',
            'type' => 'debit',
            'transfer_type' => $this->transfer_type,
            'package_tracking_number' => $this->package_tracking_number,
            'invoice_id' => $this->invoice_id,
            'client_id' => $this->client_id,
            'item_id' => $this->item_id,
            'description' => $this->description,
            'processed_at' => now(),
            'money_moved_to_final_account' => true,
            'moved_to_final_at' => now(),
            'final_movement_notes' => $notes
        ]);
    }

    /**
     * Source / destination shown in suspense UIs; replaces generic "Insurance Company" using invoice payer resolution.
     */
    public function resolvedInsuranceSourceDestinationLabel(): string
    {
        $raw = $this->type === 'credit'
            ? ($this->source ?? $this->fromAccount?->name ?? 'N/A')
            : ($this->destination ?? $this->toAccount?->name ?? 'N/A');

        $raw = (string) $raw;

        if (!$this->invoice_id || !str_contains($raw, 'Insurance Company')) {
            return $raw;
        }

        $invoice = $this->invoice;
        if (!$invoice) {
            return $raw;
        }

        $resolved = app(\App\Services\MoneyTrackingService::class)->resolveInsuranceCounterpartyLabel($invoice);
        if ($resolved === '' || $resolved === 'Insurance Company') {
            return $raw;
        }

        return str_replace('Insurance Company', $resolved, $raw);
    }

    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }

    protected static function booted()
    {
        static::creating(function ($transfer) {
            $transfer->uuid = (string) Str::uuid();
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
