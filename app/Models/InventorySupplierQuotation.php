<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventorySupplierQuotation extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'business_id',
        'inventory_order_id',
        'supplier_id',
        'reference_number',
        'status',
        'total_amount',
        'notes',
        'received_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'received_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function inventoryOrder(): BelongsTo
    {
        return $this->belongsTo(InventoryOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventorySupplierQuotationLine::class);
    }

    public function purchaseOrder(): HasOne
    {
        return $this->hasOne(InventoryPurchaseOrder::class, 'inventory_supplier_quotation_id');
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function canAccept(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RECEIVED], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_RECEIVED => 'Received',
            self::STATUS_ACCEPTED => 'Accepted',
            self::STATUS_REJECTED => 'Rejected',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }
}
