<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\Business;
use App\Models\Branch;
use App\Models\BranchItemPrice;
use App\Models\PackageItem;
use App\Models\BulkItem;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Facades\Log;

class PackageBulkTemplateImport implements ToModel, WithHeadingRow, SkipsOnError, WithEvents
{
    protected $businessId;
    protected $successCount = 0;
    protected $errorCount = 0;
    protected $errors = [];
    protected $branchPrices = [];
    protected $includedItems = [];
    protected $pendingIncludedItems = [];
    protected $branches = [];

    public function __construct($businessId)
    {
        $this->businessId = $businessId;
        // Load branches for this business
        $this->branches = Branch::where('business_id', $businessId)->orderBy('name')->get();
        
        Log::info("=== PACKAGE/BULK IMPORT INITIALIZED ===");
        Log::info("Business ID: {$businessId}");
        Log::info("Branches found: " . count($this->branches));
        Log::info("Template supports unlimited constituent items (dynamically detected from Excel)");
    }
    
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $this->processHorizontalTemplate($event->sheet);
            },
        ];
    }
    
    private function processHorizontalTemplate($sheet)
    {
        Log::info("=== PROCESSING HORIZONTAL TEMPLATE ===");
        
        // Get all data from the sheet
        $data = $sheet->toArray(null, true, true, true);
        
        // Find the row with names (should be row 1, index 0)
        $namesRow = null;
        $typesRow = null;
        $pricesRow = null;
        $purchasePricesRow = null;
        $descriptionsRow = null;
        $validityRow = null;
        $otherNamesRow = null;
        $branchPriceRows = [];
        $constituentsHeaderRow = null;
        
        foreach ($data as $index => $row) {
            if (isset($row[0]) && $row[0] === 'Name') {
                $namesRow = $index;
            } elseif (isset($row[0]) && $row[0] === 'Type (package/bulk)') {
                $typesRow = $index;
            } elseif (isset($row[0]) && in_array($row[0], ['Sale Price', 'Default Price'], true)) {
                $pricesRow = $index;
            } elseif (isset($row[0]) && $row[0] === 'Purchase Price') {
                $purchasePricesRow = $index;
            } elseif (isset($row[0]) && $row[0] === 'Description') {
                $descriptionsRow = $index;
            } elseif (isset($row[0]) && $row[0] === 'Validity Period (Days) - Required for packages') {
                $validityRow = $index;
            } elseif (isset($row[0]) && $row[0] === 'Other Names') {
                $otherNamesRow = $index;
            } elseif (isset($row[0]) && strpos($row[0], ' - Price') !== false) {
                // This is a branch price row
                $branchPriceRows[] = [
                    'index' => $index,
                    'branch_name' => str_replace(' - Price', '', $row[0])
                ];
            } elseif (isset($row[0]) && strpos($row[0], 'Constituents') !== false) {
                // This is the constituents header row
                $constituentsHeaderRow = $index;
            }
        }
        
        // Detect the maximum number of columns by checking the names row
        $maxColumns = 0;
        if ($namesRow !== null && isset($data[$namesRow])) {
            $maxColumns = count($data[$namesRow]);
        }
        
        Log::info("Found rows - Names: " . ($namesRow + 1) . ", Types: " . ($typesRow + 1) . ", Prices: " . ($pricesRow + 1));
        Log::info("Maximum columns detected: " . $maxColumns . " (will process all items, no 25-item limit)");
        
        // Process each item column (Item1, Item2, Item3, etc.) - dynamically based on actual columns
        for ($i = 1; $i < $maxColumns; $i++) {
            $itemName = $data[$namesRow][$i] ?? null;
            $itemType = $data[$typesRow][$i] ?? null;
            $itemPrice = $data[$pricesRow][$i] ?? null;
            $itemPurchasePrice = $purchasePricesRow !== null
                ? ($data[$purchasePricesRow][$i] ?? null)
                : null;
            
            // Convert to strings to handle boolean values from Excel
            $itemName = (string) $itemName;
            $itemType = (string) $itemType;
            $itemPrice = (string) $itemPrice;
            $itemPurchasePrice = (string) $itemPurchasePrice;
            
            // Skip if no name or if name looks like template instructions
            if (empty($itemName) || 
                is_numeric($itemName) || 
                strpos($itemName, 'Default:') !== false || 
                strpos($itemName, 'Add columns') !== false ||
                strpos($itemName, 'Type dropdowns') !== false ||
                strpos($itemName, 'Fill in package') !== false ||
                strpos($itemName, 'INSTRUCTIONS') !== false ||
                strpos($itemName, 'TEMPLATE') !== false) {
                Log::info("Skipping Item{$i}: Empty or template instruction - '{$itemName}'");
                continue;
            }
            
            // Skip if type is numeric (template artifacts)
            if (is_numeric($itemType)) {
                Log::info("Skipping Item{$i}: Type is numeric (template artifact) - '{$itemType}'");
                continue;
            }
            
            // Skip if both type and price are empty
            if (empty($itemType) && empty($itemPrice)) {
                Log::info("Skipping Item{$i}: No type or price data - '{$itemName}'");
                continue;
            }
            $itemDescription = $data[$descriptionsRow][$i] ?? null;
            $itemValidity = $data[$validityRow][$i] ?? null;
            $itemOtherNames = $data[$otherNamesRow][$i] ?? null;
            
            Log::info("Processing Item{$i}: {$itemName} (Type: {$itemType}, Price: {$itemPrice})");
            
            // Create the item
            $item = $this->createPackageBulkItem($itemName, $itemType, $itemPrice, $itemPurchasePrice, $itemDescription, $itemValidity, $itemOtherNames, $i);
            
            // If item was created successfully, capture branch prices and constituent items
            if ($item) {
                $this->captureBranchPrices($data, $branchPriceRows, $item, $i);
                $this->captureConstituentItems($data, $constituentsHeaderRow, $item, $i);
            }
        }
    }
    
    private function createPackageBulkItem($name, $type, $price, $purchasePrice, $description, $validity, $otherNames, $itemNumber)
    {
        try {
            // Validate required fields
            if (empty($name)) {
                $this->errors[] = "Item{$itemNumber}: Name is required";
                    $this->errorCount++;
                return;
            }
            
            if (empty($type)) {
                $this->errors[] = "Item{$itemNumber}: Type is required";
                $this->errorCount++;
                return;
            }
            
            if (!in_array(strtolower($type), ['package', 'bulk'])) {
                $this->errors[] = "Item{$itemNumber}: Type must be 'package' or 'bulk', got '{$type}'";
                    $this->errorCount++;
                return;
            }

            if ($purchasePrice === '' || !is_numeric($purchasePrice) || (float) $purchasePrice < 0) {
                $this->errors[] = "Item{$itemNumber}: Purchase price is required and must be a number greater than or equal to 0";
                $this->errorCount++;
                return;
            }
            
            // Create the item (code will be auto-generated by Item model if not provided)
            $item = Item::create([
                'name' => $name,
                'code' => null, // Let the Item model auto-generate unique code
                'type' => strtolower($type),
                'description' => $description,
                'default_price' => $price ?? 0,
                'purchase_price' => round((float) $purchasePrice, 2),
                'vat_rate' => 0.00,
                'validity_days' => $validity,
                'hospital_share' => 100,
                'other_names' => $otherNames,
                'business_id' => $this->businessId,
                'group_id' => null,
                'subgroup_id' => null,
                'department_id' => null,
                'uom_id' => null,
                'contractor_account_id' => null,
            ]);
            
            Log::info("Successfully created item: {$name} (ID: {$item->id})");
            $this->successCount++;
            
            return $item;

        } catch (\Exception $e) {
            Log::error("Error creating item {$name}: " . $e->getMessage());
            $this->errors[] = "Item{$itemNumber}: Error creating item - " . $e->getMessage();
            $this->errorCount++;
            return null;
        }
    }

    private function captureBranchPrices($data, $branchPriceRows, $item, $itemNumber)
    {
        Log::info("=== CAPTURING BRANCH PRICES FOR ITEM {$itemNumber} ===");
        Log::info("Item: {$item->name} (ID: {$item->id})");
        Log::info("Found " . count($branchPriceRows) . " branch price rows");
        
        foreach ($branchPriceRows as $branchPriceRow) {
            $branchName = $branchPriceRow['branch_name'];
            $rowIndex = $branchPriceRow['index'];
            $columnIndex = $itemNumber; // Item1 = column 1, Item2 = column 2, etc.
            
            $branchPrice = $data[$rowIndex][$columnIndex] ?? null;
            
            Log::info("Checking branch price for {$branchName}", [
                'row_index' => $rowIndex,
                'column_index' => $columnIndex,
                'raw_value' => $branchPrice,
                'is_empty' => empty($branchPrice),
                'is_numeric' => is_numeric($branchPrice),
                'will_create_price' => (!empty($branchPrice) && is_numeric($branchPrice))
            ]);
            
            if (!empty($branchPrice) && is_numeric($branchPrice)) {
                // Find the branch by name
                $branch = $this->branches->where('name', $branchName)->first();
                
                if ($branch) {
                    $this->branchPrices[] = [
                        'business_id' => $this->businessId,
                        'item_id' => $item->id,
                        'branch_id' => $branch->id,
                        'price' => (float) $branchPrice,
                        'item_code' => $item->code
                    ];
                    
                    Log::info("✓ CREATING branch price for {$branchName}: {$branchPrice} (explicitly provided)");
                } else {
                    Log::warning("Branch not found: {$branchName}");
                }
            } else {
                Log::info("✗ SKIPPING branch price for {$branchName} - no valid price provided (empty or non-numeric)");
            }
        }
    }
    
    private function captureConstituentItems($data, $constituentsHeaderRow, $item, $itemNumber)
    {
        if (!$constituentsHeaderRow) {
            Log::info("No constituents header row found for Item{$itemNumber}");
            return;
        }
        
        Log::info("=== CAPTURING CONSTITUENT ITEMS FOR ITEM {$itemNumber} ===");
        Log::info("Item: {$item->name} (ID: {$item->id}, Type: {$item->type})");
        Log::info("Constituents header row: " . ($constituentsHeaderRow + 1));
        
        // Process constituent items starting from the row after the header
        $constituentRow = $constituentsHeaderRow + 1;
        $constituentCount = 0;
        
        // Look for constituent items in ALL rows until end of data (no limit)
        $maxRows = count($data);
        Log::info("Will scan up to " . ($maxRows - $constituentRow) . " rows for constituent items");
        
        for ($row = $constituentRow; $row < $maxRows; $row++) {
            if (!isset($data[$row]) || !isset($data[$row][0])) {
                continue;
            }
            
            $constituentName = $data[$row][0];
            $quantity = $data[$row][$itemNumber] ?? null; // Get quantity from the item's column
            
            // Skip if no constituent name or quantity
            if (empty($constituentName) || empty($quantity) || !is_numeric($quantity)) {
                continue;
            }
            
            // Extract item name and code from format "Item Name (Item Code)"
            // This handles both old format (just name) and new format (name with code)
            $itemName = $constituentName;
            $itemCode = null;
            
            if (preg_match('/^(.+?)\s*\(([^)]+)\)$/', $constituentName, $matches)) {
                $itemName = trim($matches[1]);
                $itemCode = trim($matches[2]);
            }
            
            // Find the constituent item in the database
            // Try to match by code first (more precise), then by name
            $constituentItem = null;
            
            if ($itemCode) {
                $constituentItem = Item::where('business_id', $this->businessId)
                    ->where('code', $itemCode)
                    ->whereIn('type', ['service', 'good'])
                    ->first();
            }
            
            // If not found by code, try by name
            if (!$constituentItem) {
                $constituentItem = Item::where('business_id', $this->businessId)
                    ->where('name', $itemName)
                    ->whereIn('type', ['service', 'good'])
                    ->first();
            }
            
            if ($constituentItem) {
                $constituentCount++;
                
                if ($item->type === 'package') {
                    $this->pendingIncludedItems[] = [
                        'type' => 'package',
                        'business_id' => $this->businessId,
                        'package_item_id' => $item->id,
                        'included_item_id' => $constituentItem->id,
                        'max_quantity' => (int) $quantity,
                        'item_name' => $item->name,
                        'constituent_name' => $constituentItem->name
                    ];
                } elseif ($item->type === 'bulk') {
            $this->pendingIncludedItems[] = [
                        'type' => 'bulk',
                        'business_id' => $this->businessId,
                        'bulk_item_id' => $item->id,
                        'included_item_id' => $constituentItem->id,
                        'fixed_quantity' => (int) $quantity,
                        'item_name' => $item->name,
                        'constituent_name' => $constituentItem->name
                    ];
                }
                
                Log::info("Found constituent: {$constituentItem->name} (Code: {$constituentItem->code}, Qty: {$quantity})");
            } else {
                Log::warning("Constituent item not found in database: {$constituentName} (parsed as Name: '{$itemName}'" . ($itemCode ? ", Code: '{$itemCode}'" : "") . ")");
            }
        }
        
        Log::info("=== CONSTITUENT ITEMS CAPTURE SUMMARY ===");
        Log::info("Item: {$item->name} ({$item->type})");
        Log::info("Total constituent items found and captured: {$constituentCount}");
        
        if ($constituentCount === 0) {
            Log::warning("WARNING: No constituent items were captured for this {$item->type} item!");
        }
    }
    
    public function model(array $row)
    {
        // For horizontal template, we process everything in the AfterSheet event
        // This method is called for each row, but we handle everything at once
        return null;
    }

    public function onError(\Throwable $e)
    {
        Log::error("Import error: " . $e->getMessage());
        $this->errorCount++;
        $this->errors[] = "Import error: " . $e->getMessage();
    }

    public function createBranchPrices()
    {
        Log::info("=== CREATING BRANCH PRICES ===");
        Log::info("Total branch prices to create: " . count($this->branchPrices));
        
        if (count($this->branchPrices) > 0) {
            Log::info("Branch prices data:", $this->branchPrices);
        } else {
            Log::info("✓ NO BRANCH PRICES TO CREATE - This is correct if no branch-specific prices were provided in the template");
        }
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($this->branchPrices as $index => $branchPriceData) {
            try {
                Log::info("Creating branch price " . ($index + 1) . "/" . count($this->branchPrices), [
                    'item_code' => $branchPriceData['item_code'],
                    'branch_id' => $branchPriceData['branch_id'],
                    'price' => $branchPriceData['price'],
                    'business_id' => $branchPriceData['business_id']
                ]);
                
                $branchPrice = BranchItemPrice::create($branchPriceData);
                $successCount++;
                Log::info("✓ Successfully created branch price for item '{$branchPriceData['item_code']}' at branch {$branchPriceData['branch_id']} with price {$branchPriceData['price']}");
            } catch (\Exception $e) {
                $errorCount++;
                Log::error("✗ Error creating branch price for item '{$branchPriceData['item_code']}': " . $e->getMessage());
            }
        }
        
        Log::info("=== BRANCH PRICES CREATION COMPLETED ===");
        Log::info("Successfully created: {$successCount} branch prices");
        Log::info("Errors encountered: {$errorCount} branch prices");
        Log::info("Branch prices creation completed");
    }

    public function createIncludedItems()
    {
        Log::info("=== CREATING INCLUDED ITEMS (PACKAGE/BULK CONSTITUENTS) ===");
        Log::info("Total pending included items to save: " . count($this->pendingIncludedItems));
        
        if (empty($this->pendingIncludedItems)) {
            Log::warning("No pending included items to process");
            return;
        }
        
        // Group by item to show summary
        $itemGroups = [];
        foreach ($this->pendingIncludedItems as $includedItemData) {
            $itemName = $includedItemData['item_name'];
            if (!isset($itemGroups[$itemName])) {
                $itemGroups[$itemName] = 0;
            }
            $itemGroups[$itemName]++;
        }
        
        Log::info("=== CONSTITUENT ITEMS BREAKDOWN ===");
        foreach ($itemGroups as $itemName => $count) {
            Log::info("  ► {$itemName}: {$count} constituent items");
        }
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($this->pendingIncludedItems as $includedItemData) {
            try {
                    if ($includedItemData['type'] === 'package') {
                    // Only include fields that PackageItem model expects
                    $packageData = [
                        'business_id' => $includedItemData['business_id'],
                        'package_item_id' => $includedItemData['package_item_id'],
                        'included_item_id' => $includedItemData['included_item_id'],
                        'max_quantity' => $includedItemData['max_quantity']
                    ];
                    PackageItem::create($packageData);
                } elseif ($includedItemData['type'] === 'bulk') {
                    // Only include fields that BulkItem model expects
                    $bulkData = [
                        'business_id' => $includedItemData['business_id'],
                        'bulk_item_id' => $includedItemData['bulk_item_id'],
                        'included_item_id' => $includedItemData['included_item_id'],
                        'fixed_quantity' => $includedItemData['fixed_quantity']
                    ];
                    BulkItem::create($bulkData);
                }
                $successCount++;
                Log::info("Successfully created included item for {$includedItemData['type']} item '{$includedItemData['item_name']}'");
            } catch (\Exception $e) {
                $errorCount++;
                Log::error("Error creating included item for {$includedItemData['type']} item '{$includedItemData['item_name']}': " . $e->getMessage());
            }
        }
        
        Log::info("=== INCLUDED ITEMS CREATION COMPLETED ===");
        Log::info("Successfully created: {$successCount} included items");
        Log::info("Errors encountered: {$errorCount} included items");
        
        // Verify and log constituent items count for each package/bulk
        Log::info("=== VERIFICATION: CONSTITUENT ITEMS IN DATABASE ===");
        $packageBulkItems = Item::whereIn('type', ['package', 'bulk'])
            ->where('business_id', $this->businessId)
            ->get();
            
        foreach ($packageBulkItems as $item) {
            if ($item->type === 'package') {
                $constituentCount = $item->packageItems()->count();
                Log::info("✓ {$item->name} (package): {$constituentCount} constituent items in database");
            } elseif ($item->type === 'bulk') {
                $constituentCount = $item->bulkItems()->count();
                Log::info("✓ {$item->name} (bulk): {$constituentCount} constituent items in database");
            }
        }
        
        Log::info("Included items creation completed");
    }

    public function getSuccessCount()
    {
        return $this->successCount;
    }

    public function getErrorCount()
    {
        return $this->errorCount;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    private function generateUniqueCode()
    {
        $prefix = 'ITM';
        $counter = 1;
        
        do {
            $code = $prefix . str_pad($counter, 6, '0', STR_PAD_LEFT);
            $counter++;
        } while (Item::where('code', $code)->exists());
        
        return $code;
    }

    private function normalizeColumnName($name)
    {
        return strtolower(str_replace([' ', '-', '(', ')'], ['_', '_', '', ''], $name));
    }
} 