<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'generic_name',
        'category',
        'code',
        'type',
        'description',
        'group_id',
        'subgroup_id',
        'department_id',
        'uom_id',
        'default_price',
        'vat_rate',
        'validity_days',
        'max_qty',
        'hospital_share',
        'contractor_account_id',
        'business_id',
        'other_names',
    ];

    protected $casts = [
        'max_qty' => 'integer',
    ];
    
    protected static function booted()
    {
        static::creating(function ($item) {
            $item->uuid = (string) Str::uuid();
            
            // Auto-generate code if not provided
            if (empty($item->code)) {
                $item->code = self::generateUniqueCode($item->business_id);
            }
        });
    }

    /**
     * Generate a unique item code for the given business
     */
    public static function generateUniqueCode($businessId)
    {
        $prefix = 'ITM';
        
        // Try up to 10 times to generate a unique code
        $attempts = 0;
        do {
            // Use microtime + random to ensure uniqueness even in concurrent imports
            $timestamp = microtime(true);
            $microseconds = ($timestamp - floor($timestamp)) * 1000000;
            $random = mt_rand(0, 99); // Add randomness
            $uniqueId = floor($timestamp) . str_pad($microseconds, 6, '0', STR_PAD_LEFT) . $random;
            
            // Get the last 6 digits for the code
            $codeNumber = substr($uniqueId, -6);
            $code = $prefix . $codeNumber;
            
            // Check if code already exists
            $exists = self::where('code', $code)->exists();
            $attempts++;
            
            if (!$exists) {
                return $code;
            }
            
            // Small delay to ensure different microtime
            usleep(100); // 0.1ms delay
            
        } while ($attempts < 10);
        
        // Fallback: use a simple incrementing number
        $lastItem = self::orderBy('id', 'desc')->first();
        $nextNumber = $lastItem ? ($lastItem->id + 1) : 1;
        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function subgroup()
    {
        return $this->belongsTo(SubGroup::class, 'subgroup_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function itemUnit()
    {
        return $this->belongsTo(ItemUnit::class, 'uom_id');
    }

    public function inventoryStockLevel()
    {
        return $this->hasOne(InventoryStockLevel::class);
    }

    public function contractor()
    {
        return $this->belongsTo(ContractorProfile::class, 'contractor_account_id');
    }

    public function branchPrices()
    {
        return $this->hasMany(BranchItemPrice::class);
    }

    public function packageItems()
    {
        return $this->hasMany(PackageItem::class, 'package_item_id');
    }

    public function bulkItems()
    {
        return $this->hasMany(BulkItem::class, 'bulk_item_id');
    }

    public function includedInPackages()
    {
        return $this->hasMany(PackageItem::class, 'included_item_id');
    }

    public function includedInBulks()
    {
        return $this->hasMany(BulkItem::class, 'included_item_id');
    }

    public function branchServicePoints()
    {
        return $this->hasMany(BranchServicePoint::class, 'item_id');
    }

    /**
     * Get the display name for the item.
     * For package/bulk items, returns the constituent items names.
     * For regular items, returns the original name.
     */
    public function getDisplayNameAttribute()
    {
        if ($this->type === 'package') {
            return $this->getConstituentItemsName('package');
        } elseif ($this->type === 'bulk') {
            return $this->getConstituentItemsName('bulk');
        }
        
        return $this->name;
    }

    /**
     * Generate a name based on constituent items
     */
    private function getConstituentItemsName($type)
    {
        if ($type === 'package') {
            $constituents = $this->packageItems()->with('includedItem')->get();
            $itemNames = $constituents->map(function ($packageItem) {
                $maxQty = $packageItem->max_quantity ? " (Max: {$packageItem->max_quantity})" : '';
                return $packageItem->includedItem->name . $maxQty;
            })->toArray();
        } elseif ($type === 'bulk') {
            $constituents = $this->bulkItems()->with('includedItem')->get();
            $itemNames = $constituents->map(function ($bulkItem) {
                $fixedQty = $bulkItem->fixed_quantity ? " (Qty: {$bulkItem->fixed_quantity})" : '';
                return $bulkItem->includedItem->name . $fixedQty;
            })->toArray();
        } else {
            return $this->name;
        }

        if (empty($itemNames)) {
            return $this->name; // Fallback to original name if no constituents
        }

        $prefix = ucfirst($type) . ': ';
        return $prefix . implode(', ', $itemNames);
    }
}
