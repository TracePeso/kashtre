<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryPurchaseOrder extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';

    public const STATUS_FULFILLED = 'fulfilled';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'business_id',
        'inventory_order_id',
        'inventory_supplier_quotation_id',
        'supplier_id',
        'store_id',
        'po_number',
        'status',
        'total_amount',
        'notes',
        'issued_at',
        'issued_by_user_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'issued_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function inventoryOrder(): BelongsTo
    {
        return $this->belongsTo(InventoryOrder::class);
    }

    public function supplierQuotation(): BelongsTo
    {
        return $this->belongsTo(InventorySupplierQuotation::class, 'inventory_supplier_quotation_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryPurchaseOrderLine::class);
    }

    public function goodsReceivedNotes(): HasMany
    {
        return $this->hasMany(GoodsReceivedNote::class);
    }

    public function isIssued(): bool
    {
        return in_array($this->status, [
            self::STATUS_ISSUED,
            self::STATUS_PARTIALLY_RECEIVED,
            self::STATUS_FULFILLED,
        ], true);
    }

    public function canReceiveGoods(): bool
    {
        return in_array($this->status, [
            self::STATUS_ISSUED,
            self::STATUS_PARTIALLY_RECEIVED,
        ], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft LPO',
            self::STATUS_ISSUED => 'Issued',
            self::STATUS_PARTIALLY_RECEIVED => 'Partially received',
            self::STATUS_FULFILLED => 'Fulfilled',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function orderTotal(): float
    {
        return round((float) $this->lines()->sum('line_total'), 2);
    }

    public static function generateNumber(int $businessId): string
    {
        $entity = Business::query()->whereKey($businessId)->value('entity_code');
        $entity = $entity ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $entity)) : null;
        $prefix = ($entity ? $entity.'-' : '').'LPO-'.now()->format('Ymd').'-';

        $count = self::query()
            ->where('business_id', $businessId)
            ->where('po_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }
}
