<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_invoice_id',
        'vendor_portion_label',
        'vendor_portion_priority',
        'invoice_number',
        'client_id',
        'business_id',
        'branch_id',
        'client_space_id',
        'created_by',
        'client_name',
        'client_phone',
        'payment_phone',
        'visit_id',
        'items',
        'subtotal',
        'package_adjustment',
        'account_balance_adjustment',
        'service_charge',
        'total_amount',
        'amount_paid',
        'balance_due',
        'payment_methods',
        'payment_status',
        'notes',
        'insurance_authorization_reference',
        'insurance_client_total',
        'insurance_insurance_total',
        'insurance_confirmation_code',
        'insurance_authorized_at',
        'insurance_authorization_snapshot',
        'status',
        'confirmed_at',
        'printed_at',
        'is_service_charge_processed',
        'service_charge_processed_at',
        'service_charge_processed_by_user_id',
        'currency',
    ];

    protected $casts = [
        'items' => 'array',
        'payment_methods' => 'array',
        'subtotal' => 'decimal:2',
        'package_adjustment' => 'decimal:2',
        'account_balance_adjustment' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'insurance_client_total' => 'decimal:2',
        'insurance_insurance_total' => 'decimal:2',
        'insurance_authorized_at' => 'datetime',
        'insurance_authorization_snapshot' => 'array',
        'confirmed_at' => 'datetime',
        'printed_at' => 'datetime',
        'is_service_charge_processed' => 'boolean',
        'service_charge_processed_at' => 'datetime',
    ];

    // Relationships
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function clientSpace()
    {
        return $this->belongsTo(ClientSpace::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * Primary POS invoice when this row is a vendor-portion trace copy (multi-insurer cascade).
     */
    public function parentInvoice()
    {
        return $this->belongsTo(self::class, 'parent_invoice_id');
    }

    /**
     * Labeled subset invoices (A, B, C, …) created for each insurer submission in a cascade.
     */
    public function vendorPortionInvoices()
    {
        return $this->hasMany(self::class, 'parent_invoice_id')->orderBy('vendor_portion_priority');
    }

    public function thirdPartyPayerBalanceHistories()
    {
        return $this->hasMany(ThirdPartyPayerBalanceHistory::class, 'invoice_id');
    }

    public function isVendorPortionInvoice(): bool
    {
        return $this->parent_invoice_id !== null;
    }

    // Scopes
    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentStatus($query, $paymentStatus)
    {
        return $query->where('payment_status', $paymentStatus);
    }

    /** Exclude insurer cascade trace rows (children of a primary invoice). */
    public function scopeRootInvoices($query)
    {
        return $query->whereNull('parent_invoice_id');
    }

    // Methods
    
    public function isServiceChargeProcessed()
    {
        return $this->is_service_charge_processed === true;
    }
    /**
     * Next invoice number for the current calendar month.
     * $businessId is kept for callers' convenience; invoice_number is unique across all businesses,
     * so the sequence must be global for a given prefix + YYYYMM (not per-business).
     */
    public static function generateInvoiceNumber($businessId, $type = 'invoice')
    {
        $prefix = ($type === 'proforma') ? 'P' : 'INV';
        $year = date('Y');
        $month = date('m');
        $base = $prefix . $year . $month;
        $expectedLength = strlen($base) + 4;

        // Only strict P2026051234 / INV2026051234 rows (excludes legacy suffixes like P2026050009-PA).
        $maxSeq = (int) self::query()
            ->where('invoice_number', 'like', $base.'%')
            ->whereRaw('LENGTH(invoice_number) = ?', [$expectedLength])
            ->pluck('invoice_number')
            ->map(static fn ($num) => (int) substr((string) $num, -4))
            ->max();

        $newNumber = $maxSeq > 0 ? $maxSeq + 1 : 1;

        if ($newNumber > 9999) {
            Log::critical('Invoice number monthly sequence overflow', ['base' => $base, 'max' => $maxSeq]);

            throw new \RuntimeException("Invoice number sequence overflow for {$base}; contact support.");
        }

        return $base.str_pad((string) $newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function confirm()
    {
        $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function markAsPrinted()
    {
        $this->update([
            'printed_at' => now(),
        ]);
    }

    public function cancel()
    {
        $this->update([
            'status' => 'cancelled',
        ]);
    }

    public function updatePaymentStatus()
    {
        if ($this->amount_paid >= $this->total_amount) {
            $this->payment_status = 'paid';
        } elseif ($this->amount_paid > 0) {
            $this->payment_status = 'partial';
        } else {
            $this->payment_status = 'pending';
        }
        
        $this->balance_due = $this->total_amount - $this->amount_paid;
        $this->save();
    }

    // Accessors
    public function getFormattedInvoiceNumberAttribute()
    {
        return $this->invoice_number;
    }

    public function getFormattedTotalAmountAttribute()
    {
        return ($this->currency ?? 'USD') . ' ' . number_format($this->total_amount, 2);
    }

    public function getFormattedBalanceDueAttribute()
    {
        return ($this->currency ?? 'USD') . ' ' . number_format($this->balance_due, 2);
    }

    public function getStatusBadgeAttribute()
    {
        $badges = [
            'draft' => 'bg-gray-100 text-gray-800',
            'confirmed' => 'bg-blue-100 text-blue-800',
            'printed' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
        ];
        
        return $badges[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    public function getPaymentStatusBadgeAttribute()
    {
        $badges = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'pending_payment' => 'bg-yellow-100 text-yellow-800',
            'partial' => 'bg-orange-100 text-orange-800',
            'paid' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
        ];
        
        return $badges[$this->payment_status] ?? 'bg-gray-100 text-gray-800';
    }
}
