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

    public const INSPECTION_PENDING = 'pending';

    public const INSPECTION_PASSED = 'passed';

    public const INSPECTION_FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'grn_number',
        'business_id',
        'supplier_id',
        'store_id',
        'inventory_order_id',
        'inventory_purchase_order_id',
        'date_of_order',
        'date_of_delivery',
        'lead_time_days',
        'delivery_note_path',
        'delivery_note_original_name',
        'technical_representative_name',
        'technical_representative_signature_path',
        'technical_representative_signature_original_name',
        'technical_supervisor_user_id',
        'status',
        'inspection_status',
        'inspection_notes',
        'inspected_by_user_id',
        'inspected_at',
        'current_approval_order',
        'entry_by_user_id',
        'submitted_by_user_id',
        'submitted_at',
        'approved_at',
        'stock_applied_at',
        'rejection_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_of_order' => 'date',
        'date_of_delivery' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'inspected_at' => 'datetime',
        'stock_applied_at' => 'datetime',
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

    public function inventoryOrder()
    {
        return $this->belongsTo(InventoryOrder::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(InventoryPurchaseOrder::class, 'inventory_purchase_order_id');
    }

    public function entryBy()
    {
        return $this->belongsTo(User::class, 'entry_by_user_id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function technicalSupervisor()
    {
        return $this->belongsTo(User::class, 'technical_supervisor_user_id');
    }

    public function lines()
    {
        return $this->hasMany(GoodsReceivedNoteLine::class);
    }

    public function approvals()
    {
        return $this->hasMany(GoodsReceivedNoteApproval::class)->orderBy('approval_order');
    }

    public function inspectedBy()
    {
        return $this->belongsTo(User::class, 'inspected_by_user_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function inspectionPassed(): bool
    {
        return $this->inspection_status === self::INSPECTION_PASSED;
    }

    public function hasVariance(): bool
    {
        return $this->lines->contains(function (GoodsReceivedNoteLine $line) {
            return abs((float) ($line->variance_quantity ?? 0)) > 0.0001;
        });
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
