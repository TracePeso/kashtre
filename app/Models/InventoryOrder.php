<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryOrder extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PO_ISSUED = 'po_issued';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    public const STATUS_FULFILLED = 'fulfilled';

    /** @deprecated Use STATUS_PENDING_APPROVAL or STATUS_APPROVED */
    public const STATUS_SUBMITTED = 'submitted';

    public const BUDGET_MODE_DAYS = 'days';
    public const BUDGET_MODE_AMOUNT = 'amount';

    protected $fillable = [
        'business_id',
        'store_id',
        'supplier_id',
        'order_number',
        'status',
        'importance_filter',
        'group_id',
        'subgroup_id',
        'item_ids',
        'budget_mode',
        'budget_value',
        'budget_cap_enforced',
        'initial_order_total',
        'moving_average_days',
        'period_of_order_days',
        'safety_stock_days',
        'buffer_stock_days',
        'notification_to_order_days',
        'peak_period_percent',
        'peak_consumption_increase_percent',
        'notes',
        'created_by_user_id',
        'submitted_by_user_id',
        'current_approval_order',
        'submitted_at',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'item_ids' => 'array',
        'budget_value' => 'decimal:2',
        'budget_cap_enforced' => 'boolean',
        'initial_order_total' => 'decimal:2',
        'moving_average_days' => 'integer',
        'period_of_order_days' => 'decimal:2',
        'safety_stock_days' => 'decimal:2',
        'buffer_stock_days' => 'decimal:2',
        'notification_to_order_days' => 'decimal:2',
        'peak_period_percent' => 'decimal:4',
        'peak_consumption_increase_percent' => 'decimal:4',
        'current_approval_order' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function subgroup(): BelongsTo
    {
        return $this->belongsTo(SubGroup::class, 'subgroup_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryOrderLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(InventoryOrderApproval::class)->orderBy('approval_order');
    }

    public function goodsReceivedNotes(): HasMany
    {
        return $this->hasMany(GoodsReceivedNote::class);
    }

    public function supplierQuotations(): HasMany
    {
        return $this->hasMany(InventorySupplierQuotation::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(InventoryPurchaseOrder::class);
    }

    public function documentNumber(): string
    {
        return (string) $this->order_number;
    }

    public function isRfq(): bool
    {
        return str_starts_with((string) $this->order_number, 'RFQ-')
            || ! str_starts_with((string) $this->order_number, 'ORD-');
    }

    public function rfqLabel(): string
    {
        return $this->isRfq() ? 'RFQ' : 'Order';
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function isApproved(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_PO_ISSUED,
            self::STATUS_PARTIALLY_RECEIVED,
            self::STATUS_FULFILLED,
        ], true);
    }

    public function isRfqApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPoIssued(): bool
    {
        return in_array($this->status, [
            self::STATUS_PO_ISSUED,
            self::STATUS_PARTIALLY_RECEIVED,
            self::STATUS_FULFILLED,
        ], true);
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isPartiallyReceived(): bool
    {
        return $this->status === self::STATUS_PARTIALLY_RECEIVED;
    }

    public function isFulfilled(): bool
    {
        return $this->status === self::STATUS_FULFILLED;
    }

    public function canReceiveGoods(): bool
    {
        return $this->purchaseOrders()
            ->whereIn('status', [
                InventoryPurchaseOrder::STATUS_ISSUED,
                InventoryPurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            ])
            ->exists();
    }

    public function canManageSupplierQuotations(): bool
    {
        return $this->isRfqApproved();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft RFQ',
            self::STATUS_PENDING_APPROVAL => 'Pending RFQ approval',
            self::STATUS_APPROVED => 'RFQ approved',
            self::STATUS_PO_ISSUED => 'LPO issued',
            self::STATUS_REJECTED => 'RFQ rejected',
            self::STATUS_PARTIALLY_RECEIVED => 'Partially received',
            self::STATUS_FULFILLED => 'Fulfilled',
            self::STATUS_SUBMITTED => 'Submitted',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function orderTotal(): float
    {
        return round((float) $this->lines()->sum('line_total'), 2);
    }

    public function effectiveAmountCap(): ?float
    {
        if ($this->budget_mode === self::BUDGET_MODE_AMOUNT && (float) ($this->budget_value ?? 0) > 0) {
            return (float) $this->budget_value;
        }

        if ((float) ($this->initial_order_total ?? 0) > 0) {
            return (float) $this->initial_order_total;
        }

        return null;
    }

    public function enforcesBudgetCap(): bool
    {
        return (bool) ($this->budget_cap_enforced ?? true)
            && $this->effectiveAmountCap() !== null;
    }

    public function orderingMethodLabel(): string
    {
        if ($this->budget_mode === self::BUDGET_MODE_DAYS) {
            return 'Budget · stock days';
        }

        if ($this->budget_mode === self::BUDGET_MODE_AMOUNT) {
            return 'Budget · amount';
        }

        return 'Period (days)';
    }

    public function orderingTypeLabel(): string
    {
        if ($this->budget_mode === self::BUDGET_MODE_DAYS) {
            return 'By budget · Stock days';
        }

        if ($this->budget_mode === self::BUDGET_MODE_AMOUNT) {
            return 'By budget · Amount (UGX)';
        }

        return 'By period (days)';
    }

    public function orderingTypeValueLabel(): string
    {
        if ($this->budget_mode === self::BUDGET_MODE_DAYS) {
            return number_format((float) ($this->budget_value ?? 0), 0).' stock-days';
        }

        if ($this->budget_mode === self::BUDGET_MODE_AMOUNT) {
            return 'UGX '.number_format((float) ($this->budget_value ?? 0), 0).' budget cap';
        }

        return number_format((float) ($this->period_of_order_days ?? 0), 0).' day period';
    }
}
