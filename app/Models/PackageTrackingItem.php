<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\SharedTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;

class PackageTrackingItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'package_tracking_items';

    protected $fillable = [
        'package_tracking_id',
        'included_item_id',
        'total_quantity',
        'used_quantity',
        'remaining_quantity',
        'item_price',
        'notes'
    ];

    protected $casts = [
        'total_quantity' => 'integer',
        'used_quantity' => 'integer',
        'remaining_quantity' => 'integer',
        'item_price' => 'decimal:2',
    ];

    // Relationships
    public function packageTracking()
    {
        return $this->belongsTo(PackageTracking::class);
    }

    public function includedItem()
    {
        return $this->belongsTo(Item::class, 'included_item_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereHas('packageTracking', function($q) {
            $q->where('status', 'active');
        });
    }

    public function scopeValid($query)
    {
        return $query->whereHas('packageTracking', function($q) {
            $q->where('valid_until', '>=', SharedTime::businessToday())
              ->where('status', 'active');
        });
    }

    public function scopeForClient($query, $clientId)
    {
        return $query->whereHas('packageTracking', function($q) use ($clientId) {
            $q->where('client_id', $clientId);
        });
    }

    public function scopeForItem($query, $itemId)
    {
        return $query->where('included_item_id', $itemId);
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->whereHas('packageTracking', function($q) use ($businessId) {
            $q->where('business_id', $businessId);
        });
    }

    // Methods
    public function useQuantity($quantity = 1)
    {
        if ($this->remaining_quantity >= $quantity) {
            $this->increment('used_quantity', $quantity);
            $this->decrement('remaining_quantity', $quantity);
            
            // Update the main package tracking record's remaining quantity
            $this->packageTracking->updateRemainingQuantityFromItems();
            
            return true;
        }
        
        return false;
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

    protected static function booted()
    {
        static::creating(function ($trackingItem) {
            $trackingItem->uuid = (string) Str::uuid();
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}