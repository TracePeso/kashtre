<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GoodsReceivedNote extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'uuid',
        'grn_number',
        'business_id',
        'supplier_id',
        'store_id',
        'date_of_order',
        'date_of_delivery',
        'lead_time_days',
        'delivery_note_path',
        'delivery_note_original_name',
        'status',
        'current_approval_order',
        'entry_by_user_id',
        'submitted_by_user_id',
        'submitted_at',
        'approved_at',
        'rejection_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_of_order' => 'date',
        'date_of_delivery' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'lead_time_days' => 'integer',
        'current_approval_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $grn) {
            if (empty($grn->uuid)) {
                $grn->uuid = (string) Str::uuid();
            }
        });
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function entryBy()
    {
        return $this->belongsTo(User::class, 'entry_by_user_id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function lines()
    {
        return $this->hasMany(GoodsReceivedNoteLine::class);
    }

    public function approvals()
    {
        return $this->hasMany(GoodsReceivedNoteApproval::class)->orderBy('approval_order');
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

    public static function generateNumber(int $businessId): string
    {
        $prefix = 'GRN-'.now()->format('Ym').'-';
        $last = self::query()
            ->where('business_id', $businessId)
            ->where('grn_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('grn_number');

        $sequence = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
