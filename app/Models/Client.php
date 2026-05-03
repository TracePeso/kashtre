<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Client extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'business_id',
        'branch_id',
        'client_id',
        'visit_id',
        'visit_expires_at',
        'visit_authorization_sessions',
        'name',
        'nin',
        'tin_number',
        'surname',
        'first_name',
        'other_names',
        'sex',
        'date_of_birth',
        'marital_status',
        'occupation',
        'phone_number',
        'village',
        'county',
        'email',
        'services_category',
        'payment_methods',
        'payment_phone_number',
        // Next of Kin details
        'nok_surname',
        'nok_first_name',
        'nok_other_names',
        'nok_sex',
        'nok_marital_status',
        'nok_occupation',
        'nok_phone_number',
        'nok_village',
        'nok_county',
        'balance',
        'status',
        'client_type',
        'max_credit',
        'is_credit_eligible',
        'is_long_stay',
        'excluded_items',
        'insurance_company_id',
        'policy_number',
        // Payment responsibility fields
        'has_deductible',
        'deductible_amount',
        'copay_amount',
        'coinsurance_percentage',
        'copay_max_limit',
        'copay_contributes_to_deductible',
        'coinsurance_contributes_to_deductible',
        'registered_via_open_enrollment',
        'nationality',
    ];

    protected $casts = [
        'payment_methods' => 'array',
        'date_of_birth' => 'date',
        'visit_expires_at' => 'datetime',
        'visit_authorization_sessions' => 'array',
        'max_credit' => 'decimal:2',
        'is_credit_eligible' => 'boolean',
        'is_long_stay' => 'boolean',
        'excluded_items' => 'array',
        'has_deductible' => 'boolean',
        'deductible_amount' => 'decimal:2',
        'copay_amount' => 'decimal:2',
        'coinsurance_percentage' => 'decimal:2',
        'copay_max_limit' => 'decimal:2',
        'copay_contributes_to_deductible' => 'boolean',
        'coinsurance_contributes_to_deductible' => 'boolean',
        'registered_via_open_enrollment' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($user) {
            $user->uuid = (string) Str::uuid();
        });
    }
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function insuranceCompany()
    {
        return $this->belongsTo(InsuranceCompany::class);
    }

    public function balanceHistories()
    {
        return $this->hasMany(BalanceHistory::class);
    }

    public function serviceDeliveryQueues()
    {
        return $this->hasMany(ServiceDeliveryQueue::class);
    }

    public function creditLimitChangeRequests()
    {
        return $this->hasMany(CreditLimitChangeRequest::class, 'entity_id')
            ->where('entity_type', 'client');
    }

    /**
     * Get all vendors associated with this client
     */
    public function vendors()
    {
        return $this->hasMany(ClientVendor::class);
    }

    /**
     * Get the related third-party payers through vendors
     */
    public function thirdPartyPayers()
    {
        return $this->belongsToMany(
            ThirdPartyPayer::class,
            'client_vendors',
            'client_id',
            'third_party_payer_id'
        )
        ->withPivot([
            'policy_number',
            'policy_verified',
            'is_open_enrollment',
            'deductible_amount',
            'copay_amount',
            'coinsurance_percentage',
            'copay_max_limit',
            'copay_contributes_to_deductible',
            'coinsurance_contributes_to_deductible',
            'excluded_items',
            'status',
            'notes',
        ])
        ->withTimestamps();
    }

    /**
     * Get the full name of the client
     */
    public function getFullNameAttribute()
    {
        return trim($this->surname . ' ' . $this->first_name . ' ' . ($this->other_names ?? ''));
    }

    /**
     * Get the full name of the next of kin
     */
    public function getNokFullNameAttribute()
    {
        return trim($this->nok_surname . ' ' . $this->nok_first_name . ' ' . ($this->nok_other_names ?? ''));
    }

    /**
     * Get the client's total balance (money in suspense accounts)
     */
    public function getTotalBalanceAttribute()
    {
        return $this->getAvailableBalanceAttribute() + $this->getSuspenseBalanceAttribute();
    }

    /**
     * Get the client's available balance (money available for transactions)
     */
    public function getAvailableBalanceAttribute()
    {
        return MoneyAccount::where('client_id', $this->id)
            ->where('type', 'client_account')
            ->sum('balance');
    }

    /**
     * Get the client's suspense balance (money in suspense accounts that is NOT MOVED yet)
     * This should return the actual balance from ALL suspense accounts that haven't been moved to final accounts
     */
    public function getSuspenseBalanceAttribute()
    {
        // Get the sum of ALL suspense account balances for this client
        // All suspense accounts are now client-specific
        $totalSuspenseBalance = $this->suspenseAccounts()->sum('balance');
        
        return $totalSuspenseBalance;
    }

    /**
     * Calculate balance from balance history (credits - debits)
     * This is the actual account balance based on all transactions
     */
    public function getCalculatedBalanceAttribute()
    {
        $credits = $this->balanceHistories()
            ->where('transaction_type', 'credit')
            ->sum('change_amount');
        
        $debits = abs($this->balanceHistories()
            ->where('transaction_type', 'debit')
            ->sum('change_amount'));
        
        return $credits - $debits;
    }

    /**
     * Get the client's money account
     */
    public function moneyAccount()
    {
        return $this->hasOne(MoneyAccount::class)->where('type', 'client_account');
    }

    /**
     * Get the client's suspense accounts
     */
    public function suspenseAccounts()
    {
        return $this->hasMany(MoneyAccount::class)->whereIn('type', [
            'general_suspense_account', 
            'package_suspense_account',
            'kashtre_suspense_account'
        ]);
    }


    /**
     * Generate a business-scoped client ID.
     *
     * Format: <B1><B2><CODE7>
     *  - <B1><B2>: First two letters of the business name (upper-cased; fallbacks ensure two characters).
     *  - <CODE7>:  Deterministic 7-character alphanumeric string derived from surname, first name, DOB.
     *
     * Ensures uniqueness within the business by retrying with a sequence suffix if a collision occurs.
     */
    public static function generateClientId($business, string $surname = '', string $firstName = '', ?string $dateOfBirth = null): string
    {
        if (! $business) {
            throw new \InvalidArgumentException('Business context is required when generating a client ID.');
        }

        $businessPrefix = self::resolveBusinessPrefixForClientId((string) $business->name);
        $normalizedDob = self::normalizeDateForClientId($dateOfBirth);

        $attempt = 0;
        $maxAttempts = 50;

        do {
            $code = self::buildSevenCharacterCode($surname, $firstName, $normalizedDob, $attempt);
            $candidate = $businessPrefix . $code;

            $exists = self::withTrashed()
                ->where('business_id', $business->id)
                ->where('client_id', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }

            $attempt++;
        } while ($attempt < $maxAttempts);

        throw new \RuntimeException('Unable to generate a unique client ID after multiple attempts.');
    }

    protected static function resolveBusinessPrefixForClientId(string $businessName): string
    {
        $businessName = trim($businessName);
        if ($businessName === '') {
            return 'XX';
        }

        // Split business name into words
        $words = preg_split('/\s+/', $businessName);
        
        // Take first letter from first word and first letter from second word
        $firstLetter = '';
        $secondLetter = '';
        
        if (isset($words[0]) && !empty($words[0])) {
            // Extract first alphabetic letter from first word
            if (preg_match('/[A-Za-z]/', $words[0], $matches)) {
                $firstLetter = strtoupper($matches[0]);
            }
        }
        
        if (isset($words[1]) && !empty($words[1])) {
            // Extract first alphabetic letter from second word
            if (preg_match('/[A-Za-z]/', $words[1], $matches)) {
                $secondLetter = strtoupper($matches[0]);
            }
        }
        
        // If we don't have two letters, use fallbacks
        if (empty($firstLetter)) {
            $firstLetter = 'X';
        }
        if (empty($secondLetter)) {
            $secondLetter = 'X';
        }
        
        return $firstLetter . $secondLetter;
    }

    protected static function normalizeDateForClientId(?string $dateOfBirth): string
    {
        if (empty($dateOfBirth)) {
            return '00000000';
        }

        try {
            return Carbon::parse($dateOfBirth)->format('Ymd');
        } catch (\Throwable $e) {
            return '00000000';
        }
    }

    protected static function buildSevenCharacterCode(string $surname, string $firstName, string $normalizedDob, int $attempt = 0): string
    {
        $source = strtoupper(trim($surname)) . '|' . strtoupper(trim($firstName)) . '|' . $normalizedDob . '|' . $attempt;
        $hash = strtoupper(base_convert(md5($source), 16, 36));
        $clean = preg_replace('/[^A-Z0-9]/', '', $hash);

        // Separate letters and numbers from the hash
        $letters = preg_replace('/[^A-Z]/', '', $clean);
        $numbers = preg_replace('/[^0-9]/', '', $clean);

        // Character sets for fallback generation
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits = '0123456789';

        // Use a source for generating additional characters (ensure it's not empty)
        $sourceString = $clean ?: $hash ?: $source;
        $sourceLength = strlen($sourceString);
        
        if ($sourceLength === 0) {
            $sourceString = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $sourceLength = strlen($sourceString);
        }

        // Generate additional letters if needed (deterministically from hash)
        while (strlen($letters) < 5) {
            $pos = strlen($letters) % $sourceLength;
            $char = $sourceString[$pos];
            // Convert to letter if not already a letter
            if (preg_match('/[A-Z]/', $char)) {
                $letters .= $char;
            } else {
                $index = ord($char) % strlen($alphabet);
                $letters .= $alphabet[$index];
            }
        }

        // Generate additional numbers if needed (deterministically from hash)
        while (strlen($numbers) < 2) {
            $pos = strlen($numbers) % $sourceLength;
            $char = $sourceString[$pos];
            // Convert to number if not already a number
            if (preg_match('/[0-9]/', $char)) {
                $numbers .= $char;
            } else {
                $index = ord($char) % strlen($digits);
                $numbers .= $digits[$index];
            }
        }

        // Build pattern: 3 letters, 2 numbers, 2 letters
        $code = substr($letters, 0, 3) . substr($numbers, 0, 2) . substr($letters, 3, 2);

        return $code;
    }

    /**
     * Generate a visit ID based on the business and branch.
     *
     * Format: <B1><B2><NN><R>[SUFFIX]
     *  - <B1><B2>: First letters from the first two words of the business name
     *              (fallbacks ensure two alphabetic characters).
     *  - <NN>:     Two-digit sequence unique within a branch (01-99).
     *  - <R>:      First letter of the branch name (fallback to X).
     *  - [SUFFIX]: Optional suffix based on is_credit_eligible and is_long_stay flags
     *              - '/C' if is_credit_eligible is true
     *              - '/M' if is_long_stay is true
     *              - '/C/M' if both are true
     *
     * Example: ET63K (regular)
     * Example: ET63K/C (credit eligible)
     * Example: ET63K/M (long stay)
     * Example: ET63K/C/M (both credit eligible and long stay)
     */
    public static function generateVisitId($business, $branch, $isCreditEligible = false, $isLongStay = false)
    {
        if (! $business || ! $branch) {
            throw new \InvalidArgumentException('Both business and branch are required to generate a visitor ID.');
        }

        $businessPrefix = self::resolveBusinessPrefix($business?->name);
        $branchLetter = self::resolveBranchLetter($branch?->name);
        
        // Determine suffix based on flags
        $suffix = '';
        
        if ($isLongStay && $isCreditEligible) {
            // Both flags: use /C/M
            $suffix = '/C/M';
        } elseif ($isLongStay) {
            // Long stay only: add /M
            $suffix = '/M';
        } elseif ($isCreditEligible) {
            // Credit eligible only: add /C
            $suffix = '/C';
        }

        // Get all existing visit IDs for this branch (including expired ones if they have suffixes)
        // Base IDs with suffixes should remain reserved even if expired
        $existingVisitIds = self::withTrashed()
            ->where('branch_id', $branch->id)
            ->whereNotNull('visit_id')
            ->where(function ($query) {
                // Include non-expired IDs
                $query->whereNull('visit_expires_at')
                    ->orWhere('visit_expires_at', '>', now())
                    // Also include expired IDs that have suffixes (they reserve the base ID)
                    ->orWhere(function ($q) {
                        $q->whereNotNull('visit_expires_at')
                          ->where('visit_expires_at', '<=', now())
                          ->where(function ($subQ) {
                              $subQ->where('visit_id', 'like', '%/C')
                                   ->orWhere('visit_id', 'like', '%/M')
                                   ->orWhere('visit_id', 'like', '%/C/M');
                          });
                    });
            })
            ->pluck('visit_id');

        // Extract base visit IDs (remove any suffixes)
        $usedBaseIds = $existingVisitIds->map(function ($visitId) {
            // Remove suffixes: /C, /M, /C/M
            return preg_replace('/\/(C\/M|C|M)$/', '', $visitId);
        })->unique();

        // Find an available base ID
        for ($sequence = 1; $sequence <= 99; $sequence++) {
            $sequenceSegment = str_pad($sequence, 2, '0', STR_PAD_LEFT);
            $candidateBaseId = $businessPrefix . $sequenceSegment . $branchLetter;
            
            // Check if this base ID is already in use (with or without suffix)
            if (! $usedBaseIds->contains($candidateBaseId)) {
                return $candidateBaseId . $suffix;
            }
        }

        throw new \RuntimeException('No available visitor IDs for this branch. Please review visitor ID allocations.');
    }

    /**
     * Resolve the two-letter business prefix for visit IDs.
     */
    protected static function resolveBusinessPrefix(?string $businessName): string
    {
        $businessName = trim((string) $businessName);

        if ($businessName === '') {
            return 'XX';
        }

        $words = array_values(array_filter(preg_split('/\s+/', $businessName)));

        if (count($words) >= 2) {
            $first = strtoupper(substr($words[0], 0, 1));
            $second = strtoupper(substr($words[1], 0, 1));
        } else {
            $firstWord = $words[0];
            $first = strtoupper(substr($firstWord, 0, 1));
            $second = strtoupper(substr($firstWord, 1, 1) ?: $first);
        }

        $first = $first ?: 'X';
        $second = $second ?: 'X';

        return $first . $second;
    }

    /**
     * Resolve the branch letter suffix for visit IDs.
     */
    protected static function resolveBranchLetter(?string $branchName): string
    {
        $branchName = trim((string) $branchName);

        if ($branchName === '') {
            return 'X';
        }

        $letter = strtoupper(substr($branchName, 0, 1));

        return $letter ?: 'X';
    }

    /**
     * Ensure the client has a valid visit ID for the current day.
     * Note: Long-stay clients (is_long_stay = true) never expire automatically.
     */
    public function ensureActiveVisitId(bool $force = false): void
    {
        // Long-stay clients don't expire automatically - only on manual discharge
        if ($this->is_long_stay && !$force) {
            return;
        }
        
        $needsRefresh = $force
            || empty($this->visit_id)
            || empty($this->visit_expires_at)
            || Carbon::parse($this->visit_expires_at)->isPast();

        if (! $needsRefresh) {
            return;
        }

        $this->issueNewVisitId();
    }

    /**
     * Issue a new visit ID and extend validity to the next midnight.
     */
    public function issueNewVisitId(): void
    {
        $business = $this->business ?: Business::find($this->business_id);
        $branch = $this->branch ?: Branch::find($this->branch_id);

        if (! $business || ! $branch) {
            return;
        }

        $this->visit_id = self::generateVisitId($business, $branch, $this->is_credit_eligible ?? false, $this->is_long_stay ?? false);
        $this->visit_expires_at = Carbon::tomorrow()->startOfDay();
        $this->save();
    }

    /**
     * Get the age of the client
     */
    public function getAgeAttribute()
    {
        if ($this->date_of_birth) {
            return now()->diffInYears($this->date_of_birth);
        }
        return null;
    }
}
