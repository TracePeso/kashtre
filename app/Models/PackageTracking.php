<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\SharedTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;

class PackageTracking extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'package_tracking';

    protected $fillable = [
        'business_id',
        'client_id',
        'invoice_id',
        'package_item_id',
        'total_quantity',
        'used_quantity',
        'remaining_quantity',
        'valid_from',
        'valid_until',
        'status',
        'package_price',
        'package_money_moved',
        'money_moved_at',
        'money_movement_notes',
        'notes',
        'tracking_number'
    ];

    protected $casts = [
        'total_quantity' => 'integer',
        'used_quantity' => 'integer',
        'remaining_quantity' => 'integer',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'package_price' => 'decimal:2',
        'package_money_moved' => 'boolean',
        'money_moved_at' => 'datetime',
    ];

    // Relationships
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function packageItem()
    {
        return $this->belongsTo(Item::class, 'package_item_id');
    }

    public function trackingItems()
    {
        return $this->hasMany(PackageTrackingItem::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeValid($query)
    {
        return $query->where('valid_until', '>=', SharedTime::businessToday())
                    ->where('status', 'active');
    }

    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeForItem($query, $itemId)
    {
        return $query->whereHas('trackingItems', function($q) use ($itemId) {
            $q->where('included_item_id', $itemId);
        });
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    // Methods
    public function useQuantity($quantity = 1)
    {
        if ($this->remaining_quantity >= $quantity) {
            $this->increment('used_quantity', $quantity);
            $this->decrement('remaining_quantity', $quantity);
            
            // Check if fully used
            if ($this->remaining_quantity <= 0) {
                $this->update(['status' => 'fully_used']);
            }
            
            return true;
        }
        
        return false;
    }

    public function isExpired()
    {
        return $this->valid_until < SharedTime::businessToday();
    }

    public function isActive()
    {
        return $this->status === 'active' && !$this->isExpired();
    }

    public function hasRemainingQuantity()
    {
        return $this->remaining_quantity > 0;
    }

    public function getUsagePercentageAttribute()
    {
        if ($this->total_quantity > 0) {
            return round(($this->used_quantity / $this->total_quantity) * 100, 2);
        }
        return 0;
    }

    /**
     * Update status if all tracking items are fully used
     */
    public function updateStatusIfFullyUsed()
    {
        $allItemsUsed = $this->trackingItems()
            ->where('remaining_quantity', '>', 0)
            ->count() === 0;
            
        if ($allItemsUsed) {
            $this->update(['status' => 'fully_used']);
        }
    }

    /**
     * Update the main package tracking record's remaining quantity based on tracking items
     */
    public function updateRemainingQuantityFromItems()
    {
        $totalRemaining = $this->trackingItems()
            ->sum('remaining_quantity');
            
        $totalUsed = $this->trackingItems()
            ->sum('used_quantity');
            
        $this->update([
            'remaining_quantity' => $totalRemaining,
            'used_quantity' => $totalUsed
        ]);
        
        // Update status if fully used
        if ($totalRemaining <= 0) {
            $this->update(['status' => 'fully_used']);
        }
    }

    /**
     * Get all valid tracking items for a specific included item
     */
    public function getValidTrackingItemsForItem($itemId)
    {
        return $this->trackingItems()
            ->where('included_item_id', $itemId)
            ->where('remaining_quantity', '>', 0)
            ->get();
    }

    /**
     * Mark package money as moved to prevent double movement
     */
    public function markMoneyMoved($notes = null)
    {
        $this->update([
            'package_money_moved' => true,
            'money_moved_at' => now(),
            'money_movement_notes' => $notes
        ]);
    }

    /**
     * Check if package money has already been moved
     */
    public function hasMoneyMoved()
    {
        return $this->package_money_moved;
    }

    /**
     * Reset money movement status (for testing or corrections)
     */
    public function resetMoneyMovement()
    {
        $this->update([
            'package_money_moved' => false,
            'money_moved_at' => null,
            'money_movement_notes' => null
        ]);
    }

    protected static function booted()
    {
        static::creating(function ($tracking) {
            $tracking->uuid = (string) Str::uuid();
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
