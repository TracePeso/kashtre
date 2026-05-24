<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'logo',
        'country_id',
        'currency_code',
        'exchange_rate_to_usd',
        'percentage_charge',
        'minimum_amount',
        'type',
        'account_number',
        'account_balance',
        'mode',
        'date',
        'visit_id_format',
        'max_third_party_credit_limit',
        'max_first_party_credit_limit',
        'admit_button_label',
        'discharge_button_label',
        'default_payment_terms_days',
        'admit_enable_credit',
        'admit_enable_long_stay',
        'discharge_remove_credit',
        'discharge_remove_long_stay',
        'credit_excluded_items',
        'third_party_excluded_items',
    ];

    protected $casts = [
        'account_balance' => 'decimal:2',
        'date' => 'date',
        'max_third_party_credit_limit' => 'decimal:2',
        'max_first_party_credit_limit' => 'decimal:2',
        'exchange_rate_to_usd' => 'decimal:6',
        'admit_enable_credit' => 'boolean',
        'admit_enable_long_stay' => 'boolean',
        'discharge_remove_credit' => 'boolean',
        'discharge_remove_long_stay' => 'boolean',
        'credit_excluded_items' => 'array',
        'third_party_excluded_items' => 'array',
    ];

    // a businness has many users
    public function users()
    {
        return $this->hasMany(User::class);
    }

    //a business has many transactions
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    //a business has many payment links
   

    protected static function booted()
    {
        static::creating(function ($user) {
            $user->uuid = (string) Str::uuid();
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    // a business has many withdrawal settings
    public function withdrawalSettings()
    {
        return $this->hasMany(BusinessWithdrawalSetting::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /**
     * USD value of one unit of this business’s operating currency (same semantics as countries.exchange_rate_to_usd).
     * Uses business override when set; otherwise the linked country’s rate; otherwise 1.
     */
    public function effectiveExchangeRateToUsd(): float
    {
        if ($this->exchange_rate_to_usd !== null && (float) $this->exchange_rate_to_usd > 0) {
            return round((float) $this->exchange_rate_to_usd, 6);
        }

        $countryRate = $this->country?->exchange_rate_to_usd;
        if ($countryRate !== null && (float) $countryRate > 0) {
            return round((float) $countryRate, 6);
        }

        return 1.0;
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function qualifications()
    {
        return $this->hasMany(Qualification::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function clientSpaces()
    {
        return $this->hasMany(ClientSpace::class);
    }

    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    public function titles()
    {
        return $this->hasMany(Title::class);
    }

    public function moneyAccounts()
    {
        return $this->hasMany(MoneyAccount::class);
    }

    public function businessMoneyAccount()
    {
        return $this->hasOne(MoneyAccount::class)->where('type', 'business_account');
    }

    public function kashtreMoneyAccount()
    {
        return $this->hasOne(MoneyAccount::class)->where('type', 'kashtre_account');
    }

    public function creditNoteWorkflow()
    {
        return $this->hasOne(CreditNoteWorkflow::class);
    }

    public function servicePointSupervisors()
    {
        return $this->hasMany(ServicePointSupervisor::class);
    }

    public function creditLimitApprovers()
    {
        return $this->hasMany(CreditLimitApprovalApprover::class);
    }

}
