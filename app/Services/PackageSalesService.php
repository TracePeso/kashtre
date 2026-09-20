<?php

namespace App\Services;

use App\Models\PackageSales;
use App\Support\SharedTime;
use App\Models\PackageTracking;
use App\Models\Transaction;
use App\Models\Business;
use App\Models\Client;
use App\Models\Item;
use App\Services\MoneyTrackingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PackageSalesService
{
    protected $moneyTrackingService;

    public function __construct(MoneyTrackingService $moneyTrackingService)
    {
        $this->moneyTrackingService = $moneyTrackingService;
    }

    /**
     * Process package item sales when items are sold from packages
     * 
     * @param array $soldItems Array of items being sold from packages
     * @param string $invoiceNumber Invoice number for the sale
     * @param int $businessId Business ID
     * @param int $branchId Branch ID
     * @param int $clientId Client ID
     * @return array Results of the package sales processing
     */
    public function processPackageItemSales($soldItems, $invoiceNumber, $businessId, $branchId, $clientId)
    {
        Log::info('=== STARTING PACKAGE ITEM SALES PROCESSING ===', [
            'invoice_number' => $invoiceNumber,
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'client_id' => $clientId,
            'sold_items_count' => count($soldItems)
        ]);

        $results = [];
        $totalPackageAmount = 0;

        DB::beginTransaction();
        
        try {
            foreach ($soldItems as $soldItem) {
                $itemId = $soldItem['item_id'] ?? null;
                $quantity = $soldItem['quantity'] ?? 1;
                $amount = $soldItem['amount'] ?? 0;

                if (!$itemId) {
                    Log::warning('Item ID not provided for package sale', ['sold_item' => $soldItem]);
                    continue;
                }

                // Find package tracking items for this item using the new structure
                $packageTrackingItems = \App\Models\PackageTrackingItem::where('included_item_id', $itemId)
                    ->whereHas('packageTracking', function($q) use ($clientId) {
                        $q->where('client_id', $clientId)
                          ->where('status', 'active');
                    })
                    ->where('remaining_quantity', '>', 0)
                    ->with('packageTracking')
                    ->orderBy('created_at', 'asc') // Use oldest packages first (FIFO)
                    ->get();

                if ($packageTrackingItems->isEmpty()) {
                    Log::warning('No active package tracking items found for item', [
                        'item_id' => $itemId,
                        'client_id' => $clientId
                    ]);
                    continue;
                }

                $remainingQuantity = $quantity;
                $itemTotalAmount = 0;

                foreach ($packageTrackingItems as $trackingItem) {
                    if ($remainingQuantity <= 0) break;

                    $availableQuantity = $trackingItem->remaining_quantity;
                    $quantityToUse = min($remainingQuantity, $availableQuantity);
                    
                    if ($quantityToUse <= 0) continue;

                    Log::info('Processing package item sale', [
                        'package_tracking_id' => $trackingItem->package_tracking_id,
                        'item_id' => $itemId,
                        'quantity_to_use' => $quantityToUse,
                        'available_quantity' => $availableQuantity,
                        'remaining_quantity' => $remainingQuantity
                    ]);

                    // Calculate amount for this portion of the sale
                    $itemPrice = $trackingItem->item_price ?? 0;
                    $portionAmount = $itemPrice * $quantityToUse;
                    $itemTotalAmount += $portionAmount;

                    // Create package sales record
                    $packageSale = PackageSales::create([
                        'name' => $trackingItem->packageTracking->client->name ?? 'Unknown Client',
                        'invoice_number' => $invoiceNumber,
                        'pkn' => $trackingItem->packageTracking->tracking_number ?? "PKG-{$trackingItem->package_tracking_id}-{$trackingItem->created_at->format('YmdHis')}",
                        'date' => SharedTime::businessToday(),
                        'qty' => $quantityToUse,
                        'item_name' => $trackingItem->includedItem->name ?? 'Unknown Item',
                        'amount' => $portionAmount,
                        'business_id' => $businessId,
                        'branch_id' => $branchId,
                        'client_id' => $clientId,
                        'package_tracking_id' => $trackingItem->package_tracking_id,
                        'item_id' => $itemId,
                        'status' => 'completed',
                        'notes' => "Package item sale from tracking #{$trackingItem->package_tracking_id}"
                    ]);

                    // Update package tracking item quantities
                    $trackingItem->useQuantity($quantityToUse);

                    Log::info('Package tracking item updated', [
                        'tracking_item_id' => $trackingItem->id,
                        'package_tracking_id' => $trackingItem->package_tracking_id,
                        'new_used_quantity' => $trackingItem->used_quantity,
                        'new_remaining_quantity' => $trackingItem->remaining_quantity
                    ]);

                    $remainingQuantity -= $quantityToUse;
                    $totalPackageAmount += $portionAmount;

                    $results[] = [
                        'package_sale_id' => $packageSale->id,
                        'package_tracking_id' => $trackingItem->package_tracking_id,
                        'tracking_item_id' => $trackingItem->id,
                        'quantity_used' => $quantityToUse,
                        'amount' => $portionAmount,
                        'pkn' => $packageSale->pkn
                    ];
                }

                if ($remainingQuantity > 0) {
                    Log::warning('Not enough package quantity available for item', [
                        'item_id' => $itemId,
                        'requested_quantity' => $quantity,
                        'remaining_unfulfilled' => $remainingQuantity
                    ]);
                }
            }

            // Transfer money to business account (Step 4a)
            if ($totalPackageAmount > 0) {
                $this->transferMoneyToBusinessAccount($totalPackageAmount, $businessId, $branchId, $clientId, $invoiceNumber);
            }

            // Create client account statement entry (Step 4e)
            $this->createClientAccountStatementEntry($totalPackageAmount, $clientId, $invoiceNumber, $results);

            // Create business statement entry (Step 4f)
            $this->createBusinessStatementEntry($totalPackageAmount, $businessId, $branchId, $invoiceNumber, $results);

            DB::commit();

            Log::info('=== PACKAGE ITEM SALES PROCESSING COMPLETED ===', [
                'total_package_amount' => $totalPackageAmount,
                'package_sales_created' => count($results),
                'invoice_number' => $invoiceNumber
            ]);

            return [
                'success' => true,
                'total_amount' => $totalPackageAmount,
                'package_sales' => $results,
                'message' => 'Package item sales processed successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Package item sales processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'invoice_number' => $invoiceNumber
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to process package item sales'
            ];
        }
    }

    /**
     * Transfer money to business account (Step 4a)
     */
    private function transferMoneyToBusinessAccount($amount, $businessId, $branchId, $clientId, $invoiceNumber)
    {
        Log::info('Transferring money to business account for package sales', [
            'amount' => $amount,
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'client_id' => $clientId,
            'invoice_number' => $invoiceNumber
        ]);

        try {
            // Create transaction record for business account
            Transaction::create([
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'client_id' => $clientId,
                'amount' => $amount,
                'reference' => 'PKG-' . time() . '-' . $invoiceNumber,
                'description' => "Package item sales from invoice {$invoiceNumber}",
                'status' => 'completed',
                'payment_status' => 'Paid', // Package sales are always paid
                'type' => 'package_sale',
                'origin' => 'package_sales',
                'date' => SharedTime::businessToday(),
                'currency' => 'UGX',
                'method' => 'package_transfer'
            ]);

            // Update business account balance
            $business = Business::find($businessId);
            if ($business) {
                $business->increment('account_balance', $amount);
                
                Log::info('Business account balance updated', [
                    'business_id' => $businessId,
                    'amount_added' => $amount,
                    'new_balance' => $business->account_balance
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to transfer money to business account', [
                'error' => $e->getMessage(),
                'amount' => $amount,
                'business_id' => $businessId
            ]);
            throw $e;
        }
    }

    /**
     * Create client account statement entry (Step 4e)
     */
    private function createClientAccountStatementEntry($amount, $clientId, $invoiceNumber, $packageSales)
    {
        Log::info('Creating client account statement entry for package sales', [
            'amount' => $amount,
            'client_id' => $clientId,
            'invoice_number' => $invoiceNumber,
            'package_sales_count' => count($packageSales)
        ]);

        try {
            // Create transaction record for client statement (type: package)
            Transaction::create([
                'business_id' => null, // Not business-specific
                'branch_id' => null,   // Not branch-specific
                'client_id' => $clientId,
                'amount' => $amount,
                'reference' => 'CLIENT-PKG-' . time() . '-' . $invoiceNumber,
                'description' => "Package usage from invoice {$invoiceNumber}",
                'status' => 'completed',
                'payment_status' => 'Paid', // Package usage transactions are always paid
                'type' => 'package', // Special type for package transactions
                'origin' => 'package_usage',
                'date' => SharedTime::businessToday(),
                'currency' => 'UGX',
                'method' => 'package_usage',
                'transaction_for' => 'client_statement' // Mark for client statement
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create client account statement entry', [
                'error' => $e->getMessage(),
                'client_id' => $clientId,
                'amount' => $amount
            ]);
            throw $e;
        }
    }

    /**
     * Create business statement entry (Step 4f)
     */
    private function createBusinessStatementEntry($amount, $businessId, $branchId, $invoiceNumber, $packageSales)
    {
        Log::info('Creating business statement entry for package sales', [
            'amount' => $amount,
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'invoice_number' => $invoiceNumber,
            'package_sales_count' => count($packageSales)
        ]);

        try {
            // Create transaction record for business statement (type: package)
            Transaction::create([
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'client_id' => null, // Not client-specific for business statement
                'amount' => $amount,
                'reference' => 'BIZ-PKG-' . time() . '-' . $invoiceNumber,
                'description' => "Package sales revenue from invoice {$invoiceNumber}",
                'status' => 'completed',
                'payment_status' => 'Paid', // Package sales are always paid
                'type' => 'package', // Special type for package transactions
                'origin' => 'package_sales',
                'date' => SharedTime::businessToday(),
                'currency' => 'UGX',
                'method' => 'package_sales',
                'transaction_for' => 'business_statement' // Mark for business statement
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create business statement entry', [
                'error' => $e->getMessage(),
                'business_id' => $businessId,
                'amount' => $amount
            ]);
            throw $e;
        }
    }

    /**
     * Get package sales for a specific client
     */
    public function getClientPackageSales($clientId, $startDate = null, $endDate = null)
    {
        $query = PackageSales::forClient($clientId)->completed();

        if ($startDate && $endDate) {
            $query->forDateRange($startDate, $endDate);
        }

        return $query->with(['packageTracking', 'item'])->orderBy('date', 'desc')->get();
    }

    /**
     * Get package sales for a specific business
     */
    public function getBusinessPackageSales($businessId, $startDate = null, $endDate = null)
    {
        $query = PackageSales::forBusiness($businessId)->completed();

        if ($startDate && $endDate) {
            $query->forDateRange($startDate, $endDate);
        }

        return $query->with(['packageTracking', 'item', 'client'])->orderBy('date', 'desc')->get();
    }
}
