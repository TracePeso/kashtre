<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\SharedTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class PackageUsage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'business_id',
        'invoice_id',
        'package_item_id',
        'included_item_id',
        'quantity_available',
        'quantity_used',
        'purchase_date',
        'expiry_date',
        'is_active',
    ];

    protected $casts = [
        'quantity_available' => 'integer',
        'quantity_used' => 'integer',
        'purchase_date' => 'date',
        'expiry_date' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($packageUsage) {
            $packageUsage->uuid = (string) Str::uuid();
        });
    }

    // Relationships
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function packageItem()
    {
        return $this->belongsTo(Item::class, 'package_item_id');
    }

    public function includedItem()
    {
        return $this->belongsTo(Item::class, 'included_item_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValid($query)
    {
        return $query->where('expiry_date', '>=', SharedTime::businessToday());
    }

    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeForItem($query, $itemId)
    {
        return $query->where('included_item_id', $itemId);
    }

    // Methods
    public function getRemainingQuantityAttribute()
    {
        return $this->quantity_available - $this->quantity_used;
    }

    public function isExpired()
    {
        return $this->expiry_date->isPast();
    }

    public function hasAvailableQuantity()
    {
        return $this->getRemainingQuantityAttribute() > 0 && !$this->isExpired();
    }

    public function useQuantity($quantity = 1)
    {
        if ($this->hasAvailableQuantity() && $this->getRemainingQuantityAttribute() >= $quantity) {
            $this->quantity_used += $quantity;
            $this->save();
            return true;
        }
        return false;
    }

    /**
     * Calculate package adjustment for a client's items
     */
    public static function calculatePackageAdjustment($clientId, $businessId, $items)
    {
        $totalAdjustment = 0;
        $adjustmentDetails = [];

        foreach ($items as $item) {
            $itemId = $item['id'] ?? $item['item_id'];
            $quantity = $item['quantity'] ?? 1;
            $price = $item['price'] ?? 0;

            // Find valid package usages for this item
            $packageUsages = self::active()
                ->valid()
                ->forClient($clientId)
                ->forItem($itemId)
                ->where('business_id', $businessId)
                ->orderBy('expiry_date', 'asc') // Use oldest packages first
                ->get();

            $remainingQuantity = $quantity;
            $itemAdjustment = 0;

            foreach ($packageUsages as $usage) {
                if ($remainingQuantity <= 0) break;

                $availableQuantity = $usage->getRemainingQuantityAttribute();
                if ($availableQuantity <= 0) continue;

                $quantityToUse = min($remainingQuantity, $availableQuantity);
                $itemAdjustment += $quantityToUse * $price;
                $remainingQuantity -= $quantityToUse;

                // Mark the usage (we'll save this later)
                $usage->useQuantity($quantityToUse);
            }

            if ($itemAdjustment > 0) {
                $adjustmentDetails[] = [
                    'item_id' => $itemId,
                    'item_name' => $item['name'] ?? 'Unknown',
                    'quantity_adjusted' => $quantity - $remainingQuantity,
                    'adjustment_amount' => $itemAdjustment,
                ];
            }

            $totalAdjustment += $itemAdjustment;
        }

        return [
            'total_adjustment' => $totalAdjustment,
            'details' => $adjustmentDetails,
        ];
    }

    /**
     * Create package usage records when a package is purchased
     */
    public static function createFromInvoice($invoice)
    {
        $items = $invoice->items ?? [];
        $createdUsages = [];

        foreach ($items as $item) {
            $itemId = $item['id'] ?? $item['item_id'];
            $itemModel = Item::find($itemId);

            if ($itemModel && $itemModel->type === 'package') {
                // Get package items
                $packageItems = $itemModel->packageItems()->with('includedItem')->get();

                foreach ($packageItems as $packageItem) {
                    $quantity = $item['quantity'] ?? 1;
                    $maxQuantity = $packageItem->max_quantity ?? 1;
                    $totalQuantity = $quantity * $maxQuantity;

                    // Create usage record for each included item
                    $usage = self::create([
                        'client_id' => $invoice->client_id,
                        'business_id' => $invoice->business_id,
                        'invoice_id' => $invoice->id,
                        'package_item_id' => $itemModel->id,
                        'included_item_id' => $packageItem->included_item_id,
                        'quantity_available' => $totalQuantity,
                        'quantity_used' => 0,
                        'purchase_date' => $invoice->created_at->toDateString(),
                        'expiry_date' => $invoice->created_at->addDays($itemModel->validity_days)->toDateString(),
                        'is_active' => true,
                    ]);

                    $createdUsages[] = $usage;
                }
            }
        }

        return $createdUsages;
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
