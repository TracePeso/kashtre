<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryModuleConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'is_active',
        'description',
        'fixed_daily_average_suom',
        'safety_stock_days',
        'buffer_stock_days',
        'notification_to_order_days',
        'period_of_order_days',
        'financial_year_start_month',
        'finance_notification_emails',
        'lpo_email_copy_to_approvers',
        'notify_approvers_on_order_submitted',
        'notify_finance_on_order_submitted',
        'notify_next_approver_on_approval',
        'notify_on_order_fully_approved',
        'notify_suppliers_on_rfq_approved',
        'notify_on_lpo_issued',
        'evaluation_committee_required',
        'enable_floor_stock_management',
        'enable_crash_cart_management',
        'enable_batch_lot_tracking',
        'enable_serial_number_tracking',
        'enable_internal_ordering',
        'enable_automated_stock_counts',
        'enable_multi_store_network',
        'label_dictionary',
        'visit_reactivation_lookback_days',
        'admin_usage_purposes',
        'stat_priority_keywords',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'fixed_daily_average_suom' => 'decimal:4',
        'safety_stock_days' => 'decimal:2',
        'buffer_stock_days' => 'decimal:2',
        'notification_to_order_days' => 'decimal:2',
        'period_of_order_days' => 'decimal:2',
        'financial_year_start_month' => 'integer',
        'lpo_email_copy_to_approvers' => 'boolean',
        'notify_approvers_on_order_submitted' => 'boolean',
        'notify_finance_on_order_submitted' => 'boolean',
        'notify_next_approver_on_approval' => 'boolean',
        'notify_on_order_fully_approved' => 'boolean',
        'notify_suppliers_on_rfq_approved' => 'boolean',
        'notify_on_lpo_issued' => 'boolean',
        'evaluation_committee_required' => 'boolean',
        'enable_floor_stock_management' => 'boolean',
        'enable_crash_cart_management' => 'boolean',
        'enable_batch_lot_tracking' => 'boolean',
        'enable_serial_number_tracking' => 'boolean',
        'enable_internal_ordering' => 'boolean',
        'enable_automated_stock_counts' => 'boolean',
        'enable_multi_store_network' => 'boolean',
        'label_dictionary' => 'array',
        'visit_reactivation_lookback_days' => 'integer',
        'admin_usage_purposes' => 'array',
        'stat_priority_keywords' => 'array',
    ];

    /**
     * Daily usage for stock calculations: item-level value, or fixed average when item usage is zero.
     */
    public function effectiveDailyUsageSuom(?float $itemDailyUsageSuom): float
    {
        $usage = (float) ($itemDailyUsageSuom ?? 0);

        if ($usage > 0) {
            return $usage;
        }

        return (float) ($this->fixed_daily_average_suom ?? 0);
    }

    public function safetyStockSuom(?float $itemDailyUsageSuom): float
    {
        return round(
            $this->effectiveDailyUsageSuom($itemDailyUsageSuom) * (float) ($this->safety_stock_days ?? 0),
            4
        );
    }

    public function bufferStockSuom(?float $itemDailyUsageSuom): float
    {
        return round(
            $this->effectiveDailyUsageSuom($itemDailyUsageSuom) * (float) ($this->buffer_stock_days ?? 0),
            4
        );
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function approvers()
    {
        return $this->hasMany(InventoryModuleApprover::class)->orderBy('approval_order');
    }

    public function evaluationCommitteeMembers()
    {
        return $this->hasMany(InventoryEvaluationCommitteeMember::class)->orderBy('sort_order');
    }

    public function regularApprovers()
    {
        return $this->approvers()
            ->where('role', InventoryModuleApprover::ROLE_APPROVER);
    }

    public function technicalSupervisor()
    {
        return $this->hasOne(InventoryModuleApprover::class)
            ->where('role', InventoryModuleApprover::ROLE_TECHNICAL_SUPERVISOR);
    }

    /**
     * GRN chain: optional technical supervisor first, then Approver 1 / 2.
     */
    public function grnApprovers()
    {
        return $this->approvers()->orderBy('approval_order');
    }

    /** @return array<int, string> */
    public function financeNotificationEmailList(): array
    {
        $raw = (string) ($this->finance_notification_emails ?? '');

        if ($raw === '') {
            return [];
        }

        return collect(preg_split('/[\s,;]+/', $raw) ?: [])
            ->map(fn (string $email): string => trim($email))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }

    public function evaluationCommitteeRequired(): bool
    {
        return (bool) ($this->evaluation_committee_required ?? false);
    }

    public function floorStockEnabled(): bool
    {
        return (bool) ($this->enable_floor_stock_management ?? true);
    }

    public function crashCartEnabled(): bool
    {
        return (bool) ($this->enable_crash_cart_management ?? false);
    }

    public function batchLotTrackingEnabled(): bool
    {
        return (bool) ($this->enable_batch_lot_tracking ?? false);
    }

    public function serialNumberTrackingEnabled(): bool
    {
        return (bool) ($this->enable_serial_number_tracking ?? false);
    }

    public function internalOrderingEnabled(): bool
    {
        return (bool) ($this->enable_internal_ordering ?? true);
    }

    public function automatedStockCountsEnabled(): bool
    {
        return (bool) ($this->enable_automated_stock_counts ?? true);
    }

    public function multiStoreNetworkEnabled(): bool
    {
        return (bool) ($this->enable_multi_store_network ?? true);
    }

    /**
     * @return list<string>
     */
    public static function defaultAdminUsagePurposes(): array
    {
        return [
            'Cleaning',
            'Disinfection',
            'Spill Management',
            'Training',
            'Ward Operations',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function adminUsagePurposeOptions(): array
    {
        $list = $this->admin_usage_purposes;
        if (! is_array($list) || $list === []) {
            $list = self::defaultAdminUsagePurposes();
        }

        $list = array_values(array_filter(array_map(
            fn ($v) => trim((string) $v),
            $list
        ), fn ($v) => $v !== ''));

        return array_combine($list, $list) ?: [];
    }

    /**
     * @return list<string>
     */
    public function statPriorityKeywords(): array
    {
        $list = $this->stat_priority_keywords;
        if (! is_array($list) || $list === []) {
            return ['STAT', 'URGENT'];
        }

        $normalized = array_values(array_filter(array_map(
            fn ($v) => strtoupper(trim((string) $v)),
            $list
        ), fn ($v) => $v !== ''));

        return $normalized !== [] ? $normalized : ['STAT', 'URGENT'];
    }
}
