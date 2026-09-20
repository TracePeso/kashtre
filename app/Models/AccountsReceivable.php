<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\SharedTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AccountsReceivable extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'accounts_receivable';

    protected $fillable = [
        'uuid',
        'client_id',
        'third_party_payer_id',
        'business_id',
        'branch_id',
        'invoice_id',
        'transaction_id',
        'created_by',
        'amount_due',
        'amount_paid',
        'balance',
        'invoice_date',
        'due_date',
        'days_past_due',
        'aging_bucket',
        'status',
        'payer_type',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance' => 'decimal:2',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'days_past_due' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($ar) {
            if (empty($ar->uuid)) {
                $ar->uuid = (string) Str::uuid();
            }
            // Calculate balance
            if (empty($ar->balance)) {
                $ar->balance = $ar->amount_due - ($ar->amount_paid ?? 0);
            }
            // Set invoice_date if not provided
            if (empty($ar->invoice_date)) {
                $ar->invoice_date = SharedTime::businessToday($ar->business_id ? (string) $ar->business_id : null);
            }
            // Calculate aging
            $ar->updateAging();
        });

        static::updating(function ($ar) {
            // Recalculate balance
            $ar->balance = $ar->amount_due - $ar->amount_paid;
            // Update aging
            $ar->updateAging();
        });
    }

    // Relationships
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function thirdPartyPayer()
    {
        return $this->belongsTo(ThirdPartyPayer::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Helper methods
    public function updateAging()
    {
        if ($this->due_date) {
            $this->days_past_due = max(0, Carbon::parse($this->due_date)->diffInDays(now(), false));
            
            if ($this->days_past_due <= 30) {
                $this->aging_bucket = 'current';
            } elseif ($this->days_past_due <= 60) {
                $this->aging_bucket = 'days_30_60';
            } elseif ($this->days_past_due <= 90) {
                $this->aging_bucket = 'days_60_90';
            } else {
                $this->aging_bucket = 'over_90';
            }
        } else {
            $this->days_past_due = 0;
            $this->aging_bucket = 'current';
        }

        // Update status based on balance and days past due
        // For credit clients: balance > 0 means they owe money (not paid)
        if ($this->balance <= 0) {
            $this->status = 'paid';
        } elseif ($this->amount_paid > 0 && $this->balance > 0) {
            $this->status = 'partial';
        } elseif ($this->days_past_due > 0) {
            $this->status = 'overdue';
        } else {
            // If balance > 0, it's current (not paid yet)
            $this->status = $this->balance > 0 ? 'current' : 'paid';
        }
    }

    public function recordPayment($amount, $transactionId = null)
    {
        $this->amount_paid += $amount;
        $this->balance = $this->amount_due - $this->amount_paid;
        
        if ($transactionId) {
            $this->transaction_id = $transactionId;
        }
        
        $this->updateAging();
        $this->save();
    }
}
