<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class BusinessBalanceHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'money_account_id',
        'previous_balance',
        'amount',
        'new_balance',
        'type',
        'description',
        'reference_type',
        'reference_id',
        'metadata',
        'user_id',
        'payment_status',
        'payment_method',
    ];

    protected $casts = [
        'previous_balance' => 'decimal:2',
        'amount' => 'decimal:2',
        'new_balance' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Get the business that owns the balance statement
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the money account associated with this history
     */
    public function moneyAccount()
    {
        return $this->belongsTo(MoneyAccount::class);
    }

    /**
     * Get the user who performed the action
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reference model (polymorphic)
     */
    public function reference()
    {
        return $this->morphTo();
    }

    /**
     * Scope for credit transactions
     */
    public function scopeCredits($query)
    {
        return $query->where('type', 'credit');
    }

    /**
     * Scope for debit transactions
     */
    public function scopeDebits($query)
    {
        return $query->where('type', 'debit');
    }

    /**
     * Scope for package transactions
     */
    public function scopePackages($query)
    {
        return $query->where('type', 'package');
    }

    /**
     * Scope for a specific date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * For Kashtre credits: paid vs pending, treating matured service-fee pendings as paid for totals/display.
     */
    public function effectiveCreditPaymentStatus(): string
    {
        if ($this->type !== 'credit') {
            return 'paid';
        }

        $status = $this->payment_status ?? 'paid';
        if ($status !== 'pending_payment') {
            return 'paid';
        }

        $maturesAt = $this->metadata['service_charge_matures_at'] ?? $this->metadata['credit_matures_at'] ?? null;
        if (is_string($maturesAt) && $maturesAt !== '') {
            try {
                if (Carbon::parse($maturesAt)->lte(now())) {
                    return 'paid';
                }
            } catch (\Throwable) {
                // keep pending
            }
        }

        return 'pending_payment';
    }

    /**
     * Credit maturation instant (service charge on Kashtre, or entity revenue e.g. insurance on business account).
     */
    public function creditMaturityAt(): ?Carbon
    {
        if ($this->type !== 'credit') {
            return null;
        }

        $raw = $this->metadata['service_charge_matures_at'] ?? $this->metadata['credit_matures_at'] ?? null;
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Full calendar days remaining until credit_matures_at / service_charge_matures_at (0 = matures today or earlier).
     */
    public function creditDaysRemainingUntilMaturity(): ?int
    {
        $at = $this->creditMaturityAt();
        if ($at === null) {
            return null;
        }

        $today = Carbon::today();
        $end = $at->copy()->startOfDay();

        if ($end->lte($today)) {
            return 0;
        }

        return (int) $today->diffInDays($end);
    }

    /**
     * Short label for business balance statement "Days to mature" column.
     *
     * Em dash (—): no countdown applies (e.g. cash/mobile money, legacy credits without stored maturity).
     * "Available": matured / released hold (metadata still shows a maturity timestamp).
     * "Immediate": insurance treated as paid with zero-day entity maturation or rows created before hold tracking.
     */
    public function businessStatementCreditMaturityLabel(): string
    {
        if ($this->type !== 'credit') {
            return '—';
        }

        if ($this->effectiveCreditPaymentStatus() === 'paid') {
            if ($this->creditMaturityAt()) {
                return 'Available';
            }

            return ($this->payment_method ?? '') === 'insurance'
                ? 'Immediate'
                : '—';
        }

        $days = $this->creditDaysRemainingUntilMaturity();
        if ($days === null) {
            return '—';
        }

        return $days === 0 ? 'Matures today' : $days.' days';
    }

    public function businessStatementPaymentStatusDisplay(): string
    {
        if ($this->type !== 'credit') {
            return '—';
        }

        return $this->effectiveCreditPaymentStatus() === 'paid' ? 'Paid' : 'Pending';
    }

    public function businessStatementPaymentStatusBadgeClass(): string
    {
        if ($this->type !== 'credit') {
            return 'bg-gray-100 text-gray-600';
        }

        return $this->effectiveCreditPaymentStatus() === 'paid'
            ? 'bg-green-100 text-green-800'
            : 'bg-yellow-100 text-yellow-800';
    }

    public function countsTowardKashtreAvailableCredits(): bool
    {
        return $this->business_id === 1
            && $this->type === 'credit'
            && $this->effectiveCreditPaymentStatus() === 'paid';
    }

    public function countsTowardKashtrePendingCredits(): bool
    {
        return $this->business_id === 1
            && $this->type === 'credit'
            && $this->effectiveCreditPaymentStatus() === 'pending_payment';
    }

    public static function sumKashtreCreditAvailableAmount(): float
    {
        return (float) static::query()
            ->where('business_id', 1)
            ->where('type', 'credit')
            ->get()
            ->filter(fn (self $h): bool => $h->countsTowardKashtreAvailableCredits())
            ->sum('amount');
    }

    public static function sumKashtreCreditPendingAmount(): float
    {
        return (float) static::query()
            ->where('business_id', 1)
            ->where('type', 'credit')
            ->get()
            ->filter(fn (self $h): bool => $h->countsTowardKashtrePendingCredits())
            ->sum('amount');
    }

    public function kashtreStatementPaymentStatusDisplay(): string
    {
        if ($this->type !== 'credit') {
            return '—';
        }

        return $this->effectiveCreditPaymentStatus() === 'paid' ? 'Paid' : 'Pending';
    }

    public function kashtreStatementPaymentStatusBadgeClass(): string
    {
        if ($this->type !== 'credit') {
            return 'bg-gray-100 text-gray-600';
        }

        return $this->effectiveCreditPaymentStatus() === 'paid'
            ? 'bg-green-100 text-green-800'
            : 'bg-yellow-100 text-yellow-800';
    }

    public function kashtreStatementPaymentMethodLabel(): string
    {
        $metaMethod = $this->metadata['service_charge_maturation_payment_method'] ?? null;
        $method = is_string($metaMethod) && $metaMethod !== ''
            ? $metaMethod
            : ($this->payment_method ?? '');

        return $method !== ''
            ? ucwords(str_replace('_', ' ', $method))
            : '—';
    }

    /**
     * Kashtre statement column: days until service-fee / credit maturation (same rules as business credits).
     */
    public function kashtreStatementCreditMaturityLabel(): string
    {
        return $this->businessStatementCreditMaturityLabel();
    }

    /**
     * Record a balance change for a business
     */
    public static function recordChange($businessId, $moneyAccountId, $amount, $type, $description, $referenceType = null, $referenceId = null, $metadata = [], $userId = null, $paymentStatus = null, $paymentMethod = null)
    {
        $account = MoneyAccount::find($moneyAccountId);
        if (!$account) {
            throw new \Exception("Money account not found");
        }

        $previousBalance = $account->balance;
        $newBalance = $type === 'credit' ? $previousBalance + $amount : $previousBalance - $amount;

        $history = self::create([
            'business_id' => $businessId,
            'money_account_id' => $moneyAccountId,
            'previous_balance' => $previousBalance,
            'amount' => $amount,
            'new_balance' => $newBalance,
            'type' => $type,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'metadata' => $metadata,
            'user_id' => $userId,
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentMethod,
        ]);

        // Update the account balance
        $account->update(['balance' => $newBalance]);

        return $history;
    }

    /**
     * Record a package transaction for a business
     * Package transactions don't affect balance - they're just records
     */
    public static function recordPackageTransaction($businessId, $moneyAccountId, $amount, $description, $referenceType = null, $referenceId = null, $metadata = [], $userId = null)
    {
        \Log::info("=== CREATING PACKAGE TRANSACTION BUSINESS BALANCE HISTORY RECORD ===", [
            'business_id' => $businessId,
            'money_account_id' => $moneyAccountId,
            'amount' => $amount,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'metadata' => $metadata,
            'user_id' => $userId,
            'timestamp' => now()->toDateTimeString()
        ]);

        $account = MoneyAccount::find($moneyAccountId);
        if (!$account) {
            \Log::error("Money account not found for package transaction", [
                'money_account_id' => $moneyAccountId,
                'business_id' => $businessId
            ]);
            throw new \Exception("Money account not found");
        }

        \Log::info("Money account found for package transaction", [
            'money_account_id' => $moneyAccountId,
            'account_name' => $account->name,
            'account_type' => $account->type,
            'current_balance' => $account->balance
        ]);

        $previousBalance = $account->balance;
        // Package transactions don't change balance - they're just records
        $newBalance = $previousBalance;

        $businessBalanceHistoryData = [
            'business_id' => $businessId,
            'money_account_id' => $moneyAccountId,
            'previous_balance' => $previousBalance,
            'amount' => $amount,
            'new_balance' => $newBalance,
            'type' => 'package',
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'metadata' => $metadata,
            'user_id' => $userId,
        ];

        \Log::info("Creating BusinessBalanceHistory record for package transaction", [
            'business_balance_history_data' => $businessBalanceHistoryData
        ]);

        $history = self::create($businessBalanceHistoryData);

        \Log::info("Package transaction BusinessBalanceHistory record created successfully", [
            'business_balance_history_id' => $history->id,
            'business_id' => $businessId,
            'money_account_id' => $moneyAccountId,
            'type' => 'package',
            'amount' => $amount,
            'description' => $description,
            'note' => 'No balance update for package transactions - they are records only'
        ]);

        // No balance update for package transactions
        // The account balance remains the same

        return $history;
    }
}
