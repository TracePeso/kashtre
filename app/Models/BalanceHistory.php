<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BalanceHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'business_id',
        'branch_id',
        'invoice_id',
        'user_id',
        'previous_balance',
        'change_amount',
        'new_balance',
        'transaction_type',
        'description',
        'reference_number',
        'notes',
        'payment_method',
        'payment_reference',
        'payment_status',
    ];

    protected $casts = [
        'previous_balance' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'new_balance' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function scopeByTransactionType($query, $type)
    {
        return $query->where('transaction_type', $type);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Insurance invoice line items are stored as statement rows (payment_method=insurance, often change_amount=0)
     * so the client can see what the insurer covered; they must not move the client's running balance.
     */
    public function scopeAffectingClientBalance($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('payment_method')
                ->orWhere('payment_method', '!=', 'insurance');
        });
    }

    /**
     * Third-party / insurer label for insurance informational statement rows.
     * Prefer explicit "Paid by …" in notes, then invoice authorization snapshot, then client's insurer.
     */
    public function insurancePayerDisplayName(): ?string
    {
        if ($this->payment_method !== 'insurance') {
            return null;
        }

        $notes = (string) ($this->notes ?? '');
        $fromNotes = self::extractPaidByFromInsuranceNotes($notes);
        if ($fromNotes !== null) {
            return $fromNotes;
        }

        $invoice = $this->invoice;
        if ($invoice) {
            $snap = $invoice->insurance_authorization_snapshot;
            if (is_array($snap)) {
                $fromSnap = self::payerNameFromInsuranceSnapshot($snap);
                if ($fromSnap !== null && $fromSnap !== '') {
                    return $fromSnap;
                }
            }
        }

        $client = $this->relationLoaded('client') ? $this->client : null;
        if (! $client && $this->client_id) {
            $client = Client::query()->with('insuranceCompany')->find($this->client_id);
        } elseif ($client && ! $client->relationLoaded('insuranceCompany')) {
            $client->load('insuranceCompany');
        }

        if ($client && $client->insuranceCompany) {
            return $client->insuranceCompany->name;
        }

        return null;
    }

    /**
     * Label shown in "[…]" on statements: insurer covering this line when snapshot / notes allow
     * (multi-vendor cascade uses per-item excluded_items; falls back to Paid-by notes then aggregate payer).
     */
    public function statementInsuranceBracketLabel(): ?string
    {
        if ($this->payment_method !== 'insurance') {
            return null;
        }

        $invoice = $this->invoice;
        if (! $invoice && $this->invoice_id) {
            $invoice = Invoice::query()->find($this->invoice_id);
        }

        $parsed = self::parseInsuranceStatementDescriptionLine((string) $this->description);

        if ($invoice && is_array($invoice->insurance_authorization_snapshot)) {
            $fromSnap = self::resolveInsuranceVendorNameFromSnapshotForLine(
                $invoice->insurance_authorization_snapshot,
                $invoice,
                $parsed
            );
            if ($fromSnap !== null && $fromSnap !== '') {
                return self::preferInsuranceCompanyRegisteredName($fromSnap, $invoice);
            }
        }

        $fromNotes = self::extractPaidByFromInsuranceNotes((string) ($this->notes ?? ''));
        if ($fromNotes !== null && $fromNotes !== '') {
            return self::preferInsuranceCompanyRegisteredName($fromNotes, $invoice);
        }

        $fallback = $this->insurancePayerDisplayName();

        return self::preferInsuranceCompanyRegisteredName($fallback, $invoice);
    }

    private static function extractPaidByFromInsuranceNotes(string $notes): ?string
    {
        if (preg_match('/\bPaid by\s+(.+)/', $notes, $m)) {
            $name = trim($m[1]);

            return $name !== '' ? $name : null;
        }

        return null;
    }

    private static function parseInsuranceStatementDescriptionLine(string $description): ?array
    {
        $description = trim($description);
        if ($description === '') {
            return null;
        }

        $core = preg_replace('/\s*\[[^\]]+\]\s*$/', '', $description);
        $core = trim((string) $core);
        if ($core === '') {
            return null;
        }

        if (preg_match('/^(.+?)\s*\(x([\d.]+)\)\s*$/u', $core, $m)) {
            return [
                'name' => trim($m[1]),
                'quantity' => $m[2],
                'is_service_fee' => false,
            ];
        }

        if (preg_match('/service\s+fee/i', $core)) {
            return ['is_service_fee' => true];
        }

        return null;
    }

    private static function findInvoiceItemRowByDisplayName(Invoice $invoice, string $displayName): ?array
    {
        $target = mb_strtolower(trim($displayName));
        if ($target === '') {
            return null;
        }

        foreach ($invoice->items ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = mb_strtolower(trim((string) ($row['name'] ?? $row['displayName'] ?? '')));
            if ($name !== '' && $name === $target) {
                return self::invoiceItemRowWithResolvedCode($row);
            }
        }

        return null;
    }

    private static function invoiceItemRowWithResolvedCode(array $row): array
    {
        $code = trim((string) ($row['code'] ?? ''));
        if ($code === '' && ! empty($row['id'] ?? $row['item_id'] ?? null)) {
            $id = $row['id'] ?? $row['item_id'];
            $item = Item::query()->find($id);
            if ($item && ! empty($item->code)) {
                $row['code'] = $item->code;
            }
        }

        return $row;
    }

    public static function invoiceRowExcludedByInsuranceBreakdown(array $invoiceItem, array $excludedRows): bool
    {
        foreach ($excludedRows as $ex) {
            if (! is_array($ex)) {
                continue;
            }

            $exItemId = $ex['item_id'] ?? $ex['kashtre_item_id'] ?? null;
            $invItemId = $invoiceItem['id'] ?? $invoiceItem['item_id'] ?? null;
            if ($exItemId !== null && $invItemId !== null && (string) $exItemId === (string) $invItemId) {
                return true;
            }

            $itemName = mb_strtolower(trim((string) ($invoiceItem['name'] ?? $invoiceItem['displayName'] ?? '')));
            $itemCode = trim((string) ($invoiceItem['code'] ?? ''));
            $exName = mb_strtolower(trim((string) ($ex['name'] ?? '')));
            $exCode = trim((string) ($ex['code'] ?? ''));

            if ($exCode !== '' && $itemCode !== '' && strcasecmp($exCode, $itemCode) === 0) {
                return true;
            }

            if ($exName !== '' && $itemName !== '' && $exName === $itemName) {
                return true;
            }
        }

        return false;
    }

    private static function vendorCascadeRowEligible(array $v): bool
    {
        $status = strtolower((string) ($v['authorization_status'] ?? ''));

        return ! in_array($status, ['failed', 'skipped'], true);
    }

    private static function firstEligibleVendorName(array $sorted): ?string
    {
        foreach ($sorted as $v) {
            if (! self::vendorCascadeRowEligible($v)) {
                continue;
            }
            $vendorName = trim((string) ($v['vendor_name'] ?? $v['insurance_company_name'] ?? ''));
            if ($vendorName !== '') {
                return $vendorName;
            }
        }

        return null;
    }

    private static function pickHighestInsuranceVendorName(array $sorted): ?string
    {
        $best = null;
        $bestAmt = -1.0;
        foreach ($sorted as $v) {
            if (! self::vendorCascadeRowEligible($v)) {
                continue;
            }
            $amt = (float) ($v['insurance_total'] ?? $v['insurance_portion'] ?? 0);
            $vendorName = trim((string) ($v['vendor_name'] ?? $v['insurance_company_name'] ?? ''));
            if ($vendorName === '') {
                continue;
            }
            if ($amt > $bestAmt) {
                $bestAmt = $amt;
                $best = $vendorName;
            }
        }

        return $best ?? self::firstEligibleVendorName($sorted);
    }

    private static function resolveInsuranceVendorNameFromSnapshotForLine(array $snap, Invoice $invoice, ?array $parsedLine): ?string
    {
        $multiRows = null;
        if (! empty($snap['multi_vendor']) && ! empty($snap['vendors']) && is_array($snap['vendors'])) {
            $multiRows = $snap['vendors'];
        } elseif (! empty($snap['vendor_results']) && is_array($snap['vendor_results'])) {
            $multiRows = $snap['vendor_results'];
        }

        if ($multiRows !== null && $multiRows !== []) {
            $sorted = $multiRows;
            usort($sorted, fn ($a, $b) => ((float) ($a['priority'] ?? 0)) <=> ((float) ($b['priority'] ?? 0)));

            $isServiceFee = ($parsedLine['is_service_fee'] ?? false) === true;
            if ($isServiceFee) {
                return self::pickHighestInsuranceVendorName($sorted);
            }

            $invoiceItem = null;
            if ($parsedLine && empty($parsedLine['is_service_fee'])) {
                $invoiceItem = self::findInvoiceItemRowByDisplayName($invoice, (string) ($parsedLine['name'] ?? ''));
            }

            foreach ($sorted as $v) {
                if (! self::vendorCascadeRowEligible($v)) {
                    continue;
                }
                $vendorName = trim((string) ($v['vendor_name'] ?? $v['insurance_company_name'] ?? ''));
                if ($vendorName === '') {
                    continue;
                }
                $breakdown = is_array($v['breakdown'] ?? null) ? $v['breakdown'] : [];
                $excludedItems = is_array($breakdown['excluded_items'] ?? null) ? $breakdown['excluded_items'] : [];

                if ($invoiceItem !== null && self::invoiceRowExcludedByInsuranceBreakdown($invoiceItem, $excludedItems)) {
                    continue;
                }

                return $vendorName;
            }

            return null;
        }

        $single = trim((string) ($snap['vendor_name'] ?? $snap['insurance_company_name'] ?? ''));

        return $single !== '' ? $single : null;
    }

    private static function preferInsuranceCompanyRegisteredName(?string $label, ?Invoice $invoice): ?string
    {
        if ($label === null || trim($label) === '') {
            return null;
        }

        $trimmed = trim($label);
        if (! $invoice?->business_id) {
            return $trimmed;
        }

        $payer = ThirdPartyPayer::query()
            ->where('business_id', $invoice->business_id)
            ->where('type', 'insurance_company')
            ->whereNull('client_id')
            ->where(function ($q) use ($trimmed) {
                $q->where('name', $trimmed)
                    ->orWhereHas('insuranceCompany', fn ($ic) => $ic->where('name', $trimmed));
            })
            ->with('insuranceCompany')
            ->first();

        if ($payer?->insuranceCompany?->name) {
            return trim((string) $payer->insuranceCompany->name);
        }

        return $trimmed;
    }

    private static function payerNameFromInsuranceSnapshot(array $snap): ?string
    {
        $collectNames = static function ($rows): array {
            $names = [];
            foreach ($rows as $v) {
                if (! is_array($v)) {
                    continue;
                }
                $n = trim((string) ($v['vendor_name'] ?? $v['insurance_company_name'] ?? ''));
                if ($n !== '') {
                    $names[] = $n;
                }
            }

            return array_values(array_unique($names));
        };

        if (! empty($snap['multi_vendor']) && ! empty($snap['vendors']) && is_array($snap['vendors'])) {
            $names = $collectNames($snap['vendors']);
            if ($names !== []) {
                return implode(', ', $names);
            }
        }

        if (! empty($snap['vendor_results']) && is_array($snap['vendor_results'])) {
            $names = $collectNames($snap['vendor_results']);
            if ($names !== []) {
                return implode(', ', $names);
            }
        }

        $single = trim((string) ($snap['vendor_name'] ?? $snap['insurance_company_name'] ?? ''));

        return $single !== '' ? $single : null;
    }

    // Helper methods
    public function isCredit()
    {
        return $this->change_amount > 0;
    }

    public function isDebit()
    {
        return $this->change_amount < 0;
    }

    public function getFormattedChangeAmount()
    {
        $amount = abs($this->change_amount);

        // Package entries should not have + or - prefix
        if ($this->transaction_type === 'package') {
            return 'UGX '.number_format($amount, 2);
        }

        // Debits should not have negative sign (they're already shown in red)
        if ($this->transaction_type === 'debit') {
            return 'UGX '.number_format($amount, 2);
        }

        // Credits get + prefix
        $prefix = $this->isCredit() ? '+' : '';

        return $prefix.'UGX '.number_format($amount, 2);
    }

    public function getFormattedBalance()
    {
        return 'UGX '.number_format($this->new_balance, 2);
    }

    // Static methods for creating balance statement records
    public static function recordBalanceChange($data)
    {
        return self::create($data);
    }

    public static function recordPayment($client, $invoice, $amount, $paymentMethod = null, $paymentReference = null)
    {
        $previousBalance = $client->balance ?? 0;
        $newBalance = $previousBalance - $amount; // Debit from balance

        return self::create([
            'client_id' => $client->id,
            'business_id' => $client->business_id,
            'branch_id' => $client->branch_id,
            'invoice_id' => $invoice ? $invoice->id : null,
            'user_id' => auth()->id(),
            'previous_balance' => $previousBalance,
            'change_amount' => -$amount, // Negative for debit
            'new_balance' => $newBalance,
            'transaction_type' => 'payment',
            'description' => $invoice ? "Invoice #{$invoice->invoice_number}" : 'Balance adjustment',
            'reference_number' => $invoice ? $invoice->invoice_number : null,
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'notes' => 'Balance used for invoice payment',
        ]);
    }

    public static function recordCredit($client, $amount, $description, $referenceNumber = null, $notes = null, $paymentMethod = null, $invoiceId = null, $paymentStatus = null)
    {
        // Calculate previous balance from existing balance history records
        $previousBalance = self::where('client_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->value('new_balance') ?? 0;

        $newBalance = $previousBalance + $amount; // Credit to balance

        // Default payment_status to 'paid' if not provided
        if ($paymentStatus === null) {
            $paymentStatus = 'paid';
        }

        // Validate payment_method - only allow valid enum values
        $validPaymentMethods = ['account_balance', 'mobile_money', 'bank_transfer', 'v_card', 'p_card', 'insurance'];
        if ($paymentMethod !== null && ! in_array($paymentMethod, $validPaymentMethods)) {
            // If invalid payment method provided, default to 'mobile_money' for payments
            \Log::warning("Invalid payment_method '{$paymentMethod}' provided to recordCredit, defaulting to 'mobile_money'", [
                'client_id' => $client->id,
                'invalid_method' => $paymentMethod,
                'description' => $description,
            ]);
            $paymentMethod = 'mobile_money';
        }

        return self::create([
            'client_id' => $client->id,
            'business_id' => $client->business_id,
            'branch_id' => $client->branch_id,
            'invoice_id' => $invoiceId,
            'user_id' => auth()->id() ?? 1, // Default to user ID 1 if no auth
            'previous_balance' => $previousBalance,
            'change_amount' => $amount, // Positive for credit
            'new_balance' => $newBalance,
            'transaction_type' => 'credit',
            'description' => $description,
            'reference_number' => $referenceNumber,
            'notes' => $notes,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
        ]);
    }

    public static function recordDebit($client, $amount, $description, $referenceNumber = null, $notes = null, $paymentMethod = null, $invoiceId = null)
    {
        // Check if a debit entry already exists for this invoice with the same description to prevent duplicates
        // This allows multiple entries per invoice (one per item) as long as descriptions differ
        if ($invoiceId) {
            $existingDebit = self::where('client_id', $client->id)
                ->where('invoice_id', $invoiceId)
                ->where('transaction_type', 'debit')
                ->where('description', $description)
                ->first();

            if ($existingDebit) {
                \Log::info('Debit entry already exists for invoice with same description', [
                    'invoice_id' => $invoiceId,
                    'client_id' => $client->id,
                    'description' => $description,
                    'existing_debit_id' => $existingDebit->id,
                ]);

                return $existingDebit;
            }
        }

        // Calculate previous balance from existing balance history records
        $previousBalance = self::where('client_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->value('new_balance') ?? ($client->balance ?? 0);

        $newBalance = $previousBalance - $amount; // Debit from balance

        // Validate payment_method - only allow valid enum values
        $validPaymentMethods = ['account_balance', 'mobile_money', 'bank_transfer', 'v_card', 'p_card', 'insurance'];
        if ($paymentMethod !== null && ! in_array($paymentMethod, $validPaymentMethods)) {
            // If invalid payment method provided, default to 'mobile_money' for payments
            \Log::warning("Invalid payment_method '{$paymentMethod}' provided to recordDebit, defaulting to 'mobile_money'", [
                'client_id' => $client->id,
                'invalid_method' => $paymentMethod,
                'description' => $description,
            ]);
            $paymentMethod = 'mobile_money';
        }

        return self::create([
            'client_id' => $client->id,
            'business_id' => $client->business_id,
            'branch_id' => $client->branch_id,
            'invoice_id' => $invoiceId,
            'user_id' => auth()->id() ?? 1, // Default to user ID 1 if no auth
            'previous_balance' => $previousBalance,
            'change_amount' => -$amount, // Negative for debit
            'new_balance' => $newBalance,
            'transaction_type' => 'debit',
            'description' => $description,
            'reference_number' => $referenceNumber,
            'notes' => $notes,
            'payment_method' => $paymentMethod,
        ]);
    }

    public static function recordAdjustment($client, $amount, $description, $referenceNumber = null, $notes = null)
    {
        $previousBalance = $client->balance ?? 0;
        $newBalance = $previousBalance + $amount; // Can be positive or negative

        return self::create([
            'client_id' => $client->id,
            'business_id' => $client->business_id,
            'branch_id' => $client->branch_id,
            'user_id' => auth()->id(),
            'previous_balance' => $previousBalance,
            'change_amount' => $amount,
            'new_balance' => $newBalance,
            'transaction_type' => 'adjustment',
            'description' => $description,
            'reference_number' => $referenceNumber,
            'notes' => $notes,
        ]);
    }

    public static function recordPackageUsage($client, $amount, $description, $referenceNumber = null, $notes = null, $paymentMethod = null)
    {
        \Log::info('=== CREATING PACKAGE USAGE BALANCE HISTORY RECORD ===', [
            'client_id' => $client->id,
            'client_name' => $client->name ?? 'Unknown',
            'business_id' => $client->business_id,
            'branch_id' => $client->branch_id,
            'amount' => $amount,
            'description' => $description,
            'reference_number' => $referenceNumber,
            'notes' => $notes,
            'payment_method' => $paymentMethod,
            'timestamp' => now()->toDateTimeString(),
        ]);

        // Calculate previous balance from existing balance history records
        $previousBalance = self::where('client_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->value('new_balance') ?? 0;

        \Log::info('Previous balance calculated for package usage', [
            'client_id' => $client->id,
            'previous_balance' => $previousBalance,
        ]);

        // Package usage doesn't affect balance - it's just a record
        // The balance remains the same since package was already paid for
        $newBalance = $previousBalance;

        $balanceHistoryData = [
            'client_id' => $client->id,
            'business_id' => $client->business_id,
            'branch_id' => $client->branch_id,
            'user_id' => auth()->id() ?? 1, // Default to user ID 1 if no auth
            'previous_balance' => $previousBalance,
            'change_amount' => $amount, // Show actual item amount for display purposes
            'new_balance' => $newBalance, // Balance remains unchanged (package already paid for)
            'transaction_type' => 'package',
            'description' => $description,
            'reference_number' => $referenceNumber,
            'notes' => $notes,
            'payment_method' => $paymentMethod,
        ];

        \Log::info('Creating BalanceHistory record for package usage', [
            'balance_history_data' => $balanceHistoryData,
        ]);

        $balanceHistory = self::create($balanceHistoryData);

        \Log::info('Package usage BalanceHistory record created successfully', [
            'balance_history_id' => $balanceHistory->id,
            'client_id' => $client->id,
            'transaction_type' => 'package',
            'display_amount' => $amount,
            'balance_change' => 0,
            'description' => $description,
            'note' => 'Package usage shows item amount for display but does not affect balance',
        ]);

        return $balanceHistory;
    }
}
