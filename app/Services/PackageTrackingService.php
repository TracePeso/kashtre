<?php

namespace App\Services;

use App\Models\PackageTracking;
use App\Models\PackageTrackingItem;
use App\Models\Invoice;
use App\Models\Item;
use App\Services\ClinicalModuleIntegrationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PackageTrackingService
{
    /**
     * Generate a unique tracking number for a package
     */
    public function generateTrackingNumber($invoiceId, $packageItemId, $sequence = 1)
    {
        $timestamp = now()->format('YmdHis');
        return "PKG-{$timestamp}-{$invoiceId}-{$packageItemId}-{$sequence}";
    }

    /**
     * Create package tracking records for a package purchase
     */
    public function createPackageTracking($invoice, $packageItem, $quantity = 1)
    {
        DB::beginTransaction();
        
        try {
            Log::info("=== CREATING PACKAGE TRACKING ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'package_item_id' => $packageItem['id'],
                'package_name' => $packageItem['name'],
                'quantity' => $quantity
            ]);

            // Check if package tracking already exists to prevent duplicates
            $existingTracking = PackageTracking::where([
                'invoice_id' => $invoice->id,
                'package_item_id' => $packageItem['id'],
                'client_id' => $invoice->client_id
            ])->first();

            if ($existingTracking) {
                Log::warning("Package tracking record already exists - skipping creation to prevent duplicate", [
                    'existing_tracking_id' => $existingTracking->id,
                    'tracking_number' => $existingTracking->tracking_number,
                    'invoice_id' => $invoice->id,
                    'package_item_id' => $packageItem['id'],
                    'client_id' => $invoice->client_id
                ]);
                return $existingTracking; // Return existing record instead of creating new one
            }

            // Get the package item from database
            $itemModel = Item::find($packageItem['id']);
            if (!$itemModel || $itemModel->type !== 'package') {
                throw new \Exception("Item is not a package: {$packageItem['id']}");
            }

            // Get included items for this package
            $packageItems = $itemModel->packageItems()->with('includedItem')->get();
            if ($packageItems->isEmpty()) {
                throw new \Exception("Package has no included items: {$packageItem['id']}");
            }

            // Generate unique tracking number
            $trackingNumber = $this->generateTrackingNumber($invoice->id, $packageItem['id']);
            
            // Calculate total quantities
            $totalQuantity = 0;
            foreach ($packageItems as $pkgItem) {
                $totalQuantity += ($pkgItem->max_quantity ?? 1) * $quantity;
            }

            // Create main package tracking record
            $packageTracking = PackageTracking::create([
                'business_id' => $invoice->business_id,
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'package_item_id' => $packageItem['id'],
                'total_quantity' => $totalQuantity,
                'used_quantity' => 0,
                'remaining_quantity' => $totalQuantity,
                'valid_from' => now()->toDateString(),
                'valid_until' => now()->addDays(365)->toDateString(),
                'status' => 'active',
                'package_price' => $packageItem['price'] ?? 0,
                'notes' => "Package: {$itemModel->name}, Invoice: {$invoice->invoice_number}",
                'tracking_number' => $trackingNumber
            ]);

            Log::info("Package tracking record created", [
                'package_tracking_id' => $packageTracking->id,
                'tracking_number' => $trackingNumber,
                'total_quantity' => $totalQuantity
            ]);

            // Create tracking items for each included item
            foreach ($packageItems as $pkgItem) {
                $includedItem = $pkgItem->includedItem;
                $includedItemQuantity = ($pkgItem->max_quantity ?? 1) * $quantity;
                $includedItemPrice = $includedItem->default_price ?? 0;

                // Check if package tracking item already exists to prevent duplicate key constraint
                $existingTrackingItem = PackageTrackingItem::where([
                    'package_tracking_id' => $packageTracking->id,
                    'included_item_id' => $includedItem->id
                ])->first();

                if ($existingTrackingItem) {
                    Log::warning("Package tracking item already exists - skipping creation to prevent duplicate", [
                        'existing_item_id' => $existingTrackingItem->id,
                        'package_tracking_id' => $packageTracking->id,
                        'included_item_id' => $includedItem->id,
                        'included_item_name' => $includedItem->name
                    ]);
                    continue; // Skip to next item
                }

                PackageTrackingItem::create([
                    'package_tracking_id' => $packageTracking->id,
                    'included_item_id' => $includedItem->id,
                    'total_quantity' => $includedItemQuantity,
                    'used_quantity' => 0,
                    'remaining_quantity' => $includedItemQuantity,
                    'item_price' => $includedItemPrice,
                    'notes' => "Included in package: {$itemModel->name}"
                ]);

                Log::info("Package tracking item created", [
                    'included_item_id' => $includedItem->id,
                    'included_item_name' => $includedItem->name,
                    'quantity' => $includedItemQuantity,
                    'price' => $includedItemPrice
                ]);
            }

            DB::commit();
            
            Log::info("=== PACKAGE TRACKING CREATION COMPLETED ===", [
                'package_tracking_id' => $packageTracking->id,
                'tracking_number' => $trackingNumber,
                'included_items_count' => $packageItems->count()
            ]);

            // Hand the allocation to the Clinical Module so it can decrement on
            // clinical use. Its own try/catch matters: we are past DB::commit(),
            // so letting this reach the outer catch would rethrow and fail a
            // sale that has already been paid for and written.
            try {
                app(ClinicalModuleIntegrationService::class)->notifyEntitlementsGranted($packageTracking);
            } catch (\Throwable $e) {
                Log::warning("Clinical entitlement notification failed", [
                    'package_tracking_id' => $packageTracking->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $packageTracking;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to create package tracking", [
                'error' => $e->getMessage(),
                'invoice_id' => $invoice->id,
                'package_item_id' => $packageItem['id']
            ]);
            throw $e;
        }
    }

    /**
     * Calculate package adjustment without actually using the items.
     * This method only calculates what the adjustment would be without modifying the database.
     *
     * @param Invoice $invoice A mock or actual invoice object containing client_id and business_id.
     * @param array $items An array of items being purchased, with 'id' and 'quantity'.
     * @return array An array containing 'total_adjustment' and 'details' of adjustments.
     */
    public function calculatePackageAdjustment($invoice, $items)
    {
        Log::info("=== USING PACKAGE ITEMS (NEW STRUCTURE) ===", [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'items_count' => count($items)
        ]);

        $totalAdjustment = 0;
        $adjustmentDetails = [];
        $maxQtyWarnings = [];
        
        // First, collect all package items and their total requested quantities
        $packageItemQuantities = [];
        foreach ($items as $item) {
            $itemId = $item['id'] ?? $item['item_id'];
            $quantity = $item['quantity'] ?? 1;
            
            // Find valid package tracking items for this included item
            $validTrackingItems = PackageTrackingItem::active()
                ->valid()
                ->forClient($invoice->client_id)
                ->forBusiness($invoice->business_id)
                ->forItem($itemId)
                ->where('remaining_quantity', '>', 0)
                ->orderBy('created_at', 'asc')
                ->get();
            
            // Track which packages this item belongs to (to avoid double counting)
            $packagesForThisItem = [];
            
            foreach ($validTrackingItems as $trackingItem) {
                $packageItem = $trackingItem->packageTracking->packageItem;
                if ($packageItem) {
                    $packageId = $packageItem->id;
                    
                    // Only count this item's quantity once per package
                    if (!isset($packagesForThisItem[$packageId])) {
                        $packagesForThisItem[$packageId] = true;
                        
                        if (!isset($packageItemQuantities[$packageId])) {
                            $packageItemQuantities[$packageId] = [
                                'package_item' => $packageItem,
                                'total_requested' => 0
                            ];
                        }
                        // Add the quantity being requested for this item (only once per package)
                        $packageItemQuantities[$packageId]['total_requested'] += $quantity;
                    }
                }
            }
        }
        
        // Check max_qty for each package BEFORE processing
        Log::info("=== CHECKING MAX_QTY FOR PACKAGES ===", [
            'packages_found' => count($packageItemQuantities),
            'package_details' => array_map(function($data) {
                return [
                    'package_id' => $data['package_item']->id,
                    'package_name' => $data['package_item']->name,
                    'max_qty' => $data['package_item']->max_qty,
                    'total_requested' => $data['total_requested']
                ];
            }, $packageItemQuantities)
        ]);
        
        foreach ($packageItemQuantities as $packageId => $packageData) {
            $packageItem = $packageData['package_item'];
            $totalRequestedQty = $packageData['total_requested'];
            
            // Treat null max_qty as 1 (default value)
            $maxQty = $packageItem->max_qty ?? 1;
            
            Log::info("Checking package max_qty", [
                'package_id' => $packageItem->id,
                'package_name' => $packageItem->name,
                'max_qty_raw' => $packageItem->max_qty,
                'max_qty_effective' => $maxQty,
                'max_qty_is_null' => $packageItem->max_qty === null,
                'total_requested_qty' => $totalRequestedQty
            ]);
            
            // Always check max_qty (treat null as 1)
            {
                // Calculate total consumed quantity across all uses for this package item
                $totalConsumedQty = \App\Models\PackageTracking::where('package_item_id', $packageItem->id)
                    ->sum('used_quantity');
                
                Log::info("Package max_qty check details", [
                    'package_id' => $packageItem->id,
                    'package_name' => $packageItem->name,
                    'max_qty_raw' => $packageItem->max_qty,
                    'max_qty_effective' => $maxQty,
                    'total_consumed_qty' => $totalConsumedQty,
                    'total_requested_qty' => $totalRequestedQty,
                    'would_exceed' => ($totalConsumedQty + $totalRequestedQty) > $maxQty,
                    'calculation' => "{$totalConsumedQty} + {$totalRequestedQty} = " . ($totalConsumedQty + $totalRequestedQty) . " > {$maxQty}"
                ]);
                
                // Check if the total requested quantity would exceed max_qty
                if (($totalConsumedQty + $totalRequestedQty) > $maxQty) {
                    $allowedQty = max(0, $maxQty - $totalConsumedQty);
                    
                    Log::warning("MAX_QTY EXCEEDED - Adding warning", [
                        'package_id' => $packageItem->id,
                        'package_name' => $packageItem->name,
                        'max_qty' => $packageItem->max_qty,
                        'total_consumed_qty' => $totalConsumedQty,
                        'total_requested_qty' => $totalRequestedQty,
                        'allowed_qty' => $allowedQty
                    ]);
                    
                    if ($allowedQty <= 0) {
                        // Cannot use any more - add warning
                        $maxQtyWarnings[] = [
                            'package_name' => $packageItem->name,
                            'package_id' => $packageItem->id,
                            'max_qty' => $maxQty,
                            'total_consumed_qty' => $totalConsumedQty,
                            'requested_quantity' => $totalRequestedQty,
                            'message' => "Package '{$packageItem->name}' has reached its Maximum Total Quantity limit of {$maxQty} (currently used: {$totalConsumedQty}, requested: {$totalRequestedQty}). Please update the Maximum Total Quantity before proceeding."
                        ];
                    } else {
                        // Would exceed but can use some - add warning
                        $maxQtyWarnings[] = [
                            'package_name' => $packageItem->name,
                            'package_id' => $packageItem->id,
                            'max_qty' => $maxQty,
                            'total_consumed_qty' => $totalConsumedQty,
                            'requested_quantity' => $totalRequestedQty,
                            'allowed_quantity' => $allowedQty,
                            'message' => "Package '{$packageItem->name}' will exceed its Maximum Total Quantity limit of {$maxQty} (currently used: {$totalConsumedQty}, requested: {$totalRequestedQty}, allowed: {$allowedQty}). Please update the Maximum Total Quantity before proceeding."
                        ];
                    }
                }
            }
        }
        
        // Log warnings but don't prevent calculation - we'll still calculate the adjustment
        // The frontend will show the alert and block saving
        if (!empty($maxQtyWarnings)) {
            Log::warning("Package max_qty warnings detected - will show alert but still calculate adjustment", [
                'warnings_count' => count($maxQtyWarnings),
                'warnings' => $maxQtyWarnings
            ]);
        }

        foreach ($items as $item) {
            $itemId = $item['id'] ?? $item['item_id'];
            $quantity = $item['quantity'] ?? 1;
            $price = $item['price'] ?? 0;

            Log::info("Processing item for package adjustment calculation in service", [
                'item_id' => $itemId,
                'item_name' => $item['name'] ?? 'Unknown',
                'quantity' => $quantity,
                'current_item_price' => $price
            ]);

            $remainingQuantityToAdjust = $quantity;

            // Find valid package tracking items for this included item
            $validTrackingItems = PackageTrackingItem::active()
                ->valid()
                ->forClient($invoice->client_id)
                ->forBusiness($invoice->business_id)
                ->forItem($itemId)
                ->where('remaining_quantity', '>', 0)
                ->orderBy('created_at', 'asc') // Use oldest packages first (FIFO)
                ->get();

            foreach ($validTrackingItems as $trackingItem) {
                if ($remainingQuantityToAdjust <= 0) break;

                $availableQuantityInTrackingItem = $trackingItem->remaining_quantity;
                $quantityToUse = min($remainingQuantityToAdjust, $availableQuantityInTrackingItem);

                if ($quantityToUse > 0) {

                    // Skip if quantity became 0 after max_qty check
                    if ($quantityToUse <= 0) {
                        continue;
                    }

                    // Calculate adjustment based on the price of the item in the current invoice
                    $itemAdjustment = $quantityToUse * $price;
                    $totalAdjustment += $itemAdjustment;

                    // NOTE: We don't actually use the quantity here - this is just for calculation
                    // The actual usage happens when the invoice is saved/paid

                    Log::info("Package adjustment applied to tracking item", [
                        'tracking_item_id' => $trackingItem->id,
                        'package_tracking_id' => $trackingItem->package_tracking_id,
                        'included_item_id' => $itemId,
                        'quantity_used' => $quantityToUse,
                        'item_adjustment_amount' => $itemAdjustment,
                        'new_remaining_quantity' => $trackingItem->remaining_quantity
                    ]);

                    $adjustmentDetails[] = [
                        'item_id' => $itemId,
                        'item_name' => $item['name'] ?? 'Unknown',
                        'quantity_adjusted' => $quantityToUse,
                        'adjustment_amount' => $itemAdjustment,
                        'package_name' => $trackingItem->packageTracking->packageItem->name ?? 'Unknown Package',
                        'package_tracking_id' => $trackingItem->package_tracking_id,
                        'tracking_number' => $trackingItem->packageTracking->tracking_number ?? "PKG-{$trackingItem->package_tracking_id}-{$trackingItem->packageTracking->created_at->format('YmdHis')}",
                        'tracking_item_id' => $trackingItem->id,
                        'package_expiry' => $trackingItem->packageTracking->valid_until->format('Y-m-d'),
                        'remaining_in_package_item' => $trackingItem->remaining_quantity,
                    ];

                    $remainingQuantityToAdjust -= $quantityToUse;
                }
            }
        }

        Log::info("Package usage completed", [
            'total_adjustment' => $totalAdjustment,
            'adjustment_details' => $adjustmentDetails,
            'max_qty_warnings' => $maxQtyWarnings
        ]);

        return [
            'total_adjustment' => $totalAdjustment,
            'details' => $adjustmentDetails,
            'max_qty_warnings' => $maxQtyWarnings,
        ];
    }

    /**
     * Actually use package items for an invoice (when invoice is saved/paid).
     * This method will find available package tracking items and mark them as used.
     *
     * @param Invoice $invoice The actual invoice object.
     * @param array $items An array of items being purchased, with 'id' and 'quantity'.
     * @return array An array containing 'total_adjustment' and 'details' of adjustments.
     */
    public function usePackageItems($invoice, $items)
    {
        Log::info("=== ACTUALLY USING PACKAGE ITEMS (NEW STRUCTURE) ===", [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $invoice->client_id,
            'business_id' => $invoice->business_id,
            'items_count' => count($items),
            'items' => $items,
            'timestamp' => now()->toDateTimeString()
        ]);

        $totalAdjustment = 0;
        $adjustmentDetails = [];

        foreach ($items as $item) {
            $itemId = $item['id'] ?? $item['item_id'];
            $quantity = $item['quantity'] ?? 1;
            $price = $item['price'] ?? 0;

            Log::info("=== PROCESSING ITEM FOR PACKAGE USAGE ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'item_id' => $itemId,
                'item_name' => $item['name'] ?? 'Unknown',
                'quantity' => $quantity,
                'current_item_price' => $price,
                'client_id' => $invoice->client_id,
                'business_id' => $invoice->business_id,
                'timestamp' => now()->toDateTimeString()
            ]);

            $remainingQuantityToAdjust = $quantity;

            // Find valid package tracking items for this included item
            $validTrackingItems = PackageTrackingItem::active()
                ->valid()
                ->forClient($invoice->client_id)
                ->forBusiness($invoice->business_id)
                ->forItem($itemId)
                ->where('remaining_quantity', '>', 0)
                ->orderBy('created_at', 'asc') // Use oldest packages first (FIFO)
                ->get();

            foreach ($validTrackingItems as $trackingItem) {
                if ($remainingQuantityToAdjust <= 0) break;

                $availableQuantityInTrackingItem = $trackingItem->remaining_quantity;
                $quantityToUse = min($remainingQuantityToAdjust, $availableQuantityInTrackingItem);

                if ($quantityToUse > 0) {
                    // Get the package item (the actual package, not the included item)
                    $packageItem = $trackingItem->packageTracking->packageItem;
                    
                    // Check max_qty constraint if set
                    if ($packageItem && $packageItem->max_qty !== null) {
                        // Calculate total consumed quantity across all uses for this package item
                        $totalConsumedQty = PackageTracking::where('package_item_id', $packageItem->id)
                            ->sum('used_quantity');
                        
                        // Check if consuming this quantity would exceed max_qty
                        if (($totalConsumedQty + $quantityToUse) > $packageItem->max_qty) {
                            $allowedQty = max(0, $packageItem->max_qty - $totalConsumedQty);
                            
                            if ($allowedQty <= 0) {
                                Log::warning("Package max_qty exceeded - cannot use any more quantity", [
                                    'package_item_id' => $packageItem->id,
                                    'package_name' => $packageItem->name,
                                    'max_qty' => $packageItem->max_qty,
                                    'total_consumed_qty' => $totalConsumedQty,
                                    'requested_quantity' => $quantityToUse
                                ]);
                                continue; // Skip this package, try next one
                            }
                            
                            // Limit quantity to what's allowed
                            $quantityToUse = min($quantityToUse, $allowedQty);
                            
                            Log::info("Package max_qty constraint applied - limiting quantity", [
                                'package_item_id' => $packageItem->id,
                                'package_name' => $packageItem->name,
                                'max_qty' => $packageItem->max_qty,
                                'total_consumed_qty' => $totalConsumedQty,
                                'original_quantity' => $availableQuantityInTrackingItem,
                                'allowed_quantity' => $allowedQty,
                                'final_quantity_to_use' => $quantityToUse
                            ]);
                        }
                    }
                    
                    // Skip if quantity became 0 after max_qty check
                    if ($quantityToUse <= 0) {
                        continue;
                    }

                    // Calculate adjustment based on the price of the item in the current invoice
                    $itemAdjustment = $quantityToUse * $price;
                    $totalAdjustment += $itemAdjustment;

                    // Actually mark the quantity as used in the tracking item
                    $trackingItem->useQuantity($quantityToUse);

                    Log::info("Package items actually used in tracking item", [
                        'tracking_item_id' => $trackingItem->id,
                        'package_tracking_id' => $trackingItem->package_tracking_id,
                        'included_item_id' => $itemId,
                        'quantity_used' => $quantityToUse,
                        'item_adjustment_amount' => $itemAdjustment,
                        'new_remaining_quantity' => $trackingItem->remaining_quantity
                    ]);

                    $adjustmentDetails[] = [
                        'item_id' => $itemId,
                        'item_name' => $item['name'] ?? 'Unknown',
                        'quantity_adjusted' => $quantityToUse,
                        'adjustment_amount' => $itemAdjustment,
                        'package_name' => $trackingItem->packageTracking->packageItem->name ?? 'Unknown Package',
                        'package_tracking_id' => $trackingItem->package_tracking_id,
                        'tracking_number' => $trackingItem->packageTracking->tracking_number ?? "PKG-{$trackingItem->package_tracking_id}-{$trackingItem->packageTracking->created_at->format('YmdHis')}",
                        'tracking_item_id' => $trackingItem->id,
                        'package_expiry' => $trackingItem->packageTracking->valid_until->format('Y-m-d'),
                        'remaining_in_package_item' => $trackingItem->remaining_quantity,
                    ];

                    $remainingQuantityToAdjust -= $quantityToUse;
                }
            }
        }

        return [
            'total_adjustment' => $totalAdjustment,
            'details' => $adjustmentDetails,
        ];
    }

    /**
     * Get valid package tracking records for a client
     */
    public function getValidPackagesForClient($clientId, $businessId)
    {
        return PackageTracking::where('client_id', $clientId)
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->where('remaining_quantity', '>', 0)
            ->where('valid_until', '>=', now()->toDateString())
            ->with(['trackingItems.includedItem', 'packageItem'])
            ->get();
    }

    /**
     * Get package information for invoice descriptions
     */
    public function getPackageInfoForInvoice($invoice)
    {
        try {
            $validPackages = $this->getValidPackagesForClient($invoice->client_id, $invoice->business_id);

            $packageDescriptions = [];
            $packageTrackingNumbers = [];
            
            foreach ($validPackages as $packageTracking) {
                if ($packageTracking->tracking_number) {
                    $packageName = $packageTracking->packageItem->name ?? 'Unknown Package';
                    $trackingNumber = $packageTracking->tracking_number;
                    $packageDescriptions[] = "{$packageName} (Ref: {$trackingNumber})";
                    $packageTrackingNumbers[] = $trackingNumber;
                }
            }
            
            // Simplify description - use first package, add "and X more" if multiple
            if (count($packageDescriptions) == 1) {
                $description = $packageDescriptions[0];
            } elseif (count($packageDescriptions) > 1) {
                $description = $packageDescriptions[0] . " and " . (count($packageDescriptions) - 1) . " more";
            } else {
                $description = 'Package items';
            }
            
            $trackingNumbers = implode(', ', array_unique($packageTrackingNumbers));
            
            return [
                'description' => $description,
                'tracking_numbers' => $trackingNumbers ?: 'N/A'
            ];
            
        } catch (\Exception $e) {
            Log::error("Failed to get package info for invoice", [
                'error' => $e->getMessage(),
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id
            ]);
            
            return [
                'description' => 'Package items',
                'tracking_numbers' => 'N/A'
            ];
        }
    }
}
