<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReturnNote extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';

    public const REASON_DAMAGED = 'DG';
    public const REASON_DEFECTIVE = 'DO';
    public const REASON_INCORRECT_GOODS = 'IG';
    public const REASON_INCORRECT_PRODUCT = 'IP';

    protected $fillable = [
        'business_id',
        'store_id',
        'supplier_id',
        'reference',
        'status',
        'return_date',
        'reason_code',
        'notes',
        'created_by_user_id',
        'submitted_at',
    ];

    protected $casts = [
        'return_date' => 'date',
        'submitted_at' => 'datetime',
    ];

    public static function reasonOptions(): array
    {
        return [
            self::REASON_DAMAGED => 'DG — Damaged goods',
            self::REASON_DEFECTIVE => 'DO — Defective / out of spec',
            self::REASON_INCORRECT_GOODS => 'IG — Incorrect goods delivered',
            self::REASON_INCORRECT_PRODUCT => 'IP — Incorrect product invoiced',
        ];
    }

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

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReturnNoteLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
