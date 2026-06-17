<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryStockCount extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** @deprecated Use STATUS_APPROVED */
    public const STATUS_FINALIZED = 'approved';

    protected $fillable = [
        'business_id',
        'store_id',
        'reference',
        'status',
        'current_approval_order',
        'counted_at',
        'notes',
        'created_by_user_id',
        'submitted_by_user_id',
        'submitted_at',
        'finalized_by_user_id',
        'finalized_at',
        'approved_at',
        'stock_applied_at',
        'rejection_reason',
    ];

    protected $casts = [
        'counted_at' => 'datetime',
        'submitted_at' => 'datetime',
        'finalized_at' => 'datetime',
        'approved_at' => 'datetime',
        'stock_applied_at' => 'datetime',
        'current_approval_order' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryStockCountLine::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(InventoryStockCountApproval::class)->orderBy('approval_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isFinalized(): bool
    {
        return $this->isApproved();
    }
}
