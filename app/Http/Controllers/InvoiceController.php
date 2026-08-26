<?php

namespace App\Http\Controllers;

// Package functionality with comprehensive logging - Updated for testing
// Version: 2025-09-20-20:30 - Fresh deployment with package transaction type support

use App\Models\BalanceHistory;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\ServiceCharge;
use App\Services\InsuranceClientPortionThirdPartyNotifier;
use App\Services\MoneyTrackingService;
use App\Support\YoDepositGate;
use App\Support\YoExternalReference;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
    protected $currentInvoice;

    /**
     * Calculate package adjustment for a client
     */
    public function calculatePackageAdjustment(Request $request)
    {
        try {
            $validated = $request->validate([
                'client_id' => 'required|exists:clients,id',
                'business_id' => 'required|exists:businesses,id',
                'items' => 'required|array',
                'branch_id' => 'required|exists:branches,id',
            ]);

            $clientId = $validated['client_id'];
            $businessId = $validated['business_id'];
            $branchId = $validated['branch_id'];
            $items = $validated['items'];

            Log::info('=== PACKAGE ADJUSTMENT CALCULATION STARTED ===', [
                'client_id' => $clientId,
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'items_count' => count($items),
                'items' => $items,
            ]);

            // Use the new PackageTrackingService for package adjustment calculation
            $packageTrackingService = new \App\Services\PackageTrackingService();

            // Create a mock invoice object for the service
            $mockInvoice = new \App\Models\Invoice();
            $mockInvoice->client_id = $clientId;
            $mockInvoice->business_id = $businessId;

            $result = $packageTrackingService->calculatePackageAdjustment($mockInvoice, $items);
            $totalAdjustment = $result['total_adjustment'];
            $adjustmentDetails = $result['details'];
            $maxQtyWarnings = $result['max_qty_warnings'] ?? [];

            // Package adjustment calculation is now handled by the PackageTrackingService

            Log::info('=== PACKAGE ADJUSTMENT CALCULATION COMPLETED ===', [
                'client_id' => $clientId,
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'total_adjustment' => $totalAdjustment,
                'adjustment_details_count' => count($adjustmentDetails),
                'adjustment_details' => $adjustmentDetails,
                'max_qty_warnings_count' => count($maxQtyWarnings),
            ]);

            return response()->json([
                'success' => true,
                'total_adjustment' => $totalAdjustment,
                'details' => $adjustmentDetails,
                'max_qty_warnings' => $maxQtyWarnings,
                'message' => $totalAdjustment > 0 ? 'Package adjustments applied' : 'No valid package adjustments found',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error calculating package adjustment: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update package tracking records when package adjustments are used
     */
    private function updatePackageTrackingForAdjustments($invoice, $items)
    {
        try {
            $clientId = $invoice->client_id;
            $businessId = $invoice->business_id;
            $branchId = $invoice->branch_id;

            Log::info('=== PACKAGE TRACKING UPDATE STARTED ===', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $clientId,
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'package_adjustment' => $invoice->package_adjustment,
                'items_count' => count($items),
            ]);

            // Get client's valid package tracking records
            $validPackages = \App\Models\PackageTracking::where('client_id', $clientId)
                ->where('business_id', $businessId)
                ->where('status', 'active')
                ->where('remaining_quantity', '>', 0)
                ->where('valid_until', '>=', now()->toDateString())
                ->with(['packageItem.packageItems.includedItem'])
                ->get();

            Log::info('Found valid packages for client', [
                'client_id' => $clientId,
                'valid_packages_count' => $validPackages->count(),
                'package_details' => $validPackages->map(function ($pkg) {
                    return [
                        'id' => $pkg->id,
                        'package_name' => $pkg->packageItem->name,
                        'remaining_quantity' => $pkg->remaining_quantity,
                        'used_quantity' => $pkg->used_quantity,
                        'status' => $pkg->status,
                        'valid_until' => $pkg->valid_until,
                    ];
                }),
            ]);

            foreach ($items as $item) {
                $itemId = $item['id'] ?? $item['item_id'];
                $quantity = $item['quantity'] ?? 1;
                $remainingQuantity = $quantity;

                Log::info('Processing item for package tracking update', [
                    'item_id' => $itemId,
                    'item_name' => $item['name'] ?? 'Unknown',
                    'quantity' => $quantity,
                    'remaining_quantity' => $remainingQuantity,
                ]);

                // Get the item price (branch-specific or default)
                $itemModel = \App\Models\Item::find($itemId);
                $price = $itemModel ? $itemModel->default_price : 0;

                // Check for branch-specific price
                $branchPrice = \App\Models\BranchItemPrice::where('branch_id', $branchId)
                    ->where('item_id', $itemId)
                    ->first();

                if ($branchPrice) {
                    $price = $branchPrice->price;
                    Log::info('Using branch-specific price for item', [
                        'item_id' => $itemId,
                        'branch_id' => $branchId,
                        'branch_price' => $price,
                        'default_price' => $itemModel->default_price,
                    ]);
                } else {
                    Log::info('Using default price for item', [
                        'item_id' => $itemId,
                        'default_price' => $price,
                    ]);
                }

                // Check if this item is included in any valid packages
                foreach ($validPackages as $packageTracking) {
                    if ($remainingQuantity <= 0) {
                        break;
                    }

                    Log::info('Checking package for item match', [
                        'package_tracking_id' => $packageTracking->id,
                        'package_name' => $packageTracking->packageItem->name,
                        'item_id' => $itemId,
                        'package_remaining_quantity' => $packageTracking->remaining_quantity,
                    ]);

                    // Check if the current item is included in this package
                    $packageItems = $packageTracking->packageItem->packageItems;

                    foreach ($packageItems as $packageItem) {
                        if ($packageItem->included_item_id == $itemId) {
                            Log::info('Item found in package', [
                                'package_tracking_id' => $packageTracking->id,
                                'package_item_id' => $packageItem->id,
                                'included_item_id' => $packageItem->included_item_id,
                                'max_quantity' => $packageItem->max_quantity,
                                'fixed_quantity' => $packageItem->fixed_quantity,
                            ]);

                            // Check max quantity constraint from package_items table
                            $maxQuantity = $packageItem->max_quantity ?? null;
                            $fixedQuantity = $packageItem->fixed_quantity ?? null;

                            // Determine how much quantity can be used from this package
                            $availableFromPackage = $packageTracking->remaining_quantity;

                            if ($maxQuantity !== null) {
                                // If max_quantity is set, limit by that
                                $availableFromPackage = min($availableFromPackage, $maxQuantity);
                                Log::info('Limited by max_quantity constraint', [
                                    'max_quantity' => $maxQuantity,
                                    'available_from_package' => $availableFromPackage,
                                ]);
                            } elseif ($fixedQuantity !== null) {
                                // If fixed_quantity is set, use that
                                $availableFromPackage = min($availableFromPackage, $fixedQuantity);
                                Log::info('Limited by fixed_quantity constraint', [
                                    'fixed_quantity' => $fixedQuantity,
                                    'available_from_package' => $availableFromPackage,
                                ]);
                            }

                            // Calculate how much we can actually use
                            $quantityToUse = min($remainingQuantity, $availableFromPackage);

                            Log::info('Calculated quantity to use from package', [
                                'remaining_quantity_needed' => $remainingQuantity,
                                'available_from_package' => $availableFromPackage,
                                'quantity_to_use' => $quantityToUse,
                            ]);

                            if ($quantityToUse > 0) {
                                // Store old values for logging
                                $oldUsedQuantity = $packageTracking->used_quantity;
                                $oldRemainingQuantity = $packageTracking->remaining_quantity;
                                $oldStatus = $packageTracking->status;

                                // Update package tracking record
                                $packageTracking->used_quantity += $quantityToUse;
                                $packageTracking->remaining_quantity -= $quantityToUse;

                                // Mark as expired if no remaining quantity
                                if ($packageTracking->remaining_quantity <= 0) {
                                    $packageTracking->status = 'expired';
                                }

                                $packageTracking->save();

                                Log::info('Successfully updated package tracking for adjustment', [
                                    'package_tracking_id' => $packageTracking->id,
                                    'package_name' => $packageTracking->packageItem->name,
                                    'item_name' => $item['name'] ?? 'Unknown',
                                    'quantity_used' => $quantityToUse,
                                    'old_used_quantity' => $oldUsedQuantity,
                                    'new_used_quantity' => $packageTracking->used_quantity,
                                    'old_remaining_quantity' => $oldRemainingQuantity,
                                    'new_remaining_quantity' => $packageTracking->remaining_quantity,
                                    'old_status' => $oldStatus,
                                    'new_status' => $packageTracking->status,
                                ]);

                                $remainingQuantity -= $quantityToUse;

                                // If we've used all available quantity from this package, break
                                if ($remainingQuantity <= 0) {
                                    break;
                                }
                            } else {
                                Log::info('No quantity to use from package', [
                                    'package_tracking_id' => $packageTracking->id,
                                    'reason' => 'quantity_to_use is 0',
                                ]);
                            }
                        }
                    }
                }

                Log::info('Finished processing item for package tracking', [
                    'item_id' => $itemId,
                    'original_quantity' => $quantity,
                    'remaining_quantity_after_package_usage' => $remainingQuantity,
                ]);
            }

            Log::info('=== PACKAGE TRACKING UPDATE COMPLETED ===', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total_items_processed' => count($items),
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating package tracking for adjustments', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number ?? 'Unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Store a new invoice
     */
    public function store(Request $request)
    {
        try {
            Log::info('=== INVOICE CREATION STARTED ===', [
                'user_id' => Auth::id(),
                'business_id' => $request->input('business_id'),
                'client_id' => $request->input('client_id'),
                'total_amount' => $request->input('total_amount'),
                'payment_status' => $request->input('payment_status'),
                'status' => $request->input('status'),
            ]);

            DB::beginTransaction();

            $user = Auth::user();
            $business = $user->business;
            $moneyTrackingService = new MoneyTrackingService();

            // Validate request
            $validated = $request->validate([
                'invoice_number' => 'nullable|string|max:64',
                'client_id' => 'required|exists:clients,id',
                'business_id' => 'required|exists:businesses,id',
                'branch_id' => 'required|exists:branches,id',
                'created_by' => 'required|exists:users,id',
                'client_name' => 'required|string',
                'client_phone' => 'required|string',
                'payment_phone' => 'nullable|string',
                'visit_id' => 'nullable|string',
                'items' => 'required|array',
                'subtotal' => 'required|numeric|min:0',
                'package_adjustment' => 'nullable|numeric',
                'account_balance_adjustment' => 'nullable|numeric',
                'service_charge' => 'required|numeric|min:0',
                'total_amount' => 'required|numeric|min:0',
                'amount_paid' => 'nullable|numeric|min:0',
                'balance_due' => 'required|numeric|min:0',
                'payment_methods' => 'nullable|array',
                'payment_status' => 'required|in:pending,pending_payment,partial,paid,cancelled',
                'status' => 'required|in:draft,confirmed,printed,cancelled',
                'notes' => 'nullable|string',
                'third_party_payer_id' => 'nullable|exists:third_party_payers,id',
                'deductible_remaining' => 'nullable|numeric|min:0',
                'fulfillment_strategy' => 'nullable|in:DISCRETE_IMMEDIATE,BATCH_AND_STAGE',
                'end_store_id' => 'nullable|exists:stores,id',
            ]);

            // Get client
            $client = Client::find($validated['client_id']);

            // Get business
            $business = \App\Models\Business::find($validated['business_id']);
            $invoiceCurrency = strtoupper($business->currency_code ?? 'USD');

            // Check for third-party payer exclusions (when payment method is insurance)
            $paymentMethods = $validated['payment_methods'] ?? [];
            $isThirdPartyPayer = in_array('insurance', $paymentMethods);

            if ($isThirdPartyPayer) {
                // Get business-level exclusions
                $businessExcludedItems = $business->third_party_excluded_items ?? [];

                // Get individual third-party payer exclusions for this insurer+business
                $thirdPartyPayer = \App\Models\ThirdPartyPayer::where('business_id', $business->id)
                    ->where('insurance_company_id', $client->insurance_company_id)
                    ->where('type', 'insurance_company')
                    ->where('status', 'active')
                    ->first();

                $payerExcludedItems = $thirdPartyPayer ? ($thirdPartyPayer->excluded_items ?? []) : [];

                // Merge business and individual exclusions
                $excludedItems = array_unique(array_merge($businessExcludedItems, $payerExcludedItems));

                // Instead of blocking the invoice, mark these items as non-insurable so the
                // third-party authorization treats them as client-only (excluded) amounts.
                if (! empty($excludedItems)) {
                    $itemsArray = $validated['items'];

                    foreach ($itemsArray as $index => $item) {
                        $itemId = $item['id'] ?? $item['item_id'] ?? null;
                        if ($itemId && in_array($itemId, $excludedItems)) {
                            $itemsArray[$index]['kashtre_excluded'] = true;
                        }
                    }

                    $validated['items'] = $itemsArray;
                }
            }

            // Check for credit client exclusions (when client is credit-eligible and using credit)
            $isCreditClient = $client->is_credit_eligible;
            $isUsingCredit = $validated['balance_due'] > 0;

            if ($isCreditClient && $isUsingCredit) {
                // Get business-level exclusions
                $businessExcludedItems = $business->credit_excluded_items ?? [];

                // Get individual client exclusions
                $clientExcludedItems = $client->excluded_items ?? [];

                // Merge business and individual exclusions
                $excludedItems = array_unique(array_merge($businessExcludedItems, $clientExcludedItems));

                if (! empty($excludedItems)) {
                    $itemsArray = $validated['items'];
                    $excludedItemIds = [];

                    foreach ($itemsArray as $item) {
                        $itemId = $item['id'] ?? $item['item_id'] ?? null;
                        if ($itemId && in_array($itemId, $excludedItems)) {
                            $excludedItemIds[] = $itemId;
                        }
                    }

                    if (! empty($excludedItemIds)) {
                        $excludedItemNames = \App\Models\Item::whereIn('id', $excludedItemIds)
                            ->pluck('name')
                            ->toArray();

                        return response()->json([
                            'success' => false,
                            'message' => 'The following items are excluded from credit terms: '.implode(', ', $excludedItemNames).'. Please remove these items or process payment upfront.',
                            'errors' => [
                                'excluded_items' => [
                                    'The following items cannot be offered on credit: '.implode(', ', $excludedItemNames).'. Please remove these items from the invoice or process payment upfront.',
                                ],
                            ],
                            'excluded_items' => $excludedItemNames,
                            'excluded_item_ids' => $excludedItemIds,
                        ], 422);
                    }
                }
            }

            // Check credit limit for credit-eligible clients
            if ($client->is_credit_eligible) {

                // Calculate current outstanding balance from accounts receivable
                $currentOutstanding = \App\Models\AccountsReceivable::where('client_id', $client->id)
                    ->where('status', '!=', 'paid')
                    ->sum('balance');

                // Calculate new balance if this invoice is created
                $newOutstanding = $currentOutstanding + $validated['balance_due'];

                // Check if exceeds credit limit
                if ($client->max_credit && $newOutstanding > $client->max_credit) {
                    $availableCredit = $client->max_credit - $currentOutstanding;

                    return response()->json([
                        'success' => false,
                        'message' => 'Credit limit exceeded. Available credit: UGX '.number_format($availableCredit, 2).'. Requested amount: UGX '.number_format($validated['balance_due'], 2).'.',
                        'errors' => [
                            'credit_limit' => [
                                "This order would exceed the client's credit limit of UGX ".number_format($client->max_credit, 2).'. Current outstanding: UGX '.number_format($currentOutstanding, 2).'. Available credit: UGX '.number_format($availableCredit, 2).'.',
                            ],
                        ],
                        'credit_info' => [
                            'max_credit' => $client->max_credit,
                            'current_outstanding' => $currentOutstanding,
                            'available_credit' => $availableCredit,
                            'requested_amount' => $validated['balance_due'],
                        ],
                    ], 422);
                }
            }

            // Generate visit_id if client doesn't have one (only when creating invoice/transaction)
            // This is the only place where we regenerate visit_id after it's been cleared/expired by cron
            $needsNewVisitId = empty($client->visit_id)
                || empty($client->visit_expires_at)
                || Carbon::parse($client->visit_expires_at)->isPast();

            if ($needsNewVisitId) {
                $client->issueNewVisitId();
                $validated['visit_id'] = $client->visit_id;

                Log::info('Generated new visit_id for client during invoice creation', [
                    'client_id' => $client->id,
                    'visit_id' => $client->visit_id,
                    'visit_expires_at' => $client->visit_expires_at,
                    'reason' => empty($client->visit_id) ? 'no_visit_id' : 'expired',
                ]);
            } else {
                // Use existing visit_id even if not sent from frontend
                $validated['visit_id'] = $client->visit_id;
            }

            // Normalize items for further processing
            $itemsCollection = collect($validated['items'])->map(function (array $item) {
                if (! isset($item['total_amount'])) {
                    $quantity = (float) ($item['quantity'] ?? 1);
                    $price = (float) ($item['price'] ?? 0);
                    $item['total_amount'] = $price * $quantity;
                }

                return $item;
            });

            $isDepositItem = function (array $item): bool {
                $name = Str::lower(trim((string) ($item['displayName'] ?? $item['name'] ?? $item['item_name'] ?? '')));

                return $name === 'deposit';
            };

            $depositItems = $itemsCollection->filter($isDepositItem);
            $nonDepositItems = $itemsCollection->reject($isDepositItem)->values();
            $isDepositOnlyInvoice = $depositItems->isNotEmpty() && $nonDepositItems->isEmpty();
            $depositTotalAmount = (float) round($depositItems->sum(function (array $item) {
                return (float) ($item['total_amount'] ?? 0);
            }), 2);

            if ($isDepositOnlyInvoice) {
                Log::info('Deposit-only invoice detected', [
                    'client_id' => $client->id,
                    'deposit_total_amount' => $depositTotalAmount,
                    'items_count' => $itemsCollection->count(),
                ]);
            }

            if ($depositItems->isNotEmpty() && $nonDepositItems->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Deposits must be the only item on the invoice.',
                    'errors' => ['items' => ['Deposits cannot be combined with other items on the same invoice.']],
                ], 422);
            }

            if ($depositItems->isNotEmpty() && $validated['service_charge'] <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'A service charge is required for deposit invoices.',
                    'errors' => ['service_charge' => ['Service charge must be configured for deposit invoices.']],
                ], 422);
            }

            // Validate service charge for non-package, non-deposit invoices
            if (
                ! $isDepositOnlyInvoice &&
                ($validated['package_adjustment'] ?? 0) <= 0 &&
                ($validated['total_amount'] ?? 0) > 0
            ) {
                if ($validated['service_charge'] <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Service charge not configured. Please contact support.',
                        'errors' => ['service_charge' => ['Service charge not configured. Please contact support.']],
                    ], 422);
                }
            }

            // Proforma number: the POS shows a "next number" preview; it can be stale after a prior save (duplicate).
            // Always assign a free number server-side so repeat submissions never fail validation.
            $requestedNumber = isset($validated['invoice_number']) ? trim((string) $validated['invoice_number']) : '';
            if ($requestedNumber !== '' && ! Invoice::where('invoice_number', $requestedNumber)->exists()) {
                $invoiceNumber = $requestedNumber;
            } else {
                if ($requestedNumber !== '') {
                    Log::info('[Kashtre] Invoice create: generating new proforma number (preview empty, duplicate, or race)', [
                        'requested' => $requestedNumber,
                        'business_id' => $business->id,
                    ]);
                }
                $invoiceNumber = Invoice::generateInvoiceNumber($business->id, 'proforma');
            }

            // Check if this is a credit client BEFORE creating invoice
            // Refresh client to ensure we have the latest is_credit_eligible status
            $client->refresh();
            $isCreditClient = (bool) $client->is_credit_eligible;

            // For credit clients: balance_due should always be total_amount (they owe the full amount)
            // Even if they pay upfront, the invoice balance_due should reflect the debt
            $finalBalanceDue = $validated['balance_due'];
            $finalPaymentStatus = $validated['payment_status'];

            if ($isCreditClient) {
                // Credit clients always owe the full amount (debit entries are created)
                $finalBalanceDue = $validated['total_amount'];
                // Set payment_status to pending_payment if there's any amount due
                if ($finalBalanceDue > 0) {
                    $finalPaymentStatus = 'pending_payment';
                }
                Log::info('Credit client invoice - adjusting balance_due and payment_status', [
                    'client_id' => $client->id,
                    'original_balance_due' => $validated['balance_due'],
                    'adjusted_balance_due' => $finalBalanceDue,
                    'original_payment_status' => $validated['payment_status'],
                    'adjusted_payment_status' => $finalPaymentStatus,
                    'total_amount' => $validated['total_amount'],
                    'amount_paid' => $validated['amount_paid'] ?? 0,
                ]);
            }

            // Create invoice (retry on rare duplicate key if two requests race the same second)
            $invoicePayload = [
                'client_id' => $validated['client_id'],
                'business_id' => $validated['business_id'],
                'branch_id' => $validated['branch_id'],
                'client_space_id' => $validated['client_space_id'] ?? null,
                'fulfillment_strategy' => $validated['fulfillment_strategy'] ?? null,
                'end_store_id' => $validated['end_store_id'] ?? null,
                'created_by' => $validated['created_by'],
                'currency' => $invoiceCurrency,
                'client_name' => $validated['client_name'],
                'client_phone' => $validated['client_phone'],
                'payment_phone' => $validated['payment_phone'],
                'visit_id' => $validated['visit_id'],
                'items' => $itemsCollection->toArray(),
                'subtotal' => $validated['subtotal'],
                'package_adjustment' => $validated['package_adjustment'] ?? 0,
                'account_balance_adjustment' => $validated['account_balance_adjustment'] ?? 0,
                'service_charge' => $validated['service_charge'],
                'total_amount' => $validated['total_amount'],
                'amount_paid' => $validated['amount_paid'] ?? 0,
                'balance_due' => $finalBalanceDue, // Use adjusted balance_due for credit clients
                'payment_methods' => $validated['payment_methods'] ?? [],
                'payment_status' => $finalPaymentStatus, // Use adjusted payment_status for credit clients
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? '',
                'confirmed_at' => $validated['status'] === 'confirmed' ? now() : null,
            ];

            $invoice = null;
            for ($createAttempt = 0; $createAttempt < 12; $createAttempt++) {
                try {
                    $invoice = Invoice::create(array_merge($invoicePayload, [
                        'invoice_number' => $invoiceNumber,
                    ]));
                    break;
                } catch (QueryException $e) {
                    $sqlState = $e->errorInfo[0] ?? '';
                    $driverCode = (int) ($e->errorInfo[1] ?? 0);
                    $isDuplicate = ($sqlState === '23000' || $driverCode === 1062)
                        || str_contains($e->getMessage(), 'Duplicate entry')
                        || str_contains($e->getMessage(), '1062');
                    if (! $isDuplicate || $createAttempt >= 11) {
                        throw $e;
                    }
                    Log::warning('[Kashtre] Invoice create: duplicate invoice_number on insert, retrying', [
                        'attempt' => $createAttempt + 1,
                        'invoice_number' => $invoiceNumber,
                        'business_id' => $business->id,
                    ]);
                    $invoiceNumber = Invoice::generateInvoiceNumber($business->id, 'proforma');
                }
            }

            if (! $invoice) {
                throw new \RuntimeException('Could not allocate a unique invoice number after repeated attempts.');
            }

            // SRD A-02 — capture demand intent as soon as invoice items exist (before payment / stock fulfillment).
            try {
                app(\App\Services\Inventory\InventoryDemandLedgerService::class)
                    ->recordFromInvoice($invoice, $itemsCollection->toArray());
            } catch (\Throwable $e) {
                Log::warning('Inventory demand ledger early capture failed', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }

            Log::info('Invoice created successfully', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $invoice->client_id,
                'business_id' => $invoice->business_id,
                'total_amount' => $invoice->total_amount,
                'payment_status' => $invoice->payment_status,
                'status' => $invoice->status,
                'items_count' => count($invoice->items ?? []),
            ]);

            // Store current invoice for balance statement
            $this->currentInvoice = $invoice;

            Log::info('=== STEP 1: CLIENT DATA CHECK ===', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $client->id,
                'client_name' => $client->name,
                'client_is_credit_eligible_before_refresh' => $client->is_credit_eligible,
                'client_balance_before_refresh' => $client->balance,
            ]);

            // Refresh client from database to ensure we have latest data
            $client->refresh();

            // For credit clients, ensure visit_id has /C suffix
            if ($client->is_credit_eligible) {
                $hasCorrectVisitIdFormat = str_ends_with($client->visit_id ?? '', '/C') || str_ends_with($client->visit_id ?? '', '/C/M');

                if (! $hasCorrectVisitIdFormat) {
                    Log::warning('Credit client visit_id missing /C suffix - regenerating', [
                        'invoice_id' => $invoice->id,
                        'client_id' => $client->id,
                        'current_visit_id' => $client->visit_id,
                        'is_credit_eligible' => $client->is_credit_eligible,
                        'is_long_stay' => $client->is_long_stay ?? false,
                    ]);

                    // Regenerate visit_id with correct format
                    $client->issueNewVisitId();
                    $client->refresh();

                    Log::info('Visit ID regenerated for credit client', [
                        'invoice_id' => $invoice->id,
                        'client_id' => $client->id,
                        'new_visit_id' => $client->visit_id,
                    ]);
                }
            }

            Log::info('=== STEP 2: CLIENT REFRESHED FROM DATABASE ===', [
                'invoice_id' => $invoice->id,
                'client_id' => $client->id,
                'client_is_credit_eligible_after_refresh' => $client->is_credit_eligible,
                'client_balance_after_refresh' => $client->balance,
                'client_max_credit' => $client->max_credit,
                'client_visit_id' => $client->visit_id,
                'visit_id_has_credit_suffix' => str_ends_with($client->visit_id ?? '', '/C') || str_ends_with($client->visit_id ?? '', '/C/M'),
            ]);

            // Check if this is a credit client
            // For credit clients, ALWAYS treat as credit transaction (even if they pay upfront)
            // Items should be queued immediately and debits created
            $isCreditClient = (bool) $client->is_credit_eligible;
            $hasBalanceDue = $invoice->balance_due > 0;
            // For credit clients: always treat as credit transaction, regardless of payment
            // This ensures items are queued immediately and debits are created
            $isCreditTransaction = $isCreditClient;

            Log::info('=== STEP 3: CREDIT TRANSACTION DECISION ===', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $client->id,
                'client_name' => $client->name,
                'is_credit_eligible' => $isCreditClient,
                'is_credit_eligible_raw' => $client->is_credit_eligible,
                'is_credit_eligible_type' => gettype($client->is_credit_eligible),
                'balance_due' => $invoice->balance_due,
                'balance_due_type' => gettype($invoice->balance_due),
                'has_balance_due' => $hasBalanceDue,
                'total_amount' => $invoice->total_amount,
                'amount_paid' => $validated['amount_paid'] ?? 0,
                'is_credit_transaction' => $isCreditTransaction,
                'non_deposit_items_count' => $nonDepositItems->count(),
                'non_deposit_items' => $nonDepositItems->toArray(),
                'all_items_count' => count($invoice->items ?? []),
            ]);

            // For credit clients: Create accounts receivable and update client balance
            // NO suspense account movement - money stays in accounts receivable
            // This applies even if they pay upfront - items are still offered on credit
            // BUT: Skip client BalanceHistory if insurance is selected (insurance payments debit third-party payer, not client)
            $paymentMethods = $validated['payment_methods'] ?? [];
            $isInsurancePayment = in_array('insurance', $paymentMethods);

            if ($isCreditTransaction && ! $isInsurancePayment) {
                Log::info('=== STEP 4: CREDIT TRANSACTION DETECTED - STARTING PROCESSING ===', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                ]);

                // For credit clients, payment_status should already be 'pending_payment' (set during invoice creation)
                // But verify and update if needed
                if ($invoice->payment_status !== 'pending_payment' && $invoice->balance_due > 0) {
                    $oldPaymentStatus = $invoice->payment_status;
                    $invoice->update(['payment_status' => 'pending_payment']);
                    $invoice->refresh();
                    Log::info('=== STEP 5: INVOICE PAYMENT STATUS UPDATED ===', [
                        'invoice_id' => $invoice->id,
                        'old_payment_status' => $oldPaymentStatus,
                        'new_payment_status' => $invoice->payment_status,
                    ]);
                } else {
                    Log::info('=== STEP 5: CREDIT CLIENT INVOICE STATUS ===', [
                        'invoice_id' => $invoice->id,
                        'payment_status' => $invoice->payment_status,
                        'balance_due' => $invoice->balance_due,
                        'note' => 'Credit client invoice - balance_due reflects full debt amount',
                    ]);
                }

                // Create accounts receivable entry
                // For credit clients: Even if they pay upfront, they still owe the full amount
                // amount_due = total_amount, amount_paid = 0, balance = total_amount
                Log::info('=== STEP 6: CREATING ACCOUNTS RECEIVABLE ENTRY ===', [
                    'invoice_id' => $invoice->id,
                    'client_id' => $client->id,
                    'business_id' => $business->id,
                    'branch_id' => $validated['branch_id'],
                    'amount_due' => $invoice->total_amount,
                    'amount_paid_for_ar' => 0, // Credit clients always start with 0 paid in AR
                    'balance_for_ar' => $invoice->total_amount, // They owe the full amount
                    'default_payment_terms_days' => $business->default_payment_terms_days ?? 30,
                ]);

                try {
                    // Calculate due date
                    $dueDate = now()->addDays($business->default_payment_terms_days ?? 30)->toDateString();

                    // For credit clients: Always start as 'current' status with balance = total_amount
                    // Even if they paid upfront, the AR entry reflects what they owe
                    $arStatus = 'current';
                    $arAmountPaid = 0; // Credit clients start with 0 paid in accounts receivable
                    $arBalance = $invoice->total_amount; // They owe the full amount

                    Log::info('=== STEP 6.5: ACCOUNTS RECEIVABLE CREATION PARAMS ===', [
                        'invoice_id' => $invoice->id,
                        'client_id' => $client->id,
                        'business_id' => $business->id,
                        'branch_id' => $validated['branch_id'],
                        'amount_due' => $invoice->total_amount,
                        'amount_paid' => $arAmountPaid,
                        'balance' => $arBalance,
                        'due_date' => $dueDate,
                        'status' => $arStatus,
                        'note' => 'Credit client - AR reflects full debt amount regardless of upfront payment',
                    ]);

                    $accountsReceivable = \App\Models\AccountsReceivable::create([
                        'client_id' => $client->id,
                        'business_id' => $business->id,
                        'branch_id' => $validated['branch_id'],
                        'invoice_id' => $invoice->id,
                        'created_by' => $validated['created_by'],
                        'amount_due' => $invoice->total_amount, // Total invoice amount
                        'amount_paid' => $arAmountPaid, // Always 0 for credit clients (they owe the full amount)
                        'balance' => $arBalance, // Full amount owed
                        'invoice_date' => now()->toDateString(),
                        'due_date' => $dueDate,
                        'status' => $arStatus,
                        'payer_type' => 'first_party',
                        'notes' => "Credit transaction - Invoice #{$invoiceNumber}",
                    ]);

                    Log::info('=== STEP 7: ACCOUNTS RECEIVABLE CREATED SUCCESSFULLY ===', [
                        'accounts_receivable_id' => $accountsReceivable->id,
                        'amount_due' => $accountsReceivable->amount_due,
                        'amount_paid' => $accountsReceivable->amount_paid,
                        'balance' => $accountsReceivable->balance,
                        'status' => $accountsReceivable->status,
                        'due_date' => $accountsReceivable->due_date,
                    ]);
                } catch (\Exception $e) {
                    Log::error('=== ERROR CREATING ACCOUNTS RECEIVABLE ===', [
                        'invoice_id' => $invoice->id,
                        'client_id' => $client->id,
                        'business_id' => $business->id,
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Don't throw - continue processing even if AR creation fails
                    // The backfill will create it later
                }

                // Update client balance with negative entry (they owe money)
                // For credit clients: subtract the total invoice amount (what they're purchasing on credit)
                // This ensures the balance reflects their debt, regardless of upfront payment
                $previousBalance = $client->balance ?? 0;
                // Use balance_due if > 0, otherwise use total_amount (for credit clients who paid upfront but still get credit)
                $amountToDebit = $invoice->balance_due > 0 ? $invoice->balance_due : $invoice->total_amount;
                $newBalance = $previousBalance - $amountToDebit; // Negative balance = they owe

                Log::info('=== STEP 8: UPDATING CLIENT BALANCE ===', [
                    'invoice_id' => $invoice->id,
                    'client_id' => $client->id,
                    'previous_balance' => $previousBalance,
                    'balance_due' => $invoice->balance_due,
                    'total_amount' => $invoice->total_amount,
                    'amount_to_debit' => $amountToDebit,
                    'calculated_new_balance' => $newBalance,
                ]);

                $client->update(['balance' => $newBalance]);
                $client->refresh();

                // Verify balance was actually saved
                $clientAfterUpdate = \App\Models\Client::find($client->id);
                $actualBalance = $clientAfterUpdate->balance ?? 0;

                Log::info('=== STEP 9: CLIENT BALANCE UPDATED ===', [
                    'invoice_id' => $invoice->id,
                    'client_id' => $client->id,
                    'calculated_new_balance' => $newBalance,
                    'client_balance_after_update' => $client->balance,
                    'client_balance_from_fresh_query' => $actualBalance,
                    'balance_matches_calculation' => abs($actualBalance - $newBalance) < 0.01,
                ]);

                // If balance doesn't match, force update again
                if (abs($actualBalance - $newBalance) >= 0.01) {
                    Log::warning('=== BALANCE MISMATCH - FORCING UPDATE ===', [
                        'invoice_id' => $invoice->id,
                        'client_id' => $client->id,
                        'expected_balance' => $newBalance,
                        'actual_balance' => $actualBalance,
                    ]);
                    $clientAfterUpdate->update(['balance' => $newBalance]);
                    $clientAfterUpdate->refresh();
                    Log::info('Balance force-updated', [
                        'client_id' => $client->id,
                        'new_balance' => $clientAfterUpdate->balance,
                    ]);
                }

                // Get payment method before creating balance history
                $paymentMethods = $validated['payment_methods'] ?? [];
                $primaryMethod = ! empty($paymentMethods) ? $paymentMethods[0] : 'credit';

                Log::info('=== STEP 10: PREPARING TO CREATE DEBIT ENTRIES ===', [
                    'invoice_id' => $invoice->id,
                    'payment_methods' => $paymentMethods,
                    'primary_method' => $primaryMethod,
                    'items_count' => count($invoice->items ?? []),
                ]);

                // Create separate debit entry for each item (just like regular payments)
                $itemsCollection = collect($invoice->items ?? []);
                $debitCount = 0;
                $skippedItems = [];

                Log::info('=== STEP 11: PROCESSING ITEMS FOR DEBIT ENTRIES ===', [
                    'invoice_id' => $invoice->id,
                    'total_items' => $itemsCollection->count(),
                    'items' => $itemsCollection->toArray(),
                ]);

                foreach ($itemsCollection as $index => $itemData) {
                    Log::info("=== PROCESSING ITEM {$index} FOR DEBIT ===", [
                        'invoice_id' => $invoice->id,
                        'item_index' => $index,
                        'item_data' => $itemData,
                    ]);

                    $itemId = $itemData['id'] ?? $itemData['item_id'] ?? null;

                    Log::info('Processing item for debit entry', [
                        'invoice_id' => $invoice->id,
                        'item_index' => $index,
                        'item_id' => $itemId,
                        'item_id_source' => isset($itemData['id']) ? 'id' : (isset($itemData['item_id']) ? 'item_id' : 'none'),
                    ]);

                    if (! $itemId) {
                        Log::warning('Skipping item - no item ID found', [
                            'invoice_id' => $invoice->id,
                            'item_index' => $index,
                            'item_data' => $itemData,
                        ]);
                        $skippedItems[] = ['reason' => 'no_item_id', 'data' => $itemData];

                        continue;
                    }

                    $item = \App\Models\Item::find($itemId);
                    if (! $item) {
                        Log::warning('Skipping item - item not found in database', [
                            'invoice_id' => $invoice->id,
                            'item_id' => $itemId,
                        ]);
                        $skippedItems[] = ['reason' => 'item_not_found', 'item_id' => $itemId];

                        continue;
                    }

                    $quantity = $itemData['quantity'] ?? 1;
                    $itemTotalAmount = $itemData['total_amount'] ?? ($itemData['price'] ?? $item->default_price ?? 0) * $quantity;

                    Log::info('Item details for debit', [
                        'invoice_id' => $invoice->id,
                        'item_id' => $itemId,
                        'item_name' => $item->name,
                        'quantity' => $quantity,
                        'item_total_amount' => $itemTotalAmount,
                        'item_total_amount_source' => isset($itemData['total_amount']) ? 'total_amount' : 'calculated',
                    ]);

                    // Skip if amount is zero
                    if ($itemTotalAmount <= 0) {
                        Log::info('Skipping item - zero amount', [
                            'invoice_id' => $invoice->id,
                            'item_id' => $itemId,
                            'item_name' => $item->name,
                            'item_total_amount' => $itemTotalAmount,
                        ]);
                        $skippedItems[] = ['reason' => 'zero_amount', 'item_id' => $itemId, 'amount' => $itemTotalAmount];

                        continue;
                    }

                    // Check if this item is part of a package adjustment (skip if covered by package)
                    $isPackageAdjustmentItem = false;
                    if ($invoice->package_adjustment > 0) {
                        $validPackages = \App\Models\PackageTracking::where('client_id', $client->id)
                            ->where('business_id', $business->id)
                            ->where('status', 'active')
                            ->where('remaining_quantity', '>', 0)
                            ->get();

                        foreach ($validPackages as $packageTracking) {
                            $packageItems = $packageTracking->packageItem->packageItems ?? collect();
                            foreach ($packageItems as $packageItem) {
                                if ($packageItem->included_item_id == $itemId) {
                                    $isPackageAdjustmentItem = true;
                                    break 2;
                                }
                            }
                        }
                    }

                    // Skip creating debit record for package adjustment items
                    if ($isPackageAdjustmentItem) {
                        Log::info('SKIPPING CLIENT DEBIT FOR PACKAGE ITEM (Credit Client)', [
                            'item_id' => $itemId,
                            'item_name' => $item->name,
                            'reason' => 'Item is covered by package adjustment - no debit needed',
                        ]);

                        continue;
                    }

                    // Create separate debit entry for this item
                    $itemDisplayName = $item->name;
                    $debitDescription = "{$itemDisplayName} (x{$quantity})";
                    $debitNotes = "Credit purchase - {$itemDisplayName} (x{$quantity}) - Invoice #{$invoiceNumber}";

                    Log::info('Creating debit entry for item', [
                        'invoice_id' => $invoice->id,
                        'item_id' => $itemId,
                        'item_name' => $itemDisplayName,
                        'quantity' => $quantity,
                        'amount' => $itemTotalAmount,
                        'description' => $debitDescription,
                        'notes' => $debitNotes,
                        'primary_method' => $primaryMethod,
                    ]);

                    try {
                        $balanceHistory = \App\Models\BalanceHistory::recordDebit(
                            $client,
                            $itemTotalAmount,
                            $debitDescription,
                            $invoiceNumber,
                            $debitNotes,
                            $primaryMethod,
                            $invoice->id // Pass invoice_id to link entries, but allow multiple per invoice (different descriptions)
                        );

                        $debitCount++;

                        Log::info('Debit entry created successfully for item', [
                            'invoice_id' => $invoice->id,
                            'balance_history_id' => $balanceHistory->id ?? 'unknown',
                            'item_id' => $itemId,
                            'item_name' => $itemDisplayName,
                            'quantity' => $quantity,
                            'amount' => $itemTotalAmount,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Error creating debit entry for item', [
                            'invoice_id' => $invoice->id,
                            'item_id' => $itemId,
                            'item_name' => $itemDisplayName,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        throw $e; // Re-throw to prevent continuing with invalid state
                    }
                }

                Log::info('=== STEP 12: ITEM DEBIT ENTRIES PROCESSING COMPLETE ===', [
                    'invoice_id' => $invoice->id,
                    'debit_entries_created' => $debitCount,
                    'items_skipped' => count($skippedItems),
                    'skipped_items_details' => $skippedItems,
                ]);

                // Create separate debit entry for service charge if applicable
                if ($invoice->service_charge > 0) {
                    Log::info('=== STEP 13: CREATING SERVICE CHARGE DEBIT ENTRY ===', [
                        'invoice_id' => $invoice->id,
                        'service_charge' => $invoice->service_charge,
                    ]);

                    try {
                        $serviceChargeBalanceHistory = \App\Models\BalanceHistory::recordDebit(
                            $client,
                            $invoice->service_charge,
                            'Service Fee',
                            $invoiceNumber,
                            "Credit purchase - Service Fee - Invoice #{$invoiceNumber}",
                            $primaryMethod,
                            $invoice->id // Pass invoice_id to link entries, but allow multiple per invoice (different descriptions)
                        );

                        $debitCount++;

                        Log::info('Service charge debit entry created successfully', [
                            'invoice_id' => $invoice->id,
                            'balance_history_id' => $serviceChargeBalanceHistory->id ?? 'unknown',
                            'service_charge' => $invoice->service_charge,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Error creating service charge debit entry', [
                            'invoice_id' => $invoice->id,
                            'service_charge' => $invoice->service_charge,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        throw $e; // Re-throw to prevent continuing with invalid state
                    }
                } else {
                    Log::info('No service charge to create debit entry for', [
                        'invoice_id' => $invoice->id,
                        'service_charge' => $invoice->service_charge,
                    ]);
                }

                Log::info('=== STEP 14: ALL DEBIT ENTRIES COMPLETE ===', [
                    'invoice_id' => $invoice->id,
                    'client_id' => $client->id,
                    'previous_balance' => $previousBalance,
                    'new_balance' => $newBalance,
                    'client_current_balance' => $client->balance,
                    'amount_debited' => $amountToDebit,
                    'balance_due' => $invoice->balance_due,
                    'total_amount' => $invoice->total_amount,
                    'total_debit_entries_created' => $debitCount,
                ]);

                // Create transaction record for credit clients (even if payment method is mobile money)

                // Check if transaction already exists
                $existingTransaction = \App\Models\Transaction::where('reference', $invoiceNumber)
                    ->where('client_id', $validated['client_id'])
                    ->where('invoice_id', $invoice->id)
                    ->first();

                if (! $existingTransaction) {
                    $itemsDescription = $this->buildItemsDescription($validated['items'], $client, $business, $invoiceNumber);

                    $transaction = \App\Models\Transaction::create([
                        'business_id' => $validated['business_id'],
                        'branch_id' => $validated['branch_id'],
                        'client_id' => $validated['client_id'],
                        'invoice_id' => $invoice->id,
                        'amount' => $invoice->balance_due, // Use balance_due, not amount_paid
                        'reference' => $invoiceNumber,
                        'description' => $itemsDescription.' (Credit Transaction)',
                        'status' => ($primaryMethod === 'mobile_money' && ($validated['amount_paid'] ?? 0) > 0) ? 'pending' : 'completed',
                        'payment_status' => 'PP', // Always PP for credit transactions with balance due
                        'type' => 'debit',
                        'origin' => 'web',
                        'phone_number' => $validated['payment_phone'] ?? $validated['client_phone'],
                        'provider' => $primaryMethod === 'mobile_money' ? 'yo' : 'cash',
                        'service' => 'credit_invoice',
                        'date' => now(),
                        'currency' => $invoiceCurrency,
                        'names' => $validated['client_name'],
                        'email' => null,
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'method' => $primaryMethod,
                        'transaction_for' => 'main',
                    ]);

                    // Link transaction to accounts receivable
                    $accountsReceivable->update(['transaction_id' => $transaction->id]);

                    Log::info('Transaction created for credit client', [
                        'transaction_id' => $transaction->id,
                        'payment_status' => 'PP',
                        'amount' => $invoice->balance_due,
                    ]);
                }

                // For mobile money payments on credit, update existing transaction if needed
                if ($primaryMethod === 'mobile_money' && ($validated['amount_paid'] ?? 0) > 0) {
                    $existingMobileMoneyTransaction = \App\Models\Transaction::where('reference', $invoiceNumber)
                        ->where('client_id', $validated['client_id'])
                        ->where('method', 'mobile_money')
                        ->whereNull('invoice_id')
                        ->first();

                    if ($existingMobileMoneyTransaction) {
                        $existingMobileMoneyTransaction->update([
                            'invoice_id' => $invoice->id,
                            'payment_status' => 'PP',
                        ]);
                    }
                }
            }

            // For insurance payments: Create accounts receivable and update third-party payer balance
            // Similar to credit clients, but debit the insurance company instead of the client
            // Note: $paymentMethods and $isInsurancePayment are already defined above
            $thirdPartyPayer = null;
            $isInsuranceTransaction = false;

            if ($isInsurancePayment) {
                // Use selected third-party payer ID if provided, otherwise find one linked to this client or insurance company
                $selectedThirdPartyPayerId = $validated['third_party_payer_id'] ?? null;

                if ($selectedThirdPartyPayerId) {
                    // Use the selected third-party payer
                    $thirdPartyPayer = \App\Models\ThirdPartyPayer::where('id', $selectedThirdPartyPayerId)
                        ->where('business_id', $business->id)
                        ->where('status', 'active')
                        ->first();

                    if (! $thirdPartyPayer) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Selected third-party payer not found or inactive.',
                        ], 400);
                    }
                } else {
                    // First, try to find a business-level third-party payer for this insurance company
                    // (shared by all clients with this insurance company - this is the "internal" account)
                    if ($client->insurance_company_id) {
                        $thirdPartyPayer = \App\Models\ThirdPartyPayer::where('insurance_company_id', $client->insurance_company_id)
                            ->where('business_id', $business->id)
                            ->where('type', 'insurance_company')
                            ->whereNull('client_id') // Business-level, not client-specific
                            ->where('status', 'active')
                            ->first();

                        // If not found, try client-specific one as fallback
                        if (! $thirdPartyPayer) {
                            $thirdPartyPayer = \App\Models\ThirdPartyPayer::where('client_id', $client->id)
                                ->where('business_id', $business->id)
                                ->where('type', 'insurance_company')
                                ->where('status', 'active')
                                ->first();
                        }

                        // If still not found, create a business-level one automatically
                        // This is the "internal" account where all invoices for this insurance company are posted
                        if (! $thirdPartyPayer) {
                            $insuranceCompany = \App\Models\InsuranceCompany::find($client->insurance_company_id);
                            if ($insuranceCompany) {
                                $thirdPartyPayer = \App\Models\ThirdPartyPayer::create([
                                    'business_id' => $business->id,
                                    'type' => 'insurance_company',
                                    'insurance_company_id' => $insuranceCompany->id,
                                    'client_id' => null, // Business-level account (shared by all clients)
                                    'name' => $insuranceCompany->name,
                                    'status' => 'active',
                                    'credit_limit' => 0, // Can be configured later
                                ]);

                                Log::info('Auto-created business-level third-party payer for insurance company', [
                                    'third_party_payer_id' => $thirdPartyPayer->id,
                                    'insurance_company_id' => $insuranceCompany->id,
                                    'insurance_company_name' => $insuranceCompany->name,
                                    'business_id' => $business->id,
                                    'note' => 'This is the internal account where all invoices for this insurance company are posted',
                                ]);
                            }
                        }
                    } else {
                        // Fallback: Find the third-party payer linked to this client (if no insurance_company_id)
                        $thirdPartyPayer = \App\Models\ThirdPartyPayer::where('client_id', $client->id)
                            ->where('business_id', $business->id)
                            ->where('type', 'insurance_company')
                            ->where('status', 'active')
                            ->first();
                    }
                }

                if ($thirdPartyPayer) {
                    $isInsuranceTransaction = true;

                    Log::info('=== INSURANCE TRANSACTION DETECTED - STARTING PROCESSING ===', [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'client_id' => $client->id,
                        'client_name' => $client->name,
                        'third_party_payer_id' => $thirdPartyPayer->id,
                        'third_party_payer_name' => $thirdPartyPayer->name,
                    ]);

                    // For insurance payments, payment_status should be 'pending_payment' if there's balance due
                    if ($invoice->payment_status !== 'pending_payment' && $invoice->balance_due > 0) {
                        $oldPaymentStatus = $invoice->payment_status;
                        $invoice->update(['payment_status' => 'pending_payment']);
                        $invoice->refresh();
                        Log::info('=== INSURANCE INVOICE PAYMENT STATUS UPDATED ===', [
                            'invoice_id' => $invoice->id,
                            'old_payment_status' => $oldPaymentStatus,
                            'new_payment_status' => $invoice->payment_status,
                        ]);
                    }

                    // Create accounts receivable entry for third-party payer
                    try {
                        $dueDate = now()->addDays($business->default_payment_terms_days ?? 30)->toDateString();
                        $arStatus = 'current';
                        $arAmountPaid = 0;
                        $arBalance = $invoice->total_amount;

                        $accountsReceivable = \App\Models\AccountsReceivable::create([
                            'client_id' => $client->id, // Client who received the service
                            'third_party_payer_id' => $thirdPartyPayer->id, // Who will pay
                            'business_id' => $business->id,
                            'branch_id' => $validated['branch_id'],
                            'invoice_id' => $invoice->id,
                            'created_by' => $validated['created_by'],
                            'amount_due' => $invoice->total_amount,
                            'amount_paid' => $arAmountPaid,
                            'balance' => $arBalance,
                            'invoice_date' => now()->toDateString(),
                            'due_date' => $dueDate,
                            'status' => $arStatus,
                            'payer_type' => 'third_party',
                            'notes' => "Insurance transaction - Invoice #{$invoiceNumber}",
                        ]);

                        Log::info('=== INSURANCE ACCOUNTS RECEIVABLE CREATED ===', [
                            'accounts_receivable_id' => $accountsReceivable->id,
                            'third_party_payer_id' => $thirdPartyPayer->id,
                            'amount_due' => $accountsReceivable->amount_due,
                            'balance' => $accountsReceivable->balance,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('=== ERROR CREATING INSURANCE ACCOUNTS RECEIVABLE ===', [
                            'invoice_id' => $invoice->id,
                            'third_party_payer_id' => $thirdPartyPayer->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    // Per-vendor balance history debits are created in the post-authorization
                    // block below, once the snapshot with per-vendor amounts is available.

                    // Create separate debit entry for each item
                    $itemsCollection = collect($invoice->items ?? []);
                    $debitCount = 0;
                    $primaryMethod = 'insurance';

                    // Insurance debits to the third-party payer are now created only
                    // after the client's portion has been paid (see CheckPaymentStatus).
                    // We still create tracking entries in the client's BalanceHistory below.
                    Log::info('=== CREATING CLIENT TRACKING ENTRIES FOR INSURANCE PAYMENT ===', [
                        'invoice_id' => $invoice->id,
                        'client_id' => $client->id,
                    ]);

                    $itemsCollectionForTracking = collect($invoice->items ?? []);
                    $trackingCount = 0;

                    $thirdPartyPayer->loadMissing('insuranceCompany');
                    $insuranceTrackingPayerBracketName = $thirdPartyPayer->insuranceCompany->name ?? $thirdPartyPayer->name;

                    foreach ($itemsCollectionForTracking as $index => $itemData) {
                        $itemId = $itemData['id'] ?? $itemData['item_id'] ?? null;
                        if (! $itemId) {
                            continue;
                        }

                        $item = \App\Models\Item::find($itemId);
                        if (! $item) {
                            continue;
                        }

                        $quantity = $itemData['quantity'] ?? 1;
                        $itemTotalAmount = $itemData['total_amount'] ?? ($itemData['price'] ?? $item->default_price ?? 0) * $quantity;

                        if ($itemTotalAmount <= 0) {
                            continue;
                        }

                        // Skip package adjustment items
                        $isPackageAdjustmentItem = false;
                        if ($invoice->package_adjustment > 0) {
                            $validPackages = \App\Models\PackageTracking::where('client_id', $client->id)
                                ->where('business_id', $business->id)
                                ->where('status', 'active')
                                ->where('remaining_quantity', '>', 0)
                                ->get();

                            foreach ($validPackages as $packageTracking) {
                                $packageItems = $packageTracking->packageItem->packageItems ?? collect();
                                foreach ($packageItems as $packageItem) {
                                    if ($packageItem->included_item_id == $itemId) {
                                        $isPackageAdjustmentItem = true;
                                        break 2;
                                    }
                                }
                            }
                        }

                        if ($isPackageAdjustmentItem) {
                            continue;
                        }

                        // Create tracking entry (no balance change - just for display)
                        $itemDisplayName = $item->name;
                        $trackingDescription = "{$itemDisplayName} (x{$quantity}) [Insurance]";
                        $trackingNotes = "Insurance payment - {$itemDisplayName} (x{$quantity}) - Invoice #{$invoiceNumber} - Paid by {$insuranceTrackingPayerBracketName}";

                        try {
                            // Get current balance (won't change, but needed for the record)
                            $currentBalance = \App\Models\BalanceHistory::where('client_id', $client->id)
                                ->orderBy('created_at', 'desc')
                                ->value('new_balance') ?? ($client->balance ?? 0);

                            // Create tracking entry with transaction_type='debit' but change_amount=0
                            // payment_method='insurance' and special notes identify it as tracking
                            $trackingEntry = \App\Models\BalanceHistory::create([
                                'client_id' => $client->id,
                                'business_id' => $client->business_id,
                                'branch_id' => $client->branch_id,
                                'invoice_id' => $invoice->id,
                                'user_id' => auth()->id() ?? 1,
                                'previous_balance' => $currentBalance,
                                'change_amount' => 0, // No balance change - just tracking
                                'new_balance' => $currentBalance, // Balance stays the same
                                'transaction_type' => 'debit', // Use debit type but with 0 amount
                                'description' => $trackingDescription,
                                'reference_number' => $invoiceNumber,
                                'notes' => $trackingNotes,
                                'payment_method' => 'insurance',
                                'payment_status' => 'paid', // Insurance payments are considered paid
                            ]);

                            $trackingCount++;

                            Log::info('Client tracking entry created for insurance payment', [
                                'invoice_id' => $invoice->id,
                                'client_id' => $client->id,
                                'item_id' => $itemId,
                                'tracking_entry_id' => $trackingEntry->id,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Error creating client tracking entry for insurance payment', [
                                'invoice_id' => $invoice->id,
                                'client_id' => $client->id,
                                'item_id' => $itemId,
                                'error' => $e->getMessage(),
                            ]);
                            // Don't throw - tracking entries are not critical
                        }
                    }

                    // Create tracking entry for service charge if applicable
                    if ($invoice->service_charge > 0) {
                        try {
                            $currentBalance = \App\Models\BalanceHistory::where('client_id', $client->id)
                                ->orderBy('created_at', 'desc')
                                ->value('new_balance') ?? ($client->balance ?? 0);

                            $trackingEntry = \App\Models\BalanceHistory::create([
                                'client_id' => $client->id,
                                'business_id' => $client->business_id,
                                'branch_id' => $client->branch_id,
                                'invoice_id' => $invoice->id,
                                'user_id' => auth()->id() ?? 1,
                                'previous_balance' => $currentBalance,
                                'change_amount' => 0, // No balance change
                                'new_balance' => $currentBalance,
                                'transaction_type' => 'debit',
                                'description' => 'Service Fee [Insurance]',
                                'reference_number' => $invoiceNumber,
                                'notes' => "Insurance payment - Service Fee - Invoice #{$invoiceNumber} - Paid by {$insuranceTrackingPayerBracketName}",
                                'payment_method' => 'insurance',
                                'payment_status' => 'paid',
                            ]);

                            $trackingCount++;

                            Log::info('Client tracking entry created for service charge (insurance)', [
                                'invoice_id' => $invoice->id,
                                'client_id' => $client->id,
                                'tracking_entry_id' => $trackingEntry->id,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Error creating client tracking entry for service charge (insurance)', [
                                'invoice_id' => $invoice->id,
                                'client_id' => $client->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    Log::info('=== CLIENT TRACKING ENTRIES CREATED FOR INSURANCE PAYMENT ===', [
                        'invoice_id' => $invoice->id,
                        'client_id' => $client->id,
                        'tracking_entries_created' => $trackingCount,
                    ]);
                } else {
                    Log::warning('Insurance payment selected but no active third-party payer found for client', [
                        'invoice_id' => $invoice->id,
                        'client_id' => $client->id,
                    ]);
                }
            }

            // MONEY TRACKING: Step 1 - Process payment received
            // For credit clients and insurance payments with balance due, skip suspense account processing
            // For non-credit clients or fully paid invoices, process normally
            if ($validated['amount_paid'] > 0 && ! $isCreditTransaction && ! $isInsuranceTransaction) {
                $paymentMethods = $validated['payment_methods'] ?? [];
                $primaryMethod = ! empty($paymentMethods) ? $paymentMethods[0] : 'cash';

                // Only process payment immediately for cash payments
                // Mobile money payments will be processed by the cron job when payment is completed
                if ($primaryMethod === 'cash') {
                    Log::info('Processing cash payment immediately', [
                        'client_id' => $client->id,
                        'amount_paid' => $validated['amount_paid'],
                        'invoice_number' => $invoiceNumber,
                        'primary_method' => $primaryMethod,
                    ]);

                    // Process payment through money tracking system
                    $moneyTrackingService->processPaymentReceived(
                        $client,
                        $validated['amount_paid'],
                        $invoiceNumber,
                        $primaryMethod,
                        [
                            'invoice_id' => $invoice->id,
                            'payment_methods' => $paymentMethods,
                            'payment_phone' => $validated['payment_phone'],
                        ]
                    );

                    Log::info('Cash payment processed successfully', [
                        'client_id' => $client->id,
                        'amount_paid' => $validated['amount_paid'],
                    ]);
                } else {
                    Log::info('Mobile money payment detected - will be processed by cron job when payment is completed', [
                        'client_id' => $client->id,
                        'amount_paid' => $validated['amount_paid'],
                        'invoice_number' => $invoiceNumber,
                        'primary_method' => $primaryMethod,
                    ]);

                    // Update any existing mobile money transaction with the invoice_id
                    $existingMobileMoneyTransaction = \App\Models\Transaction::where('reference', $invoiceNumber)
                        ->where('client_id', $validated['client_id'])
                        ->where('amount', $validated['amount_paid'])
                        ->where('method', 'mobile_money')
                        ->whereNull('invoice_id')
                        ->first();

                    if ($existingMobileMoneyTransaction) {
                        $existingMobileMoneyTransaction->update(['invoice_id' => $invoice->id]);
                        Log::info('Updated mobile money transaction with invoice_id', [
                            'transaction_id' => $existingMobileMoneyTransaction->id,
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoiceNumber,
                        ]);
                    }
                }

                // Determine payment status label for the transaction record.
                $paymentStatus = 'Paid';

                // TODO: REVERT IN PROD - local bypass: create transaction immediately for mobile money
                // when API is bypassed on frontend and payment arrives as fully paid.
                if ($primaryMethod === 'mobile_money' && ($validated['amount_paid'] ?? 0) >= $invoice->total_amount) {
                    $existingTransaction = \App\Models\Transaction::where('reference', $invoiceNumber)
                        ->where('client_id', $validated['client_id'])
                        ->first();

                    if (! $existingTransaction) {
                        $itemsDescription = $this->buildItemsDescription($validated['items'], $client, $business, $invoiceNumber);

                        \App\Models\Transaction::create([
                            'business_id' => $validated['business_id'],
                            'branch_id' => $validated['branch_id'],
                            'client_id' => $validated['client_id'],
                            'invoice_id' => $invoice->id,
                            'amount' => $validated['amount_paid'],
                            'reference' => $invoiceNumber,
                            'description' => $itemsDescription,
                            'status' => 'completed',
                            'payment_status' => $paymentStatus,
                            'type' => 'debit',
                            'origin' => 'web',
                            'phone_number' => $validated['payment_phone'] ?? $validated['client_phone'],
                            'provider' => 'yo',
                            'service' => 'invoice_payment',
                            'date' => now(),
                            'currency' => $invoiceCurrency,
                            'names' => $validated['client_name'],
                            'email' => null,
                            'ip_address' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                            'method' => 'mobile_money',
                            'transaction_for' => 'main',
                        ]);
                    }
                }

                // Create transaction record for cash payments (non-credit transactions)
                // Mobile money transactions are created in processMobileMoneyPayment method
                if ($primaryMethod === 'cash') {
                    // Check if a transaction already exists for this invoice (to prevent duplicates)
                    $existingTransaction = \App\Models\Transaction::where('reference', $invoiceNumber)
                        ->where('client_id', $validated['client_id'])
                        ->where('amount', $validated['amount_paid'])
                        ->first();

                    // Only create transaction record if one doesn't already exist
                    if (! $existingTransaction) {
                        // Build description with purchased items, client, and business information
                        $itemsDescription = $this->buildItemsDescription($validated['items'], $client, $business, $invoiceNumber);

                        $cashTransaction = \App\Models\Transaction::create([
                            'business_id' => $validated['business_id'],
                            'branch_id' => $validated['branch_id'],
                            'client_id' => $validated['client_id'],
                            'invoice_id' => $invoice->id,
                            'amount' => $validated['amount_paid'],
                            'reference' => $invoiceNumber,
                            'description' => $itemsDescription,
                            'status' => 'completed',
                            'payment_status' => $paymentStatus,
                            'type' => 'debit',
                            'origin' => 'web',
                            'phone_number' => $validated['payment_phone'] ?? $validated['client_phone'],
                            'provider' => 'cash',
                            'service' => 'invoice_payment',
                            'date' => now(),
                            'currency' => $invoiceCurrency,
                            'names' => $validated['client_name'],
                            'email' => null,
                            'ip_address' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                            'method' => $primaryMethod,
                            'transaction_for' => 'main',
                        ]);
                        InsuranceClientPortionThirdPartyNotifier::notifyIfApplicable($invoice, $cashTransaction);
                    } else {
                        // Update existing transaction with invoice_id if it doesn't have one
                        if (! $existingTransaction->invoice_id) {
                            $existingTransaction->update(['invoice_id' => $invoice->id]);
                        }
                    }
                }
            }

            // MONEY TRACKING: Step 2 - Process order confirmation (includes service charge)
            if ($validated['status'] === 'confirmed' && $nonDepositItems->isNotEmpty()) {
                Log::info('Processing order confirmation', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'items_count' => $nonDepositItems->count(),
                ]);

                $orderConfirmationResult = $moneyTrackingService->processOrderConfirmed($invoice, $nonDepositItems->toArray());

                Log::info('Order confirmation processed', [
                    'invoice_id' => $invoice->id,
                    'result' => $orderConfirmationResult,
                ]);
            }

            // PACKAGE TRACKING: Package tracking records will be created after payment completion
            // (handled in CheckPaymentStatus.php when payment is confirmed)

            // PACKAGE ADJUSTMENT: Log that package adjustments will be processed after payment completion
            if ($validated['package_adjustment'] > 0) {
                Log::info('=== PACKAGE ADJUSTMENT DETECTED IN INVOICE CREATION ===', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'package_adjustment_amount' => $validated['package_adjustment'],
                    'subtotal' => $validated['subtotal'],
                    'subtotal_1' => $validated['subtotal_1'] ?? 0,
                    'subtotal_2' => $validated['subtotal_2'] ?? 0,
                    'service_charge' => $validated['service_charge'] ?? 0,
                    'total_amount' => $validated['total_amount'],
                    'items_count' => count($validated['items']),
                    'items_details' => $validated['items'],
                    'client_id' => $validated['client_id'],
                    'client_name' => $client->name ?? 'Unknown',
                    'business_id' => $validated['business_id'],
                    'business_name' => $business->name ?? 'Unknown',
                    'branch_id' => $validated['branch_id'],
                    'timestamp' => now()->toISOString(),
                    'note' => 'Package tracking will be updated after payment completion via Save & Exit',
                ]);
            }

            // BALANCE ADJUSTMENT: Update client balance if balance adjustment was used
            if ($validated['account_balance_adjustment'] > 0) {
                $this->updateClientBalance($client, $validated['account_balance_adjustment']);
            }

            // SERVICE POINT QUEUING: Items will be queued only after payment is completed
            // This is now handled by the CheckPaymentStatus cron job
            // EXCEPTIONS:
            // 1. For zero-amount transactions (due to package adjustments OR balance adjustments), queue immediately
            // 2. For credit clients with balance_due > 0, queue immediately (no payment required upfront)
            // 3. For insurance payments, queue immediately (regardless of third-party payer status)
            // 4. For fully paid cash transactions, queue immediately (payment is complete)
            // Zero-amount transactions should always be auto-completed regardless of payment method

            // Determine if this is an insurance payment (check payment methods, not just third-party payer)
            $paymentMethods = $validated['payment_methods'] ?? [];
            $isInsurancePaymentMethod = in_array('insurance', $paymentMethods);

            // Insurance authorization runs after commit; queue only after POS user confirms (Continue).
            $hasActiveInsuranceVendors = \App\Models\ClientVendor::where('client_id', $client->id)
                ->where('status', 'active')
                ->exists();
            $willRequestInsuranceAuthorization = $hasActiveInsuranceVendors
                || ($client->insurance_company_id && trim((string) ($client->policy_number ?? '')) !== '');
            $deferInsuranceServiceQueue = $willRequestInsuranceAuthorization && $nonDepositItems->isNotEmpty();

            // Check if payment is fully completed (for cash payments)
            $isFullyPaid = ($validated['amount_paid'] ?? 0) >= $invoice->total_amount && $invoice->payment_status === 'paid';
            $isCashPayment = in_array('cash', $paymentMethods) && ! $isCreditTransaction && ! $isInsurancePaymentMethod;
            $isMobileMoneyPayment = in_array('mobile_money', $paymentMethods) && ! $isCreditTransaction && ! $isInsurancePaymentMethod;

            $shouldQueueInsuranceNow = ($isInsuranceTransaction || $isInsurancePaymentMethod) && ! $deferInsuranceServiceQueue;

            // Queue items immediately for credit clients (they're approved for credit, items should be offered)
            Log::info('=== STEP 15: CHECKING IF ITEMS SHOULD BE QUEUED ===', [
                'invoice_id' => $invoice->id,
                'is_credit_transaction' => $isCreditTransaction,
                'is_insurance_transaction' => $isInsuranceTransaction,
                'is_insurance_payment_method' => $isInsurancePaymentMethod,
                'defer_insurance_service_queue' => $deferInsuranceServiceQueue,
                'should_queue_insurance_now' => $shouldQueueInsuranceNow,
                'is_fully_paid' => $isFullyPaid,
                'is_cash_payment' => $isCashPayment,
                'non_deposit_items_empty' => $nonDepositItems->isEmpty(),
                'non_deposit_items_count' => $nonDepositItems->count(),
                'is_credit_client' => $isCreditClient,
                'has_balance_due' => $hasBalanceDue,
                'payment_status' => $invoice->payment_status,
                'amount_paid' => $validated['amount_paid'] ?? 0,
                'total_amount' => $invoice->total_amount,
            ]);

            // Queue items for:
            // 1. Credit clients (approved to receive services on credit)
            // 2. Insurance transactions (after POS confirms authorization — see completeInsuranceServiceDelivery)
            // 3. Fully paid cash transactions (payment complete)
            $shouldQueueItems = (
                $isCreditTransaction ||
                $shouldQueueInsuranceNow ||
                ($isFullyPaid && ($isCashPayment || $isMobileMoneyPayment))
            ) && $nonDepositItems->isNotEmpty();

            if ($shouldQueueItems) {
                // Determine transaction type for logging
                if ($shouldQueueInsuranceNow && ($isInsuranceTransaction || $isInsurancePaymentMethod)) {
                    $transactionType = 'INSURANCE';
                } elseif ($isCreditTransaction) {
                    $transactionType = 'CREDIT';
                } elseif ($isFullyPaid && $isMobileMoneyPayment) {
                    $transactionType = 'MOBILE MONEY (FULLY PAID)';
                } elseif ($isFullyPaid && $isCashPayment) {
                    $transactionType = 'CASH (FULLY PAID)';
                } else {
                    $transactionType = 'OTHER';
                }

                Log::info("=== STEP 16: {$transactionType} TRANSACTION - QUEUING ITEMS IMMEDIATELY ===", [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'is_credit_eligible' => $isCreditClient,
                    'is_insurance_payment' => $isInsuranceTransaction || $isInsurancePaymentMethod,
                    'is_fully_paid_cash' => $isFullyPaid && $isCashPayment,
                    'is_fully_paid_mobile_money' => $isFullyPaid && $isMobileMoneyPayment,
                    'balance_due' => $invoice->balance_due,
                    'items_count' => $nonDepositItems->count(),
                    'items' => $nonDepositItems->toArray(),
                ]);

                try {
                    // Get insurance company ID if this is an insurance payment
                    $insuranceCompanyId = null;
                    if ($isInsuranceTransaction || $isInsurancePaymentMethod) {
                        $insuranceCompanyId = $client->insurance_company_id ?? null;
                        if (! $insuranceCompanyId && $thirdPartyPayer) {
                            $insuranceCompanyId = $thirdPartyPayer->insurance_company_id ?? null;
                        }
                    }

                    // Queue items immediately for credit clients, insurance payments, or fully paid cash transactions
                    $this->queueItemsAtServicePoints($invoice, $nonDepositItems->toArray(), $insuranceCompanyId);

                    // For credit clients and insurance payments: Process suspense account movements even though no payment was received
                    // This ensures money moves to suspense accounts (general, package, kashtre) for proper tracking
                    // For fully paid cash transactions, suspense movements are already processed above
                    if ($isCreditTransaction || $isInsuranceTransaction || $isInsurancePaymentMethod) {
                        Log::info("=== STEP 17: PROCESSING SUSPENSE ACCOUNT MOVEMENTS FOR {$transactionType} TRANSACTION ===", [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'client_id' => $client->id,
                            'items_count' => count($nonDepositItems),
                        ]);

                        $suspenseMovements = $moneyTrackingService->processSuspenseAccountMovements($invoice, $nonDepositItems->toArray());

                        Log::info("=== STEP 18: SUSPENSE ACCOUNT MOVEMENTS COMPLETED FOR {$transactionType} TRANSACTION ===", [
                            'invoice_id' => $invoice->id,
                            'movements_count' => count($suspenseMovements),
                            'movements' => $suspenseMovements,
                        ]);
                    }

                    // Verify items were queued
                    $queuedCount = \App\Models\ServiceDeliveryQueue::where('invoice_id', $invoice->id)->count();

                    Log::info("=== STEP 19: ITEMS QUEUED SUCCESSFULLY FOR {$transactionType} TRANSACTION ===", [
                        'invoice_id' => $invoice->id,
                        'items_queued' => $nonDepositItems->count(),
                        'verified_queued_count' => $queuedCount,
                        'queue_verification_match' => $queuedCount === $nonDepositItems->count(),
                    ]);
                } catch (\Exception $e) {
                    Log::error('=== ERROR QUEUING ITEMS FOR CREDIT CLIENT ===', [
                        'invoice_id' => $invoice->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Don't throw - we still want the invoice to be created
                }
            } else {
                Log::warning('=== STEP 16: CREDIT CLIENT QUEUING SKIPPED ===', [
                    'invoice_id' => $invoice->id,
                    'is_credit_transaction' => $isCreditTransaction,
                    'non_deposit_items_empty' => $nonDepositItems->isEmpty(),
                    'non_deposit_items_count' => $nonDepositItems->count(),
                    'is_credit_client' => $isCreditClient,
                    'has_balance_due' => $hasBalanceDue,
                    'reason' => ! $isCreditTransaction ? 'not_credit_transaction' : ($nonDepositItems->isEmpty() ? 'no_non_deposit_items' : 'unknown'),
                ]);
            }

            Log::info('=== STEP 18: CREDIT CLIENT PROCESSING COMPLETE ===', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $client->id,
                'is_credit_transaction' => $isCreditTransaction,
                'items_queued' => $isCreditTransaction && $nonDepositItems->isNotEmpty(),
            ]);

            // Queue items immediately for zero-amount transactions
            if ($validated['total_amount'] == 0 && $nonDepositItems->isNotEmpty()) {
                Log::info('Zero-amount transaction detected - auto-completing and queuing items', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'total_amount' => $validated['total_amount'],
                    'package_adjustment' => $validated['package_adjustment'] ?? 0,
                    'account_balance_adjustment' => $validated['account_balance_adjustment'] ?? 0,
                    'payment_methods' => $validated['payment_methods'] ?? [],
                ]);

                // Auto-complete the transaction
                $invoice->update([
                    'payment_status' => 'paid',
                    'status' => 'confirmed',
                    'amount_paid' => 0,
                    'balance_due' => 0,
                    'confirmed_at' => now(),
                ]);

                // Create transaction record for zero-amount transactions
                $itemsDescription = $this->buildItemsDescription($validated['items'], $client, $business, $invoiceNumber);

                \App\Models\Transaction::create([
                    'business_id' => $validated['business_id'],
                    'branch_id' => $validated['branch_id'],
                    'client_id' => $validated['client_id'],
                    'invoice_id' => $invoice->id,
                    'amount' => 0,
                    'reference' => $invoiceNumber,
                    'description' => $itemsDescription,
                    'status' => 'completed',
                    'type' => 'debit',
                    'origin' => 'web',
                    'phone_number' => $validated['payment_phone'] ?? $validated['client_phone'],
                    'provider' => 'yo', // Use 'yo' as default provider for zero-amount transactions
                    'service' => 'invoice_payment',
                    'date' => now(),
                    'currency' => $invoiceCurrency,
                    'names' => $validated['client_name'],
                    'email' => null,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'method' => 'package_adjustment',
                    'transaction_for' => 'main',
                ]);

                Log::info('Transaction record created for zero-amount transaction', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoiceNumber,
                    'amount' => 0,
                    'description' => $itemsDescription,
                ]);

                // Get insurance company ID if this is an insurance payment
                $insuranceCompanyId = null;
                $paymentMethods = $validated['payment_methods'] ?? [];
                if (in_array('insurance', $paymentMethods)) {
                    $insuranceCompanyId = $client->insurance_company_id ?? null;
                }

                // Queue items immediately for zero-amount transactions
                $this->queueItemsAtServicePoints($invoice, $nonDepositItems->toArray(), $insuranceCompanyId);

                Log::info('Zero-amount transaction auto-completed and items queued', [
                    'invoice_id' => $invoice->id,
                    'items_queued' => count($validated['items']),
                ]);
            }

            // Generate next invoice number (default to proforma type)
            $nextInvoiceNumber = Invoice::generateInvoiceNumber($business->id, 'proforma');

            // Handle direct client deposits
            if ($depositTotalAmount > 0) {
                $moneyTrackingService->processClientDeposit($client, $depositTotalAmount, $invoice, $depositItems->values()->toArray());

                $newAmountPaid = ($invoice->amount_paid ?? 0) + $depositTotalAmount;
                $newBalanceDue = max($invoice->total_amount - $newAmountPaid, 0);
                $invoice->update([
                    'amount_paid' => $newAmountPaid,
                    'balance_due' => $newBalanceDue,
                    'payment_status' => $newBalanceDue <= 0 ? 'paid' : $invoice->payment_status,
                ]);
            }

            DB::commit();

            // Final verification: Check client balance one more time after commit
            if ($isCreditTransaction) {
                $finalClientCheck = \App\Models\Client::find($client->id);
                Log::info('=== FINAL CLIENT BALANCE VERIFICATION ===', [
                    'invoice_id' => $invoice->id,
                    'client_id' => $client->id,
                    'client_balance_after_commit' => $finalClientCheck->balance ?? 0,
                    'expected_negative_balance' => $invoice->balance_due > 0,
                ]);

                // If balance should be negative but isn't, fix it
                if ($invoice->balance_due > 0 && ($finalClientCheck->balance ?? 0) >= 0) {
                    $expectedBalance = ($finalClientCheck->balance ?? 0) - $invoice->balance_due;
                    Log::warning('=== BALANCE NOT NEGATIVE AFTER COMMIT - FIXING ===', [
                        'invoice_id' => $invoice->id,
                        'client_id' => $client->id,
                        'current_balance' => $finalClientCheck->balance ?? 0,
                        'expected_balance' => $expectedBalance,
                        'balance_due' => $invoice->balance_due,
                    ]);
                    $finalClientCheck->update(['balance' => $expectedBalance]);
                    Log::info('Balance fixed after commit', [
                        'client_id' => $client->id,
                        'new_balance' => $finalClientCheck->fresh()->balance,
                    ]);
                }
            }

            Log::info('=== INVOICE CREATION COMPLETED SUCCESSFULLY ===', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $invoice->client_id,
                'business_id' => $invoice->business_id,
                'total_amount' => $invoice->total_amount,
                'payment_status' => $invoice->payment_status,
                'status' => $invoice->status,
                'client_final_balance' => $isCreditTransaction ? (\App\Models\Client::find($client->id)->balance ?? 0) : null,
            ]);

            // Insurance flow: send invoice to third-party for authorization; get client_total and insurance_total (sync, one round-trip)
            $insuranceAuthorization = null;

            // ── Multi-vendor cascade ──────────────────────────────────────────────────
            // For clients with multiple insurers (ClientVendor records), cascade the
            // invoice: full amount → Vendor 1, Vendor 1's client_total → Vendor 2, etc.
            $activeClientVendors = \App\Models\ClientVendor::where('client_id', $client->id)
                ->where('status', 'active')
                ->orderBy('priority')
                ->get();

            if ($activeClientVendors->count() > 0) {
                $insuranceAuthorization = $this->runMultiVendorCascade(
                    $invoice, $client, $business, $activeClientVendors,
                    isset($validated['deductible_remaining']) ? (float) $validated['deductible_remaining'] : 0
                );
            }

            // ── Legacy single-vendor ─────────────────────────────────────────────────
            if (! $insuranceAuthorization && $client->insurance_company_id && $client->policy_number) {
                Log::info('[Kashtre] Insurance authorization: client is insurance', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $client->id,
                    'insurance_company_id' => $client->insurance_company_id,
                    'policy_number' => $client->policy_number,
                ]);
                $insuranceCompany = \App\Models\InsuranceCompany::find($client->insurance_company_id);
                $thirdPartyBusinessId = $insuranceCompany->third_party_business_id ?? null;
                if ($thirdPartyBusinessId) {
                    // Check if the vendor is suspended or blocked
                    $vendor = \App\Models\ThirdPartyPayer::where('business_id', $thirdPartyBusinessId)->first();
                    if ($vendor && ($vendor->isSuspended() || $vendor->isBlocked())) {
                        Log::warning('[Kashtre] Insurance authorization blocked: vendor is suspended/blocked', [
                            'invoice_id' => $invoice->id,
                            'vendor_id' => $vendor->id,
                            'vendor_status' => $vendor->status,
                            'block_reason' => $vendor->block_reason,
                        ]);

                        return response()->json([
                            'success' => false,
                            'message' => "Cannot authorize invoice: Insurance vendor is {$vendor->status}. Reason: {$vendor->block_reason}",
                            'vendor_status' => $vendor->status,
                            'vendor_block_reason' => $vendor->block_reason,
                        ], 403);
                    }
                    $deductibleRemaining = isset($validated['deductible_remaining']) ? (float) $validated['deductible_remaining'] : 0;
                    // Prepare items payload for authorization, including item codes and Kashtre exclusion flags
                    $itemsForAuthorization = collect($invoice->items ?? [])->map(function (array $item) {
                        $itemId = $item['id'] ?? $item['item_id'] ?? null;
                        $itemModel = $itemId ? \App\Models\Item::find($itemId) : null;
                        $quantity = (float) ($item['quantity'] ?? 1);
                        $price = (float) ($item['price'] ?? 0);
                        $total = (float) ($item['total_amount'] ?? ($price * $quantity));

                        return [
                            'id' => $itemId,
                            'name' => $item['name'] ?? $item['displayName'] ?? null,
                            'quantity' => $quantity,
                            'price' => $price,
                            'total_amount' => $total,
                            'code' => $itemModel?->code,
                            'type' => $itemModel?->type,
                            'kashtre_excluded' => ! empty($item['kashtre_excluded']),
                        ];
                    })->values()->all();
                    $apiService = new \App\Services\ThirdPartyApiService();
                    $authPayload = [
                        'kashtre_invoice_id' => (string) $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'insurance_company_id' => (int) $thirdPartyBusinessId,
                        'policy_number' => trim($client->policy_number),
                        'services_category' => $client->services_category ?? null,
                        'total_amount' => (float) $invoice->total_amount,
                        'deductible_remaining' => $deductibleRemaining,
                        'copay_contributes_to_deductible' => (bool) $client->copay_contributes_to_deductible,
                        'coinsurance_contributes_to_deductible' => (bool) $client->coinsurance_contributes_to_deductible,
                        'items' => $itemsForAuthorization,
                        'connected_business_id' => $business->id,
                    ];
                    Log::info('[Kashtre] Insurance authorization: calling third-party', [
                        'invoice_id' => $invoice->id,
                        'third_party_business_id' => $thirdPartyBusinessId,
                        'total_amount' => $authPayload['total_amount'],
                        'deductible_remaining' => $deductibleRemaining,
                        'copay_contributes_to_deductible' => $authPayload['copay_contributes_to_deductible'],
                        'coinsurance_contributes_to_deductible' => $authPayload['coinsurance_contributes_to_deductible'],
                    ]);
                    $insuranceAuthorization = $apiService->requestInvoiceAuthorization($authPayload);
                    if ($insuranceAuthorization && ! empty($insuranceAuthorization['success'])) {
                        // Client portion must match invoice split: invoice_total = insurance_total + client_total (vendor ledger).
                        $insuranceAuthorization = $this->normalizeInsuranceAuthorizationClientTotals($invoice, $insuranceAuthorization);
                        // Persist clinical vs fee breakdown for receipts / POS confirmation UI.
                        $insuranceAuthorization = $this->withInvoicePricingOnAuthorizationSnapshot($invoice, $insuranceAuthorization);
                        // Same per-line insurer attribution as multi-vendor (POS / invoice show immediately on confirm).
                        $vendorSnapshotRow = [
                            'vendor_name' => trim((string) ($insuranceCompany->name ?? '')) !== '' ? trim((string) $insuranceCompany->name) : 'Insurer',
                            'priority' => 1,
                            'authorization_status' => $insuranceAuthorization['authorization_status'] ?? 'auto_approved',
                            'breakdown' => is_array($insuranceAuthorization['breakdown'] ?? null)
                                ? $insuranceAuthorization['breakdown']
                                : [],
                        ];
                        $insuranceAuthorization['cascade_line_items'] = $this->buildCascadeLineItemRowsForSnapshot(
                            $itemsForAuthorization,
                            [$vendorSnapshotRow]
                        );
                        // Persist policy flags on snapshot when insurer omits them (used by UI / refresh).
                        $insuranceAuthorization['copay_contributes_to_deductible'] = $insuranceAuthorization['copay_contributes_to_deductible'] ?? (bool) $client->copay_contributes_to_deductible;
                        $insuranceAuthorization['coinsurance_contributes_to_deductible'] = $insuranceAuthorization['coinsurance_contributes_to_deductible'] ?? (bool) $client->coinsurance_contributes_to_deductible;
                        $invoice->update([
                            'insurance_authorization_reference' => $insuranceAuthorization['authorization_reference'] ?? null,
                            'insurance_client_total' => $insuranceAuthorization['client_total'] ?? null,
                            'insurance_insurance_total' => $insuranceAuthorization['insurance_total'] ?? null,
                            'insurance_confirmation_code' => $insuranceAuthorization['confirmation_code'] ?? null,
                            'insurance_authorized_at' => now(),
                            'insurance_authorization_snapshot' => $insuranceAuthorization,
                        ]);
                        Log::info('[Kashtre] Insurance authorization: invoice updated', [
                            'invoice_id' => $invoice->id,
                            'authorization_reference' => $insuranceAuthorization['authorization_reference'] ?? null,
                            'confirmation_code' => $insuranceAuthorization['confirmation_code'] ?? null,
                            'client_total' => $insuranceAuthorization['client_total'] ?? null,
                            'insurance_total' => $insuranceAuthorization['insurance_total'] ?? null,
                            'authorization_status' => $insuranceAuthorization['authorization_status'] ?? null,
                        ]);
                    } else {
                        Log::warning('[Kashtre] Insurance authorization: third-party returned no data or failed', [
                            'invoice_id' => $invoice->id,
                            'has_response' => (bool) $insuranceAuthorization,
                            'auth_message' => is_array($insuranceAuthorization) ? ($insuranceAuthorization['message'] ?? null) : null,
                        ]);
                    }
                } else {
                    Log::info('[Kashtre] Insurance authorization: skipped (no third_party_business_id)', [
                        'invoice_id' => $invoice->id,
                        'insurance_company_id' => $client->insurance_company_id,
                    ]);
                }
            }

            $responseData = [
                'success' => true,
                'message' => 'Invoice created successfully',
                'invoice' => $invoice,
                'next_invoice_number' => $nextInvoiceNumber,
            ];
            if ($deferInsuranceServiceQueue) {
                $responseData['service_queue_deferred'] = true;
            }
            if ($insuranceAuthorization && ! empty($insuranceAuthorization['success'])) {
                $authStatus = $insuranceAuthorization['authorization_status'] ?? 'auto_approved';

                // ── Third-party payer credit limit gate (conditional guarantee) ──
                // We only know the true insurer share after authorization returns insurance_total,
                // so we enforce Prudential's (third-party payer) limit here.
                try {
                    $insurancePortion = (float) ($insuranceAuthorization['insurance_total'] ?? 0);
                    $isGracePeriod = (bool) ($insuranceAuthorization['grace_period']['active'] ?? false);
                    if (! $isGracePeriod && $authStatus !== 'auto_rejected' && $insurancePortion > 0) {
                        $thirdPartyPayer = \App\Models\ThirdPartyPayer::where('insurance_company_id', $client->insurance_company_id)
                            ->where('business_id', $business->id)
                            ->where('type', 'insurance_company')
                            ->whereNull('client_id')
                            ->where('status', 'active')
                            ->first();

                        if ($thirdPartyPayer) {
                            $payerLimit = (float) ($thirdPartyPayer->credit_limit ?? 0);
                            $businessLimit = (float) ($business->max_third_party_credit_limit ?? 0);
                            $effectiveLimit = $payerLimit > 0 ? $payerLimit : $businessLimit;

                            if ($effectiveLimit > 0) {
                                $currentOutstanding = (float) \App\Models\AccountsReceivable::where('third_party_payer_id', $thirdPartyPayer->id)
                                    ->where('status', '!=', 'paid')
                                    ->sum('balance');

                                $newExposure = $currentOutstanding + $insurancePortion;

                                if ($newExposure > $effectiveLimit) {
                                    $authStatus = 'auto_rejected';

                                    $warning = "Insurance credit limit exceeded. Outstanding {$currentOutstanding}, requested guarantee {$insurancePortion}, limit {$effectiveLimit}.";
                                    $insuranceAuthorization['warnings'] = array_values(array_unique(array_merge(
                                        (array) ($insuranceAuthorization['warnings'] ?? []),
                                        [$warning]
                                    )));
                                    $insuranceAuthorization['authorization_status'] = 'auto_rejected';
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('[Kashtre] Insurance credit-limit gate failed (skipping)', [
                        'invoice_id' => $invoice->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // If insurer rejected, treat full amount as client-pay and drop insurance as a payer
                if ($authStatus === 'auto_rejected') {
                    $fullAmount = (float) $invoice->total_amount;

                    // Update authorization snapshot to reflect client paying everything
                    $snapshot = $insuranceAuthorization;
                    $snapshot['client_total'] = $fullAmount;
                    $snapshot['insurance_total'] = 0.0;
                    $snapshot['breakdown'] = $snapshot['breakdown'] ?? [];
                    $snapshot['breakdown']['deductible'] = 0.0;
                    $snapshot['breakdown']['copay'] = 0.0;
                    $snapshot['breakdown']['coinsurance'] = 0.0;

                    // Update invoice fields
                    $invoice->update([
                        'insurance_client_total' => $fullAmount,
                        'insurance_insurance_total' => 0.0,
                        'insurance_authorization_snapshot' => $snapshot,
                    ]);

                    // Remove insurer Accounts Receivable entry for this invoice (since insurer won't pay)
                    try {
                        $thirdPartyPayerForAr = \App\Models\ThirdPartyPayer::where('insurance_company_id', $client->insurance_company_id)
                            ->where('business_id', $business->id)
                            ->where('type', 'insurance_company')
                            ->whereNull('client_id')
                            ->where('status', 'active')
                            ->first();

                        if ($thirdPartyPayerForAr) {
                            \App\Models\AccountsReceivable::where('invoice_id', $invoice->id)
                                ->where('third_party_payer_id', $thirdPartyPayerForAr->id)
                                ->delete();
                        }
                    } catch (\Throwable $e) {
                        Log::warning('[Kashtre] Failed to remove insurer AR after rejection', [
                            'invoice_id' => $invoice->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    // Remove insurance from payment methods and fall back to client methods
                    $paymentMethods = $invoice->payment_methods ?? [];
                    if (is_array($paymentMethods)) {
                        $paymentMethods = array_values(array_filter($paymentMethods, function ($m) {
                            return $m !== 'insurance';
                        }));
                    } else {
                        $paymentMethods = [];
                    }
                    if (empty($paymentMethods)) {
                        $paymentMethods = ['cash'];
                    }
                    $invoice->update(['payment_methods' => $paymentMethods]);

                    $insuranceAuthorization = $snapshot;
                }

                // Full-cover insurance should post insurer guarantee immediately on save/confirm.
                // This keeps vendor financial statements in sync even when client_total is zero.
                try {
                    $authorizedClientTotal = (float) ($insuranceAuthorization['client_total'] ?? 0);
                    $authorizedInsuranceTotal = (float) ($insuranceAuthorization['insurance_total'] ?? 0);

                    if ($authStatus !== 'auto_rejected' && $authorizedInsuranceTotal > 0 && $authorizedClientTotal <= 0.01) {
                        $this->postImmediateInsuranceGuaranteeDebits($invoice, $client, $business, $insuranceAuthorization);
                    }
                } catch (\Throwable $e) {
                    Log::warning('[Kashtre] Immediate insurer guarantee posting failed (non-blocking)', [
                        'invoice_id' => $invoice->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $responseData['authorization_status'] = $authStatus;
                // For multi-vendor: client payment is the FINAL client_total (after all vendors cascade)
                // For single-vendor: client payment is the standard client_total
                $isMultiVendor = ! empty($insuranceAuthorization['multi_vendor']);
                $responseData['is_multi_vendor'] = $isMultiVendor;
                // Only show payment prompt if there's a client portion to collect
                // For multi-vendor, this is the true final amount after cascade complete
                $responseData['requires_insurance_client_payment'] = ((float) ($insuranceAuthorization['client_total'] ?? 0)) > 0;
                $responseData['insurance_authorization'] = $insuranceAuthorization;
                $responseData['client_total'] = (float) ($insuranceAuthorization['client_total'] ?? 0);
                $responseData['insurance_total'] = (float) ($insuranceAuthorization['insurance_total'] ?? 0);
                $responseData['breakdown'] = $insuranceAuthorization['breakdown'] ?? null;
                $responseData['insurance_authorization_reference'] = $insuranceAuthorization['authorization_reference'] ?? null;
                $responseData['confirmation_code'] = $insuranceAuthorization['confirmation_code'] ?? null;
                $responseData['warnings'] = $insuranceAuthorization['warnings'] ?? [];
                $responseData['payment_methods'] = $invoice->payment_methods;
                if (isset($insuranceAuthorization['service_category'])) {
                    $responseData['service_category'] = $insuranceAuthorization['service_category'];
                }
                if (isset($insuranceAuthorization['amount_that_reduces_deductible'])) {
                    $responseData['amount_that_reduces_deductible'] = (float) $insuranceAuthorization['amount_that_reduces_deductible'];
                }
                if (isset($insuranceAuthorization['copay_contributes_to_deductible'])) {
                    $responseData['copay_contributes_to_deductible'] = (bool) $insuranceAuthorization['copay_contributes_to_deductible'];
                }
                if (isset($insuranceAuthorization['coinsurance_contributes_to_deductible'])) {
                    $responseData['coinsurance_contributes_to_deductible'] = (bool) $insuranceAuthorization['coinsurance_contributes_to_deductible'];
                }
                if (isset($insuranceAuthorization['policy_options'])) {
                    $responseData['policy_options'] = $insuranceAuthorization['policy_options'];
                }

                $invoice->refresh();
                if (is_array($invoice->insurance_authorization_snapshot)) {
                    $responseData['insurance_authorization'] = $invoice->insurance_authorization_snapshot;
                }
                $invoice->load('vendorPortionInvoices');
                if ($invoice->vendorPortionInvoices->isNotEmpty()) {
                    $responseData['vendor_portion_invoices'] = $invoice->vendorPortionInvoices->map(static function ($p) {
                        return [
                            'id' => $p->id,
                            'invoice_number' => $p->invoice_number,
                            'vendor_portion_label' => $p->vendor_portion_label,
                            'total_amount' => (float) $p->total_amount,
                        ];
                    })->values();
                }
            } elseif ($insuranceAuthorization && empty($insuranceAuthorization['success']) && isset($insuranceAuthorization['message'])) {
                // Surface authorization failure reason to the frontend (e.g. "Policy is not active.")
                $responseData['insurance_authorization_failed'] = true;
                $responseData['insurance_authorization_error'] = $insuranceAuthorization['message'];
            }

            return response()->json($responseData);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create invoice: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Post insurer guarantee debits immediately for full-cover approvals (client_total ~= 0).
     * Duplicate-safe: checks existing invoice+description debit before creating.
     */
    private function postImmediateInsuranceGuaranteeDebits(Invoice $invoice, Client $client, $business, array $authorization): void
    {
        // Ensure the client statement always has an insurance tracking row
        // even when earlier insurance-tracking creation paths are skipped.
        $this->ensureClientInsuranceTrackingEntry($invoice, $client, $authorization);

        if (app(\App\Services\ThirdPartyInsuranceVendorLedgerService::class)
            ->postInsuranceVendorDebits($invoice, (int) $business->id, (int) $client->id)) {
            return;
        }

        $vendors = [];
        if (! empty($authorization['multi_vendor']) && ! empty($authorization['vendors']) && is_array($authorization['vendors'])) {
            $vendors = $authorization['vendors'];
        } else {
            $vendors[] = [
                'vendor_name' => null,
                'insurance_total' => (float) ($authorization['insurance_total'] ?? 0),
                'authorization_status' => $authorization['authorization_status'] ?? 'auto_approved',
            ];
        }

        foreach ($vendors as $vendor) {
            $vendorStatus = (string) ($vendor['authorization_status'] ?? '');
            if (in_array($vendorStatus, ['failed', 'skipped', 'auto_rejected'], true)) {
                continue;
            }

            $insurancePortion = (float) ($vendor['insurance_total'] ?? 0);
            if ($insurancePortion <= 0) {
                continue;
            }

            $vendorName = $vendor['vendor_name'] ?? $vendor['insurance_company_name'] ?? null;

            $thirdPartyPayer = null;
            if (! empty($vendorName)) {
                $thirdPartyPayer = \App\Models\ThirdPartyPayer::where('name', $vendorName)
                    ->where('business_id', $business->id)
                    ->where('type', 'insurance_company')
                    ->whereNull('client_id')
                    ->first();
            }

            if (! $thirdPartyPayer && $client->insurance_company_id) {
                $thirdPartyPayer = \App\Models\ThirdPartyPayer::where('insurance_company_id', $client->insurance_company_id)
                    ->where('business_id', $business->id)
                    ->where('type', 'insurance_company')
                    ->whereNull('client_id')
                    ->first();
            }

            if (! $thirdPartyPayer) {
                continue;
            }

            $description = 'Insurance guarantee for invoice '.$invoice->invoice_number;
            $existingDebit = \App\Models\ThirdPartyPayerBalanceHistory::where('third_party_payer_id', $thirdPartyPayer->id)
                ->where('invoice_id', $invoice->id)
                ->where('transaction_type', 'debit')
                ->where('description', $description)
                ->first();

            if ($existingDebit) {
                continue;
            }

            \App\Models\ThirdPartyPayerBalanceHistory::recordDebit(
                $thirdPartyPayer,
                $insurancePortion,
                $description,
                $invoice->invoice_number,
                'Posted immediately on full-cover authorization',
                'insurance',
                $invoice->id,
                $client->id
            );

            Log::info('[Kashtre] Immediate insurer guarantee posted', [
                'invoice_id' => $invoice->id,
                'third_party_payer_id' => $thirdPartyPayer->id,
                'vendor_name' => $vendorName,
                'amount' => $insurancePortion,
            ]);
        }
    }

    /**
     * Create a single client-side insurance tracking statement row (no balance impact).
     */
    private function ensureClientInsuranceTrackingEntry(Invoice $invoice, Client $client, array $authorization): void
    {
        $existing = \App\Models\BalanceHistory::where('client_id', $client->id)
            ->where('invoice_id', $invoice->id)
            ->where('payment_method', 'insurance')
            ->first();

        if ($existing) {
            return;
        }

        $currentBalance = \App\Models\BalanceHistory::where('client_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->value('new_balance') ?? ($client->balance ?? 0);

        $moneyTracking = app(\App\Services\MoneyTrackingService::class);
        $payerBracketName = $moneyTracking->resolveInsuranceCounterpartyLabel($invoice);

        $items = collect($invoice->items ?? []);
        foreach ($items as $itemData) {
            $itemName = trim((string) ($itemData['name'] ?? $itemData['displayName'] ?? ''));
            if ($itemName === '') {
                continue;
            }

            $quantity = (int) ($itemData['quantity'] ?? 1);
            if ($quantity <= 0) {
                $quantity = 1;
            }

            \App\Models\BalanceHistory::create([
                'client_id' => $client->id,
                'business_id' => $client->business_id,
                'branch_id' => $client->branch_id,
                'invoice_id' => $invoice->id,
                'user_id' => auth()->id() ?? 1,
                'previous_balance' => $currentBalance,
                'change_amount' => 0,
                'new_balance' => $currentBalance,
                'transaction_type' => 'debit',
                'description' => "{$itemName} (x{$quantity}) [Insurance]",
                'reference_number' => $invoice->invoice_number,
                'notes' => "Insurance payment - {$itemName} (x{$quantity}) - Invoice #{$invoice->invoice_number} - Paid by {$payerBracketName}",
                'payment_method' => 'insurance',
                'payment_status' => 'paid',
            ]);
        }

        if ((float) ($invoice->service_charge ?? 0) > 0) {
            \App\Models\BalanceHistory::create([
                'client_id' => $client->id,
                'business_id' => $client->business_id,
                'branch_id' => $client->branch_id,
                'invoice_id' => $invoice->id,
                'user_id' => auth()->id() ?? 1,
                'previous_balance' => $currentBalance,
                'change_amount' => 0,
                'new_balance' => $currentBalance,
                'transaction_type' => 'debit',
                'description' => 'Service Fee [Insurance]',
                'reference_number' => $invoice->invoice_number,
                'notes' => "Insurance payment - Service Fee - Invoice #{$invoice->invoice_number} - Paid by {$payerBracketName}",
                'payment_method' => 'insurance',
                'payment_status' => 'paid',
            ]);
        }
    }

    /**
     * Build description with purchased items, client, and business information
     * Simplified to avoid XML special characters that cause parse errors
     */
    private function buildItemsDescription($items, $client = null, $business = null, $invoiceNumber = null)
    {
        // Keep it simple: Proforma Invoice {number}
        if ($invoiceNumber) {
            return "Proforma Invoice {$invoiceNumber}";
        }

        // If no invoice number, use client name
        if ($client) {
            return $client->name;
        }

        // Fallback
        return 'Services';
    }

    /**
     * Calculate service charge for a business
     */
    public function calculateServiceCharge($businessId, $subtotal)
    {
        // Get service charge settings for the business based on amount range
        // Order by lower_bound DESC to get the highest applicable range
        $serviceCharge = ServiceCharge::where('business_id', $businessId)
            ->where('entity_type', 'business')
            ->where('is_active', true)
            ->where('lower_bound', '<=', $subtotal)
            ->where('upper_bound', '>=', $subtotal)
            ->orderBy('lower_bound', 'desc')
            ->first();

        if (! $serviceCharge) {
            return 0; // No service charge configured for this amount range
        }

        // For fixed charges, return the amount directly
        if ($serviceCharge->type === 'fixed') {
            return $serviceCharge->amount;
        }

        // For percentage charges, calculate based on amount
        if ($serviceCharge->type === 'percentage') {
            return ($subtotal * $serviceCharge->amount) / 100;
        }

        return 0;
    }

    /**
     * Get service charge for AJAX request
     */
    public function getServiceCharge(Request $request)
    {
        $businessId = $request->input('business_id');
        $subtotal = $request->input('subtotal', 0);

        $serviceCharge = $this->calculateServiceCharge($businessId, $subtotal);

        return response()->json([
            'service_charge' => $serviceCharge,
            'formatted_service_charge' => 'UGX '.number_format($serviceCharge, 2),
        ]);
    }

    /**
     * Calculate service charge for AJAX request (new endpoint)
     */
    public function serviceCharge(Request $request)
    {
        try {
            $validated = $request->validate([
                'subtotal' => 'required|numeric|min:0',
                'business_id' => 'required|exists:businesses,id',
                'branch_id' => 'nullable|exists:branches,id',
            ]);

            $businessId = $validated['business_id'];
            $subtotal = $validated['subtotal'];
            $branchId = $validated['branch_id'] ?? null;

            // Try to get service charge for branch first, then business
            $serviceCharge = null;

            // Debug: Check what service charges exist
            $allServiceCharges = ServiceCharge::where('business_id', $businessId)->get();
            \Log::info('All service charges for business', [
                'business_id' => $businessId,
                'count' => $allServiceCharges->count(),
                'charges' => $allServiceCharges->toArray(),
            ]);

            if ($branchId) {
                $serviceCharge = ServiceCharge::where('business_id', $businessId)
                    ->where('entity_type', 'branch')
                    ->where('entity_id', $branchId)
                    ->where('is_active', true)
                    ->where('lower_bound', '<=', $subtotal)
                    ->where('upper_bound', '>=', $subtotal)
                    ->first();

                \Log::info('Branch service charge search', [
                    'branch_id' => $branchId,
                    'subtotal' => $subtotal,
                    'found' => $serviceCharge ? 'yes' : 'no',
                ]);
            }

            // If no branch service charge, try business level
            if (! $serviceCharge) {
                $serviceCharge = ServiceCharge::where('business_id', $businessId)
                    ->where('entity_type', 'business')
                    ->where('is_active', true)
                    ->where('lower_bound', '<=', $subtotal)
                    ->where('upper_bound', '>=', $subtotal)
                    ->orderBy('lower_bound', 'desc')
                    ->first();

                \Log::info('Business service charge search', [
                    'business_id' => $businessId,
                    'subtotal' => $subtotal,
                    'found' => $serviceCharge ? 'yes' : 'no',
                ]);
            }

            if (! $serviceCharge) {
                // Check if any service charge ranges exist for this business/branch
                $hasServiceChargeRanges = false;

                if ($branchId) {
                    // Check if branch has any service charge ranges
                    $hasServiceChargeRanges = ServiceCharge::where('business_id', $businessId)
                        ->where('entity_type', 'branch')
                        ->where('entity_id', $branchId)
                        ->where('is_active', true)
                        ->exists();
                }

                // If no branch ranges, check business level
                if (! $hasServiceChargeRanges) {
                    $hasServiceChargeRanges = ServiceCharge::where('business_id', $businessId)
                        ->where('entity_type', 'business')
                        ->where('is_active', true)
                        ->exists();
                }

                // Debug: Log what we're looking for
                \Log::info('No service charge found', [
                    'business_id' => $businessId,
                    'branch_id' => $branchId,
                    'subtotal' => $subtotal,
                    'searched_branch' => $branchId ? 'yes' : 'no',
                    'searched_business' => 'yes',
                    'has_service_charge_ranges' => $hasServiceChargeRanges,
                ]);

                return response()->json([
                    'success' => true,
                    'service_charge' => 0,
                    'has_service_charge_ranges' => $hasServiceChargeRanges,
                    'message' => $hasServiceChargeRanges
                        ? 'No service charge configured for this amount range. Please contact admin to set up service charges for this range.'
                        : 'No service charge configured. Please contact admin to set up service charges.',
                    'debug' => [
                        'business_id' => $businessId,
                        'branch_id' => $branchId,
                        'subtotal' => $subtotal,
                        'has_service_charge_ranges' => $hasServiceChargeRanges,
                    ],
                ]);
            }

            // Calculate service charge based on type
            $calculatedCharge = 0;

            if ($serviceCharge->type === 'percentage') {
                $calculatedCharge = ($subtotal * $serviceCharge->amount) / 100;
            } else {
                // For fixed charges, return the amount directly
                $calculatedCharge = $serviceCharge->amount;
            }

            return response()->json([
                'success' => true,
                'service_charge' => $calculatedCharge,
                'has_service_charge_ranges' => true, // Service charge ranges are configured
                'service_charge_info' => [
                    'type' => $serviceCharge->type,
                    'amount' => $serviceCharge->amount,
                    'lower_bound' => $serviceCharge->lower_bound,
                    'upper_bound' => $serviceCharge->upper_bound,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error calculating service charge: '.$e->getMessage(),
                'service_charge' => 0,
            ], 500);
        }
    }

    /**
     * Calculate balance adjustment for a client
     */
    public function calculateBalanceAdjustment(Request $request)
    {
        try {
            $validated = $request->validate([
                'client_id' => 'required|exists:clients,id',
                'total_amount' => 'required|numeric|min:0',
            ]);

            $client = Client::find($validated['client_id']);
            $availableBalance = $client->available_balance ?? 0;
            $totalBalance = $client->total_balance ?? 0;
            $suspenseBalance = $client->suspense_balance ?? 0;
            $totalAmount = $validated['total_amount'];

            // Calculate how much balance can be used (only from available balance)
            $balanceAdjustment = min($availableBalance, $totalAmount);

            return response()->json([
                'success' => true,
                'balance_adjustment' => $balanceAdjustment,
                'available_balance' => $availableBalance,
                'total_balance' => $totalBalance,
                'suspense_balance' => $suspenseBalance,
                'client_balance' => $availableBalance, // For backward compatibility
                'remaining_balance' => $availableBalance - $balanceAdjustment,
                'message' => $balanceAdjustment > 0 ? 'Balance adjustment applied' : 'No balance available for adjustment',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error calculating balance adjustment: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate next invoice number
     */
    public function generateInvoiceNumber(Request $request)
    {
        $businessId = $request->input('business_id');
        $invoiceNumber = Invoice::generateInvoiceNumber($businessId, 'proforma');

        return response()->json([
            'invoice_number' => $invoiceNumber,
        ]);
    }

    /**
     * Process mobile money payment with comprehensive description
     */
    public function processMobileMoneyPayment(Request $request)
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0',
                'phone_number' => 'required|string',
                'client_id' => 'required|exists:clients,id',
                'business_id' => 'required|exists:businesses,id',
                'items' => 'required|array',
                'invoice_number' => 'nullable|string',
            ]);

            $client = Client::find($validated['client_id']);
            $business = \App\Models\Business::find($validated['business_id']);

            // CREDIT CLIENTS: Skip payment prompts - they don't need to pay upfront
            if ($client->is_credit_eligible) {
                Log::info('=== CREDIT CLIENT - SKIPPING PAYMENT PROMPT ===', [
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'is_credit_eligible' => $client->is_credit_eligible,
                    'amount' => $validated['amount'],
                    'invoice_number' => $validated['invoice_number'],
                    'reason' => 'Credit clients do not receive payment prompts - items are offered on credit',
                ]);

                return response()->json([
                    'success' => true,
                    'transaction_id' => 'CREDIT-'.time(),
                    'status' => 'credit_client',
                    'message' => 'Credit client - No payment prompt sent. Items will be offered on credit.',
                    'credit_client' => true,
                    'skip_payment' => true,
                ]);
            }

            // Find the invoice if invoice_number is provided
            $invoice = null;
            if (! empty($validated['invoice_number'])) {
                $invoice = \App\Models\Invoice::where('invoice_number', $validated['invoice_number'])
                    ->where('client_id', $validated['client_id'])
                    ->where('business_id', $validated['business_id'])
                    ->first();

                if ($invoice) {
                    Log::info('Found invoice for mobile money payment', [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'client_id' => $client->id,
                    ]);
                } else {
                    Log::warning('Invoice not found for mobile money payment', [
                        'invoice_number' => $validated['invoice_number'],
                        'client_id' => $validated['client_id'],
                        'business_id' => $validated['business_id'],
                    ]);
                }
            }

            // Build comprehensive payment description
            $description = $this->buildItemsDescription(
                $validated['items'],
                $client,
                $business,
                $validated['invoice_number']
            );

            // Format phone number for mobile money API
            $phone = $validated['phone_number'];

            // Remove any non-numeric characters except + at the beginning
            $phone = preg_replace('/[^0-9+]/', '', $phone);

            // Handle different phone number formats
            if (str_starts_with($phone, '+256')) {
                // Remove the + prefix for YoAPI
                $phone = substr($phone, 1);
            } elseif (str_starts_with($phone, '256')) {
                // Already in correct format
                $phone = $phone;
            } elseif (str_starts_with($phone, '0')) {
                // Convert from local format (0XXXXXXXXX) to international (256XXXXXXXXX)
                $phone = '256'.substr($phone, 1);
            } else {
                // Assume it's already in international format without +
                $phone = $phone;
            }

            $liveYo = YoDepositGate::useLiveYoDeposits();

            Log::info('Mobile money payment attempt', [
                'original_phone' => $validated['phone_number'],
                'formatted_phone' => $phone,
                'amount' => $validated['amount'],
                'description' => $description,
                'description_length' => strlen($description),
                'client_id' => $validated['client_id'],
                'business_id' => $validated['business_id'],
                'yo_live_deposits' => $liveYo,
            ]);

            if (! $liveYo) {
                $result = [
                    'Status' => 'OK',
                    'TransactionReference' => 'SIM-MM-'.strtoupper(Str::random(12)),
                    'StatusMessage' => 'Simulated (YO_LIVE_DEPOSITS_ENABLED is not true). No handset prompt.',
                ];
                Log::info('Mobile money: skipping Yo ac_deposit_funds — creating pending transaction for simulate-success', [
                    'result' => $result,
                ]);
            } else {
                $yoPayments = new \App\Payments\YoAPI(config('payments.yo_username'), config('payments.yo_password'));
                $webhook = config('payments.webhook_url');
                if (! empty($webhook)) {
                    $yoPayments->set_instant_notification_url($webhook);
                }
                $yoPayments->set_external_reference(YoExternalReference::make('MM'));

                Log::info('=== YOAPI REQUEST PARAMETERS ===', [
                    'phone' => $phone,
                    'amount' => $validated['amount'],
                    'narrative' => $description,
                    'narrative_length' => strlen($description),
                    'webhook_url' => $webhook ?: 'not_set',
                    'credentials_configured' => [
                        'username' => config('payments.yo_username') ? substr((string) config('payments.yo_username'), 0, 3).'***' : 'MISSING',
                        'password' => config('payments.yo_password') ? '***SET***' : 'MISSING',
                    ],
                ]);

                $result = $yoPayments->ac_deposit_funds($phone, $validated['amount'], $description);
                Log::info('YoAPI actual response', ['result' => $result]);
            }

            // Check if payment request was initiated successfully
            if (isset($result['Status']) && $result['Status'] === 'OK' && isset($result['TransactionReference'])) {

                Log::info('Mobile money payment request initiated successfully', [
                    'transaction_reference' => $result['TransactionReference'],
                    'phone' => $phone,
                    'amount' => $validated['amount'],
                    'client_id' => $validated['client_id'],
                    'invoice_number' => $validated['invoice_number'],
                ]);

                // Determine payment status: PP for credit clients with balance due, otherwise Paid
                $paymentStatus = 'Paid';
                if ($invoice && $client->is_credit_eligible && $invoice->balance_due > 0) {
                    $paymentStatus = 'PP';
                }

                // Create transaction record for tracking
                $transaction = \App\Models\Transaction::create([
                    'business_id' => $validated['business_id'],
                    'branch_id' => $client->branch_id ?? null,
                    'client_id' => $validated['client_id'],
                    'invoice_id' => $invoice ? $invoice->id : null, // Link to invoice if found
                    'amount' => $validated['amount'],
                    'reference' => $validated['invoice_number'], // Use invoice number as reference
                    'external_reference' => $result['TransactionReference'], // Store YoAPI TransactionReference
                    'description' => $description,
                    'status' => 'pending',
                    'payment_status' => $paymentStatus,
                    'type' => 'debit',
                    'origin' => 'web',
                    'phone_number' => $validated['phone_number'],
                    'provider' => 'yo',
                    'service' => 'mobile_money_payment',
                    'date' => now(),
                    'currency' => strtoupper($business->currency_code ?? 'USD'),
                    'names' => $client->name,
                    'email' => null,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'method' => 'mobile_money',
                    'transaction_for' => 'main',
                ]);

                Log::info('Transaction record created for mobile money payment', [
                    'transaction_id' => $transaction->id,
                    'invoice_id' => $invoice ? $invoice->id : null,
                    'external_reference' => $result['TransactionReference'],
                    'status' => 'pending',
                ]);

                return response()->json([
                    'success' => true,
                    'transaction_id' => $result['TransactionReference'],
                    'status' => 'pending',
                    'message' => $liveYo
                        ? 'A payment prompt has been sent to your phone. Please complete the payment to proceed.'
                        : 'Mobile money payment is pending (simulated — Yo live deposits disabled). Use: php artisan payments:simulate-success --transaction='.$transaction->id,
                    'description' => $description,
                    'yoapi_response' => $result,
                    'internal_transaction_id' => $transaction->id,
                    'yo_simulated' => ! $liveYo,
                ]);
            } else {
                $errorMessage = isset($result['StatusMessage']) ? "Mobile Money payment failed: {$result['StatusMessage']}" :
                               (isset($result['ErrorMessage']) ? "Mobile Money payment failed: {$result['ErrorMessage']}" :
                               'Mobile Money payment failed: Unknown error.');

                Log::error('Mobile money payment failed', [
                    'result' => $result,
                    'phone' => $phone,
                    'amount' => $validated['amount'],
                    'error_message' => $errorMessage,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'yoapi_response' => $result,
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Mobile money payment error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error processing mobile money payment: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reinitiate a failed mobile money transaction
     */
    public function reinitiateFailedTransaction(Request $request)
    {
        try {
            Log::info('=== RETRY TRANSACTION REQUEST STARTED ===', [
                'timestamp' => now()->toDateTimeString(),
                'user_agent' => request()->userAgent(),
                'ip_address' => request()->ip(),
                'request_data' => $request->all(),
            ]);

            $validated = $request->validate([
                'transaction_id' => 'required|exists:transactions,id',
            ]);

            Log::info('Request validation passed', [
                'validated_transaction_id' => $validated['transaction_id'],
            ]);

            $transaction = \App\Models\Transaction::find($validated['transaction_id']);

            // Log transaction details
            Log::info('Transaction found for retry', [
                'transaction_id' => $transaction->id,
                'reference' => $transaction->reference,
                'status' => $transaction->status,
                'method' => $transaction->method,
                'provider' => $transaction->provider,
                'amount' => $transaction->amount,
                'phone_number' => $transaction->phone_number,
                'external_reference' => $transaction->external_reference,
                'client_id' => $transaction->client_id,
                'business_id' => $transaction->business_id,
                'invoice_id' => $transaction->invoice_id,
                'created_at' => $transaction->created_at,
                'updated_at' => $transaction->updated_at,
            ]);

            // Check if transaction is failed
            if ($transaction->status !== 'failed') {
                Log::warning('Retry attempted on non-failed transaction', [
                    'transaction_id' => $transaction->id,
                    'current_status' => $transaction->status,
                    'expected_status' => 'failed',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Transaction is not in failed status and cannot be reinitiated.',
                ], 400);
            }

            // Check if transaction is mobile money
            if ($transaction->method !== 'mobile_money' || $transaction->provider !== 'yo') {
                Log::warning('Retry attempted on non-YoAPI mobile money transaction', [
                    'transaction_id' => $transaction->id,
                    'method' => $transaction->method,
                    'provider' => $transaction->provider,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Only YoAPI mobile money transactions can be reinitiated.',
                ], 400);
            }

            Log::info('Reinitiating failed transaction', [
                'transaction_id' => $transaction->id,
                'reference' => $transaction->reference,
                'external_reference' => $transaction->external_reference,
                'amount' => $transaction->amount,
                'client_id' => $transaction->client_id,
            ]);

            // Get client and business
            $client = $transaction->client;
            $business = $transaction->business;

            // CREDIT CLIENTS: Skip payment prompts - they don't need to pay upfront
            if ($client && $client->is_credit_eligible) {
                Log::info('=== CREDIT CLIENT - SKIPPING PAYMENT PROMPT (REINITIATE) ===', [
                    'transaction_id' => $transaction->id,
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'is_credit_eligible' => $client->is_credit_eligible,
                    'reason' => 'Credit clients do not receive payment prompts - items are offered on credit',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Credit clients do not receive payment prompts. Items are offered on credit.',
                    'credit_client' => true,
                    'skip_payment' => true,
                ], 400);
            }

            if (! $client || ! $business) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client or business not found for this transaction.',
                ], 400);
            }

            // Format phone number for mobile money API
            $phone = $transaction->phone_number;

            // Remove any non-numeric characters except + at the beginning
            $phone = preg_replace('/[^0-9+]/', '', $phone);

            // Handle different phone number formats
            if (str_starts_with($phone, '+256')) {
                // Remove the + prefix for YoAPI
                $phone = substr($phone, 1);
            } elseif (str_starts_with($phone, '256')) {
                // Already in correct format
                $phone = $phone;
            } elseif (str_starts_with($phone, '0')) {
                // Convert from local format (0XXXXXXXXX) to international (256XXXXXXXXX)
                $phone = '256'.substr($phone, 1);
            } else {
                // Assume it's already in international format without +
                $phone = $phone;
            }

            // Generate a new unique external reference for the retry
            // This prevents YoAPI duplicate transaction errors
            $newExternalReference = YoExternalReference::make('RTRY');

            $liveYoRetry = YoDepositGate::useLiveYoDeposits();

            Log::info('Reinitiating mobile money payment', [
                'original_phone' => $transaction->phone_number,
                'formatted_phone' => $phone,
                'amount' => $transaction->amount,
                'description' => $transaction->description,
                'client_id' => $transaction->client_id,
                'business_id' => $transaction->business_id,
                'old_external_reference' => $transaction->external_reference,
                'new_external_reference' => $newExternalReference,
                'transaction_reference' => $transaction->reference,
                'yo_live_deposits' => $liveYoRetry,
            ]);

            if (! $liveYoRetry) {
                $result = [
                    'Status' => 'OK',
                    'TransactionReference' => 'SIM-RTRY-'.strtoupper(Str::random(10)),
                    'StatusMessage' => 'Simulated retry (Yo live deposits disabled).',
                ];
                Log::info('Retry: skipping Yo ac_deposit_funds (simulated)', ['result' => $result]);
            } else {
                $yoPayments = new \App\Payments\YoAPI(config('payments.yo_username'), config('payments.yo_password'));
                $webhookRetry = config('payments.webhook_url');
                if (! empty($webhookRetry)) {
                    $yoPayments->set_instant_notification_url($webhookRetry);
                }
                $yoPayments->set_external_reference($newExternalReference);

                Log::info('Calling YoAPI ac_deposit_funds (retry)', [
                    'phone' => $phone,
                    'amount' => $transaction->amount,
                    'description' => $transaction->description,
                ]);

                $result = $yoPayments->ac_deposit_funds($phone, $transaction->amount, $transaction->description);
                Log::info('YoAPI reinitiation response received', [
                    'result' => $result,
                    'response_type' => gettype($result),
                    'response_size' => is_string($result) ? strlen($result) : (is_array($result) ? count($result) : 'unknown'),
                ]);
            }

            // Check if payment request was initiated successfully
            Log::info('Checking YoAPI response for success', [
                'has_status' => isset($result['Status']),
                'status_value' => $result['Status'] ?? 'not_set',
                'has_transaction_reference' => isset($result['TransactionReference']),
                'transaction_reference' => $result['TransactionReference'] ?? 'not_set',
                'full_response' => $result,
            ]);

            if (isset($result['Status']) && $result['Status'] === 'OK' && isset($result['TransactionReference'])) {

                Log::info('✅ YoAPI retry SUCCESS - Failed transaction reinitiated successfully', [
                    'old_transaction_id' => $transaction->id,
                    'new_transaction_reference' => $result['TransactionReference'],
                    'phone' => $phone,
                    'amount' => $transaction->amount,
                    'client_id' => $transaction->client_id,
                    'business_id' => $transaction->business_id,
                ]);

                // Update the existing transaction with new external reference and reset status
                // This updates the SAME transaction, does NOT create a new one
                $oldExternalReference = $transaction->external_reference;

                $transaction->update([
                    'external_reference' => $result['TransactionReference'],
                    'status' => 'pending',
                    'updated_at' => now(),
                ]);

                Log::info('✅ Existing transaction updated (NOT creating new transaction)', [
                    'transaction_id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'old_external_reference' => $oldExternalReference,
                    'our_new_external_reference' => $newExternalReference,
                    'yoapi_transaction_reference' => $result['TransactionReference'],
                    'status_changed_from' => 'failed',
                    'status_changed_to' => 'pending',
                ]);

                // Update related invoice if exists
                if ($transaction->invoice_id) {
                    $invoice = \App\Models\Invoice::find($transaction->invoice_id);
                    if ($invoice) {
                        $invoice->update([
                            'status' => 'pending',
                            'payment_status' => 'pending',
                        ]);

                        Log::info('Invoice status updated to pending for reinitiated transaction', [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'transaction_id' => $transaction->id,
                        ]);
                    }
                }

                return response()->json([
                    'success' => true,
                    'transaction_id' => $result['TransactionReference'],
                    'status' => 'pending',
                    'message' => $liveYoRetry
                        ? 'Transaction reinitiated successfully. A payment prompt has been sent to your phone. Please complete the payment to proceed.'
                        : 'Transaction reinitiated as pending (simulated). Run: php artisan payments:simulate-success --transaction='.$transaction->id,
                    'description' => $transaction->description,
                    'yoapi_response' => $result,
                    'internal_transaction_id' => $transaction->id,
                    'yo_simulated' => ! $liveYoRetry,
                ]);
            } else {
                $errorMessage = isset($result['StatusMessage']) ? "Transaction reinitiation failed: {$result['StatusMessage']}" :
                               (isset($result['ErrorMessage']) ? "Transaction reinitiation failed: {$result['ErrorMessage']}" :
                               'Transaction reinitiation failed: Unknown error.');

                Log::error('❌ YoAPI retry FAILED - Failed transaction reinitiation failed', [
                    'result' => $result,
                    'phone' => $phone,
                    'amount' => $transaction->amount,
                    'error_message' => $errorMessage,
                    'transaction_id' => $transaction->id,
                    'status_code' => $result['StatusCode'] ?? 'not_set',
                    'status_message' => $result['StatusMessage'] ?? 'not_set',
                    'transaction_status' => $result['TransactionStatus'] ?? 'not_set',
                    'error_message_field' => $result['ErrorMessage'] ?? 'not_set',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'yoapi_response' => $result,
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('💥 EXCEPTION in retry transaction', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'transaction_id' => $transactionId ?? 'unknown',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error reinitiating transaction: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reinitiate a failed invoice (all failed transactions associated with it)
     */
    public function reinitiateFailedInvoice(Request $request)
    {
        try {
            $validated = $request->validate([
                'invoice_id' => 'required|exists:invoices,id',
            ]);

            $invoice = \App\Models\Invoice::find($validated['invoice_id']);

            // Check if invoice has failed transactions
            $failedTransactions = $invoice->transactions()
                ->where('status', 'failed')
                ->where('method', 'mobile_money')
                ->where('provider', 'yo')
                ->get();

            if ($failedTransactions->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No failed mobile money transactions found for this invoice.',
                ], 400);
            }

            Log::info('Reinitiating failed invoice', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'failed_transactions_count' => $failedTransactions->count(),
            ]);

            $reinitiatedCount = 0;
            $errors = [];
            $liveYoInvoice = YoDepositGate::useLiveYoDeposits();

            foreach ($failedTransactions as $transaction) {
                try {
                    // Use the same logic as reinitiateFailedTransaction but without the validation
                    $client = $transaction->client;
                    $business = $transaction->business;

                    if (! $client || ! $business) {
                        $errors[] = "Client or business not found for transaction {$transaction->id}";

                        continue;
                    }

                    // Format phone number for mobile money API
                    $phone = $transaction->phone_number;
                    $phone = preg_replace('/[^0-9+]/', '', $phone);

                    if (str_starts_with($phone, '+256')) {
                        $phone = substr($phone, 1);
                    } elseif (str_starts_with($phone, '0')) {
                        $phone = '256'.substr($phone, 1);
                    }

                    if (! $liveYoInvoice) {
                        $result = [
                            'Status' => 'OK',
                            'TransactionReference' => 'SIM-INV-'.$transaction->id.'-'.strtoupper(Str::random(6)),
                            'StatusMessage' => 'Simulated (Yo live deposits disabled).',
                        ];
                    } else {
                        $yoPayments = new \App\Payments\YoAPI(config('payments.yo_username'), config('payments.yo_password'));
                        $wh = config('payments.webhook_url');
                        if (! empty($wh)) {
                            $yoPayments->set_instant_notification_url($wh);
                        }
                        $yoPayments->set_external_reference(YoExternalReference::make('MM'));
                        $result = $yoPayments->ac_deposit_funds($phone, $transaction->amount, $transaction->description);
                    }

                    if (isset($result['Status']) && $result['Status'] === 'OK' && isset($result['TransactionReference'])) {
                        // Update the transaction with new external reference and reset status
                        $transaction->update([
                            'external_reference' => $result['TransactionReference'],
                            'status' => 'pending',
                            'updated_at' => now(),
                        ]);

                        $reinitiatedCount++;

                        Log::info('Transaction reinitiated successfully in invoice reinitiation', [
                            'transaction_id' => $transaction->id,
                            'new_external_reference' => $result['TransactionReference'],
                        ]);
                    } else {
                        $errorMessage = isset($result['StatusMessage']) ? $result['StatusMessage'] :
                                       (isset($result['ErrorMessage']) ? $result['ErrorMessage'] : 'Unknown error');
                        $errors[] = "Transaction {$transaction->id}: {$errorMessage}";
                    }

                } catch (\Exception $e) {
                    $errors[] = "Transaction {$transaction->id}: {$e->getMessage()}";
                    Log::error("Error reinitiating transaction {$transaction->id} in invoice reinitiation", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Update invoice status if at least one transaction was reinitiated
            if ($reinitiatedCount > 0) {
                $invoice->update([
                    'status' => 'pending',
                    'payment_status' => 'pending',
                ]);

                Log::info('Invoice status updated to pending after reinitiation', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'reinitiated_transactions' => $reinitiatedCount,
                ]);
            }

            $response = [
                'success' => $reinitiatedCount > 0,
                'reinitiated_count' => $reinitiatedCount,
                'total_failed_transactions' => $failedTransactions->count(),
                'message' => $reinitiatedCount > 0 ?
                    "Successfully reinitiated {$reinitiatedCount} transaction(s) for invoice {$invoice->invoice_number}" :
                    "Failed to reinitiate any transactions for invoice {$invoice->invoice_number}",
            ];

            if (! empty($errors)) {
                $response['errors'] = $errors;
            }

            return response()->json($response, $reinitiatedCount > 0 ? 200 : 400);

        } catch (\Exception $e) {
            Log::error('Invoice reinitiation error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error reinitiating invoice: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send receipts for an invoice (testing purposes)
     */
    public function sendReceipts(Invoice $invoice)
    {
        try {
            $user = Auth::user();

            // Check if user has access to this invoice
            if ($user->business_id !== 1 && $invoice->business_id !== $user->business_id) {
                abort(403, 'Unauthorized access to invoice.');
            }

            Log::info('=== MANUAL RECEIPT SENDING TEST STARTED ===', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'user_id' => $user->id,
                'client_email' => $invoice->client->email ?? 'no email',
                'business_email' => $invoice->business->email ?? 'no email',
                'kashtre_business_id' => 1,
                'service_charge' => $invoice->service_charge ?? 0,
                'mail_config' => [
                    'driver' => config('mail.default'),
                    'host' => config('mail.mailers.smtp.host'),
                    'port' => config('mail.mailers.smtp.port'),
                    'username' => config('mail.mailers.smtp.username'),
                    'kashtre_email' => config('mail.kashtre_email'),
                ],
            ]);

            $receiptService = new \App\Services\ReceiptService();
            $result = $receiptService->sendElectronicReceipts($invoice);

            Log::info('=== MANUAL RECEIPT SENDING TEST COMPLETED ===', [
                'invoice_id' => $invoice->id,
                'result' => $result ? 'success' : 'failed',
            ]);

            return response()->json([
                'success' => $result,
                'message' => $result ? 'Receipts sent successfully' : 'Failed to send receipts',
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending receipts manually', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error sending receipts: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a listing of invoices
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $business = $user->business;

        $query = Invoice::where('business_id', $business->id)
            ->whereNull('parent_invoice_id')
            ->with(['client', 'branch', 'createdBy', 'vendorPortionInvoices']);

        // Filter by client if specified
        if ($request->has('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        // Filter by status if specified
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Filter by payment status if specified
        if ($request->has('payment_status') && $request->payment_status !== '') {
            $query->where('payment_status', $request->payment_status);
        }

        // Filter by date range if specified
        if ($request->has('date_filter') && $request->date_filter !== '') {
            switch ($request->date_filter) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'week':
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'month':
                    $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
                    break;
                case 'year':
                    $query->whereYear('created_at', now()->year);
                    break;
            }
        }

        // Search functionality
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%")
                    ->orWhere('client_phone', 'like', "%{$search}%");
            });
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('invoices.index', compact('invoices'));
    }

    /**
     * Display the specified invoice
     */
    public function show(Invoice $invoice)
    {
        $user = Auth::user();

        // Check if user has access to this invoice
        if ($user->business_id !== 1 && $invoice->business_id !== $user->business_id) {
            abort(403, 'Unauthorized access to invoice.');
        }

        // Fix existing credit client invoices that may be missing debit entries, accounts receivable, or have wrong payment_status
        $client = $invoice->client;
        if ($client) {
            // Refresh client to get latest data
            $client->refresh();

            if (! $invoice->parent_invoice_id && $client->is_credit_eligible && $invoice->balance_due > 0) {
                Log::info('=== AUTO-FIXING CREDIT CLIENT INVOICE ===', [
                    'invoice_id' => $invoice->id,
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'is_credit_eligible' => $client->is_credit_eligible,
                    'balance_due' => $invoice->balance_due,
                ]);

                // Update payment_status if needed
                if ($invoice->payment_status !== 'pending_payment') {
                    $invoice->update(['payment_status' => 'pending_payment']);
                    Log::info('Fixed invoice payment_status for credit client', [
                        'invoice_id' => $invoice->id,
                        'old_payment_status' => $invoice->getOriginal('payment_status'),
                        'new_payment_status' => 'pending_payment',
                    ]);
                }

                // Check if accounts receivable entry exists
                $accountsReceivable = \App\Models\AccountsReceivable::where('invoice_id', $invoice->id)
                    ->where('client_id', $client->id)
                    ->first();

                if (! $accountsReceivable) {
                    Log::info('Creating missing accounts receivable entry', [
                        'invoice_id' => $invoice->id,
                        'client_id' => $client->id,
                    ]);

                    $business = $invoice->business;
                    $accountsReceivable = \App\Models\AccountsReceivable::create([
                        'client_id' => $client->id,
                        'business_id' => $invoice->business_id,
                        'branch_id' => $invoice->branch_id,
                        'invoice_id' => $invoice->id,
                        'created_by' => $invoice->created_by ?? Auth::id(),
                        'amount_due' => $invoice->total_amount,
                        'amount_paid' => $invoice->amount_paid ?? 0,
                        'balance' => $invoice->balance_due,
                        'invoice_date' => $invoice->created_at->toDateString(),
                        'due_date' => $invoice->created_at->addDays($business->default_payment_terms_days ?? 30)->toDateString(),
                        'status' => ($invoice->amount_paid ?? 0) > 0 ? 'partial' : 'current',
                        'payer_type' => 'first_party',
                        'notes' => "Credit transaction - Invoice #{$invoice->invoice_number} (Auto-fixed)",
                    ]);

                    Log::info('Accounts receivable entry created', [
                        'accounts_receivable_id' => $accountsReceivable->id,
                    ]);
                }

                // Update client balance if needed (should be negative for credit clients with debt)
                $expectedBalance = ($client->balance ?? 0);
                $outstandingDebt = \App\Models\AccountsReceivable::where('client_id', $client->id)
                    ->where('status', '!=', 'paid')
                    ->sum('balance');

                // Calculate what the balance should be (negative = they owe)
                $calculatedBalance = -$outstandingDebt;

                if (abs($expectedBalance - $calculatedBalance) > 0.01) {
                    Log::info('Updating client balance to match outstanding debt', [
                        'client_id' => $client->id,
                        'old_balance' => $expectedBalance,
                        'new_balance' => $calculatedBalance,
                        'outstanding_debt' => $outstandingDebt,
                    ]);
                    $client->update(['balance' => $calculatedBalance]);
                }

                // Check if debit entries exist for all items in balance history
                $itemsCollection = collect($invoice->items ?? []);
                $existingDebits = \App\Models\BalanceHistory::where('client_id', $client->id)
                    ->where('invoice_id', $invoice->id)
                    ->where('transaction_type', 'debit')
                    ->get();

                // Create separate debit entries for each item if they don't exist
                $paymentMethods = $invoice->payment_methods ?? [];
                $primaryMethod = ! empty($paymentMethods) ? $paymentMethods[0] : 'credit';
                $debitCount = 0;

                foreach ($itemsCollection as $itemData) {
                    $itemId = $itemData['id'] ?? $itemData['item_id'] ?? null;
                    if (! $itemId) {
                        continue;
                    }

                    $item = \App\Models\Item::find($itemId);
                    if (! $item) {
                        continue;
                    }

                    $quantity = $itemData['quantity'] ?? 1;
                    $itemTotalAmount = $itemData['total_amount'] ?? ($itemData['price'] ?? $item->default_price ?? 0) * $quantity;

                    if ($itemTotalAmount <= 0) {
                        continue;
                    }

                    // Check if debit already exists for this specific item
                    $itemDisplayName = $item->name;
                    $itemDescription = "{$itemDisplayName} (x{$quantity})";
                    $existingItemDebit = $existingDebits->first(function ($debit) use ($itemDescription) {
                        return $debit->description === $itemDescription;
                    });

                    if (! $existingItemDebit) {
                        \App\Models\BalanceHistory::recordDebit(
                            $client,
                            $itemTotalAmount,
                            $itemDescription,
                            $invoice->invoice_number,
                            "Credit purchase - {$itemDisplayName} (x{$quantity}) - Invoice #{$invoice->invoice_number}",
                            $primaryMethod,
                            $invoice->id
                        );
                        $debitCount++;
                    }
                }

                // Create service charge debit if applicable
                if ($invoice->service_charge > 0) {
                    $existingServiceDebit = $existingDebits->first(function ($debit) {
                        return $debit->description === 'Service Fee';
                    });

                    if (! $existingServiceDebit) {
                        \App\Models\BalanceHistory::recordDebit(
                            $client,
                            $invoice->service_charge,
                            'Service Fee',
                            $invoice->invoice_number,
                            "Credit purchase - Service Fee - Invoice #{$invoice->invoice_number}",
                            $primaryMethod,
                            $invoice->id
                        );
                        $debitCount++;
                    }
                }

                if ($debitCount > 0) {
                    Log::info('Created missing debit entries for credit client invoice', [
                        'invoice_id' => $invoice->id,
                        'client_id' => $client->id,
                        'debit_entries_created' => $debitCount,
                    ]);
                }

                // Check if items were queued at service points
                $queuedItemsCount = \App\Models\ServiceDeliveryQueue::where('invoice_id', $invoice->id)->count();
                $nonDepositItems = $itemsCollection->reject(function ($item) {
                    $itemModel = \App\Models\Item::find($item['id'] ?? $item['item_id'] ?? null);

                    return $itemModel && $itemModel->type === 'deposit';
                })->values();

                if ($queuedItemsCount === 0 && $nonDepositItems->isNotEmpty()) {
                    Log::info('Items were not queued for credit client invoice - queuing now', [
                        'invoice_id' => $invoice->id,
                        'items_to_queue' => $nonDepositItems->count(),
                    ]);

                    try {
                        $this->queueItemsAtServicePoints($invoice, $nonDepositItems->toArray());
                        Log::info('Items queued successfully for credit client invoice (auto-fixed)', [
                            'invoice_id' => $invoice->id,
                            'items_queued' => $nonDepositItems->count(),
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Error queuing items for credit client invoice (auto-fix)', [
                            'invoice_id' => $invoice->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }

                Log::info('=== AUTO-FIX COMPLETED FOR CREDIT CLIENT INVOICE ===', [
                    'invoice_id' => $invoice->id,
                    'debit_entries_created' => $debitCount,
                    'accounts_receivable_created' => $accountsReceivable ? 'yes' : 'no',
                    'items_queued' => $queuedItemsCount > 0 ? 'yes' : 'no',
                ]);
            }
        }

        $this->repairVendorPortionTraceInvoicesIfNeeded($invoice);

        // Load the invoice with quotations relationship
        $invoice->load(['quotations', 'vendorPortionInvoices', 'parentInvoice.vendorPortionInvoices', 'client']);

        $inventoryUsageEvent = null;
        $canCollectUsagePayment = false;
        $usagePaymentMethods = [];
        $usageDefaultPhone = null;

        if ($invoice->isInventoryUsagePostpaid()) {
            $inventoryUsageEvent = \App\Models\InventoryUsageEvent::query()
                ->where('invoice_id', $invoice->id)
                ->with(['client:id,name,client_id,phone_number,payment_phone_number,is_credit_eligible'])
                ->first();

            if ($inventoryUsageEvent) {
                $usagePayments = app(\App\Services\Inventory\InventoryUsagePaymentService::class);
                $canCollectUsagePayment = $usagePayments->canCollect($inventoryUsageEvent);
                $usagePaymentMethods = $usagePayments->availablePaymentMethods((int) $invoice->business_id);
                $usageDefaultPhone = $invoice->payment_phone
                    ?: $inventoryUsageEvent->client?->payment_phone_number
                    ?: $inventoryUsageEvent->client?->phone_number;
            }
        }

        return view('invoices.show', compact('invoice', 'inventoryUsageEvent', 'canCollectUsagePayment', 'usagePaymentMethods', 'usageDefaultPhone'));
    }

    /**
     * Print invoice
     */
    public function print(Invoice $invoice)
    {
        $user = Auth::user();

        // Check if user has access to this invoice
        if ($user->business_id !== 1 && $invoice->business_id !== $user->business_id) {
            abort(403, 'Unauthorized access to invoice.');
        }

        // Mark as printed
        $invoice->markAsPrinted();

        $invoice->load(['business', 'branch', 'createdBy']);

        return view('invoices.print', \App\Support\DocumentViewData::merge(compact('invoice')));
    }

    /**
     * Cancel invoice
     */
    public function cancel(Invoice $invoice)
    {
        $user = Auth::user();

        // Check if user has access to this invoice
        if ($user->business_id !== 1 && $invoice->business_id !== $user->business_id) {
            abort(403, 'Unauthorized access to invoice.');
        }

        // Check if invoice can be cancelled
        if ($invoice->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Invoice is already cancelled.',
            ], 400);
        }

        try {
            $invoice->cancel();

            return response()->json([
                'success' => true,
                'message' => 'Invoice cancelled successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel invoice: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get insurance authorization details for an invoice (for Refresh on collect-client-portion modal).
     */
    /**
     * Queue invoice line items at service points after the POS user confirms insurance authorization.
     */
    public function completeInsuranceServiceDelivery(Invoice $invoice)
    {
        $user = Auth::user();
        if ($user->business_id !== 1 && $invoice->business_id !== $user->business_id) {
            abort(403, 'Unauthorized access to invoice.');
        }

        $invoice->load('client');
        $client = $invoice->client;
        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found for this invoice.',
            ], 422);
        }

        $existingCount = \App\Models\ServiceDeliveryQueue::where('invoice_id', $invoice->id)->count();
        if ($existingCount > 0) {
            return response()->json([
                'success' => true,
                'message' => 'Service items are already queued for this invoice.',
                'already_queued' => true,
            ]);
        }

        $nonDepositItems = collect($invoice->items ?? [])->filter(function ($item) {
            $name = \Illuminate\Support\Str::lower(trim((string) ($item['displayName'] ?? $item['name'] ?? $item['item_name'] ?? '')));

            return $name !== 'deposit';
        })->values();

        if ($nonDepositItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No items to queue on this invoice.',
            ], 422);
        }

        $hasInsuranceContext = is_array($invoice->insurance_authorization_snapshot)
            || \App\Models\ClientVendor::where('client_id', $client->id)->where('status', 'active')->exists()
            || $client->insurance_company_id;

        if (! $hasInsuranceContext) {
            return response()->json([
                'success' => false,
                'message' => 'This invoice is not part of an insurance authorization flow.',
            ], 422);
        }

        $insuranceCompanyId = $client->insurance_company_id;
        if (! $insuranceCompanyId) {
            $thirdPartyPayer = \App\Models\ThirdPartyPayer::where('business_id', $invoice->business_id)
                ->where('client_id', $client->id)
                ->where('type', 'insurance_company')
                ->where('status', 'active')
                ->first();
            $insuranceCompanyId = $thirdPartyPayer?->insurance_company_id;
        }

        try {
            $moneyTrackingService = new \App\Services\MoneyTrackingService();

            DB::transaction(function () use ($invoice, $nonDepositItems, $insuranceCompanyId, $moneyTrackingService) {
                $this->queueItemsAtServicePoints($invoice, $nonDepositItems->toArray(), $insuranceCompanyId);
                $moneyTrackingService->processSuspenseAccountMovements($invoice, $nonDepositItems->toArray());
            });

            Log::info('[Kashtre] Insurance service delivery queued after POS confirmation', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'items_queued' => $nonDepositItems->count(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Items queued at service points.',
                'queued_count' => \App\Models\ServiceDeliveryQueue::where('invoice_id', $invoice->id)->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[Kashtre] completeInsuranceServiceDelivery failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not queue items: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getInsuranceAuthorization(Invoice $invoice)
    {
        $user = Auth::user();
        if ($user->business_id !== 1 && $invoice->business_id !== $user->business_id) {
            abort(403, 'Unauthorized access to invoice.');
        }
        $snapshot = $invoice->insurance_authorization_snapshot;
        if (! $snapshot || ! is_array($snapshot)) {
            return response()->json([
                'success' => false,
                'message' => 'No insurance authorization data for this invoice.',
            ], 404);
        }
        $clientTotal = $this->normalizedInsuranceClientTotalFromSnapshot($invoice, $snapshot);

        return response()->json([
            'success' => true,
            'invoice' => ['id' => $invoice->id, 'total_amount' => $invoice->total_amount],
            'requires_insurance_client_payment' => $clientTotal > 0,
            'client_total' => $clientTotal,
            'insurance_total' => (float) ($snapshot['insurance_total'] ?? 0),
            'breakdown' => $snapshot['breakdown'] ?? null,
            'policy_options' => $snapshot['policy_options'] ?? null,
            'amount_that_reduces_deductible' => isset($snapshot['amount_that_reduces_deductible']) ? (float) $snapshot['amount_that_reduces_deductible'] : null,
            'copay_contributes_to_deductible' => $snapshot['copay_contributes_to_deductible'] ?? null,
            'coinsurance_contributes_to_deductible' => $snapshot['coinsurance_contributes_to_deductible'] ?? null,
        ]);
    }

    /**
     * Create package tracking records for package items
     */
    private function createPackageTrackingRecords($invoice, $items)
    {
        Log::info('=== STARTING PACKAGE TRACKING CREATION ===', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'total_items' => count($items),
        ]);

        // Use static variable to prevent multiple calls within same request
        static $processedInvoices = [];
        if (in_array($invoice->id, $processedInvoices)) {
            Log::info('⚠️ PACKAGE TRACKING ALREADY PROCESSED FOR THIS INVOICE IN THIS REQUEST', [
                'invoice_id' => $invoice->id,
                'processed_invoices' => $processedInvoices,
            ]);

            return;
        }

        // Check if package tracking records already exist for this invoice
        $existingRecords = \App\Models\PackageTracking::where('invoice_id', $invoice->id)->count();
        if ($existingRecords > 0) {
            Log::info('⚠️ PACKAGE TRACKING RECORDS ALREADY EXIST IN DATABASE', [
                'invoice_id' => $invoice->id,
                'existing_count' => $existingRecords,
            ]);
            $processedInvoices[] = $invoice->id;

            return;
        }

        // Mark this invoice as being processed
        $processedInvoices[] = $invoice->id;

        $packageTrackingCount = 0;

        foreach ($items as $index => $item) {
            Log::info("🔍 PROCESSING ITEM {$index} FOR PACKAGE TRACKING", [
                'item_id' => $item['id'] ?? $item['item_id'] ?? 'null',
                'item_data' => $item,
                'item_name' => $item['name'] ?? 'unknown',
            ]);

            $itemId = $item['id'] ?? $item['item_id'] ?? null;
            if (! $itemId) {
                Log::info('❌ SKIPPING ITEM - NO ID', ['item' => $item]);

                continue;
            }

            // Get the item from database to check if it's a package
            $itemModel = \App\Models\Item::find($itemId);
            if (! $itemModel) {
                Log::info('❌ SKIPPING ITEM - NOT FOUND IN DATABASE', ['item_id' => $itemId]);

                continue;
            }

            if ($itemModel->type !== 'package') {
                Log::info('❌ SKIPPING ITEM - NOT A PACKAGE', [
                    'item_id' => $itemId,
                    'item_type' => $itemModel->type,
                    'item_name' => $itemModel->name,
                ]);

                continue;
            }

            Log::info('✅ FOUND PACKAGE ITEM', [
                'item_id' => $itemId,
                'item_name' => $itemModel->name,
                'item_type' => $itemModel->type,
            ]);

            // Get included items for this package from package_items table
            $packageItems = $itemModel->packageItems()->with('includedItem')->get();
            if ($packageItems->isEmpty()) {
                continue;
            }

            $quantity = $item['quantity'] ?? 1;
            $packagePrice = $item['price'] ?? 0;

            // Create ONE package tracking record per package
            $packageTracking = \App\Models\PackageTracking::create([
                'business_id' => $invoice->business_id,
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'package_item_id' => $itemId,
                'total_quantity' => $quantity, // Total packages purchased
                'used_quantity' => 0,
                'remaining_quantity' => $quantity,
                'valid_from' => now()->toDateString(),
                'valid_until' => now()->addDays(365)->toDateString(), // Default 1 year validity
                'status' => 'active',
                'package_price' => $packagePrice,
                'notes' => "Package: {$itemModel->name}, Invoice: {$invoice->invoice_number}",
                'tracking_number' => 'PKG-'.$packageTrackingCount.'-'.now()->format('YmdHis'),
            ]);

            // Create package tracking items for each included item in the package
            foreach ($packageItems as $packageItem) {
                $includedItem = $packageItem->includedItem;
                $includedItemId = $includedItem->id;
                $maxQuantity = $packageItem->max_quantity ?? 1;
                $includedItemPrice = $includedItem->default_price ?? 0;

                // Calculate total quantity for this included item
                $totalQuantity = $maxQuantity * $quantity;

                // Create package tracking item record
                \App\Models\PackageTrackingItem::create([
                    'package_tracking_id' => $packageTracking->id,
                    'included_item_id' => $includedItemId,
                    'item_name' => $includedItem->name,
                    'item_price' => $includedItemPrice,
                    'max_quantity' => $maxQuantity,
                    'total_quantity' => $totalQuantity,
                    'used_quantity' => 0,
                    'remaining_quantity' => $totalQuantity,
                    'status' => 'active',
                ]);
            }

            $packageTrackingCount++;
        }

        Log::info('=== PACKAGE TRACKING CREATION COMPLETED ===', [
            'invoice_id' => $invoice->id,
            'package_tracking_count' => $packageTrackingCount,
        ]);
    }

    /**
     * Queue items at their respective service points
     *
     * @param  \App\Models\Invoice  $invoice
     * @param  array  $items
     * @param  int|null  $insuranceCompanyId  Optional insurance company ID to mark items as insurance items
     */
    private function queueItemsAtServicePoints($invoice, $items, $insuranceCompanyId = null)
    {
        $filteredItems = collect($items)->reject(function ($item) {
            $name = Str::lower(trim((string) ($item['displayName'] ?? $item['name'] ?? $item['item_name'] ?? '')));

            return $name === 'deposit';
        })->values();

        Log::info('=== QUEUEING ITEMS AT SERVICE POINTS STARTED ===', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'items_count' => $filteredItems->count(),
            'insurance_company_id' => $insuranceCompanyId,
            'items' => $filteredItems->toArray(),
        ]);

        if ($filteredItems->isEmpty()) {
            Log::info('No queueable items found after filtering deposits.', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ]);

            return;
        }

        // SRD §4.1 — paid goods land on the mapped End Store fulfillment queue (independent of service-point mapping).
        try {
            app(\App\Services\Inventory\InventoryFulfillmentIngestService::class)
                ->ingestFromInvoice($invoice, $filteredItems->all());
        } catch (\Throwable $e) {
            Log::error('Inventory fulfillment ingest failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }

        foreach ($filteredItems as $item) {
            $itemId = $item['id'] ?? $item['item_id'] ?? null;
            if (! $itemId) {
                Log::warning('Skipping item - no item ID', ['item' => $item]);

                continue;
            }

            // Get the item from database
            $itemModel = \App\Models\Item::find($itemId);
            if (! $itemModel) {
                Log::warning('Skipping item - item model not found', ['item_id' => $itemId]);

                continue;
            }

            $quantity = $item['quantity'] ?? 1;

            Log::info('Processing item for service point queuing', [
                'item_id' => $itemId,
                'item_name' => $itemModel->name,
                'item_type' => $itemModel->type,
                'service_point_id' => $itemModel->service_point_id,
                'quantity' => $quantity,
            ]);

            // Get service point through BranchServicePoint relationship (same logic as cron job)
            $branchServicePoint = $itemModel->branchServicePoints()
                ->where('business_id', $invoice->business_id)
                ->where('branch_id', $invoice->branch_id)
                ->first();

            Log::info('Found item model and branch service point', [
                'item_id' => $itemModel->id,
                'item_name' => $itemModel->name,
                'item_type' => $itemModel->type,
                'business_id' => $invoice->business_id,
                'branch_id' => $invoice->branch_id,
                'branch_service_point_found' => $branchServicePoint ? 'yes' : 'no',
                'service_point_id' => $branchServicePoint ? $branchServicePoint->service_point_id : null,
            ]);

            // Handle package items FIRST - packages use their own tracking system, not service point queuing
            if ($itemModel->type === 'package') {
                Log::info('Package item detected - using package tracking system instead of service point queuing', [
                    'package_item_id' => $itemId,
                    'package_name' => $itemModel->name,
                    'invoice_id' => $invoice->id,
                    'client_id' => $invoice->client_id,
                ]);

                // Packages are handled by package tracking logic, not service point queuing
                // No queuing action needed here
                continue; // Skip to next item
            }

            // Handle bulk items - bulk items don't have service points, use service point from one of their included items
            if ($itemModel->type === 'bulk') {
                Log::info('Processing bulk item', [
                    'bulk_item_id' => $itemId,
                    'bulk_name' => $itemModel->name,
                ]);

                // Get service point from one of the bulk item's contained items
                Log::info("Bulk items don't have service points, checking contained items for service point", [
                    'bulk_item_id' => $itemId,
                    'bulk_name' => $itemModel->name,
                ]);

                $bulkItems = $itemModel->bulkItems()->with('includedItem')->get();

                Log::info('Found bulk items for service point lookup', [
                    'bulk_item_id' => $itemId,
                    'included_items_count' => $bulkItems->count(),
                ]);

                $servicePointId = null;
                $selectedIncludedItem = null;

                // Find the first included item that has a service point for this business/branch
                foreach ($bulkItems as $bulkItem) {
                    $includedItem = $bulkItem->includedItem;

                    $includedItemBranchServicePoint = $includedItem->branchServicePoints()
                        ->where('business_id', $invoice->business_id)
                        ->where('branch_id', $invoice->branch_id)
                        ->first();

                    if ($includedItemBranchServicePoint && $includedItemBranchServicePoint->service_point_id) {
                        $servicePointId = $includedItemBranchServicePoint->service_point_id;
                        $selectedIncludedItem = $includedItem;
                        Log::info('Found service point from included item', [
                            'included_item_id' => $includedItem->id,
                            'included_item_name' => $includedItem->name,
                            'service_point_id' => $servicePointId,
                        ]);
                        break;
                    }
                }

                // Queue bulk item at the service point from its included item
                if ($servicePointId) {
                    Log::info("Creating service delivery queue for bulk item using included item's service point", [
                        'bulk_item_id' => $itemId,
                        'service_point_id' => $servicePointId,
                        'selected_included_item' => $selectedIncludedItem ? $selectedIncludedItem->name : 'Unknown',
                        'quantity' => $quantity,
                    ]);

                    $queueRecord = \App\Models\ServiceDeliveryQueue::create([
                        'business_id' => $invoice->business_id,
                        'branch_id' => $invoice->branch_id,
                        'service_point_id' => $servicePointId,
                        'invoice_id' => $invoice->id,
                        'client_id' => $invoice->client_id,
                        'insurance_company_id' => $insuranceCompanyId,
                        'item_id' => $itemId,
                        'item_name' => $item['name'] ?? $itemModel->name,
                        'quantity' => $quantity,
                        'price' => $item['price'] ?? $itemModel->default_price ?? 0,
                        'status' => 'pending',
                        'priority' => 'normal',
                        'notes' => "Bulk item queued at SP from included item: {$selectedIncludedItem->name}, Invoice: {$invoice->invoice_number}, Client: {$invoice->client_name}",
                        'queued_at' => now(),
                        'estimated_delivery_time' => now()->addHours(2), // Default 2 hours
                    ]);

                    Log::info("Service delivery queue created for bulk item using included item's service point", [
                        'queue_id' => $queueRecord->id,
                        'bulk_item_id' => $itemId,
                        'service_point_id' => $servicePointId,
                        'selected_included_item' => $selectedIncludedItem ? $selectedIncludedItem->name : 'Unknown',
                    ]);
                } else {
                    Log::info('No service point found for bulk item or any of its contained items, skipping queuing', [
                        'bulk_item_id' => $itemId,
                        'bulk_name' => $itemModel->name,
                        'business_id' => $invoice->business_id,
                        'branch_id' => $invoice->branch_id,
                    ]);
                }

                continue; // Skip to next item after handling bulk
            }

            // Handle regular items with service points (only for non-package, non-bulk items)
            if ($branchServicePoint && $branchServicePoint->service_point_id) {
                $existingQueue = \App\Models\ServiceDeliveryQueue::where('invoice_id', $invoice->id)
                    ->where('client_id', $invoice->client_id)
                    ->where('item_id', $itemId)
                    ->where('service_point_id', $branchServicePoint->service_point_id)
                    ->first();

                if ($existingQueue) {
                    Log::info('Regular item already queued for invoice, skipping duplicate', [
                        'invoice_id' => $invoice->id,
                        'queue_id' => $existingQueue->id,
                        'item_id' => $itemId,
                        'service_point_id' => $branchServicePoint->service_point_id,
                    ]);
                    continue;
                }

                Log::info('Creating service delivery queue for regular item', [
                    'item_id' => $itemId,
                    'service_point_id' => $branchServicePoint->service_point_id,
                    'quantity' => $quantity,
                ]);

                // Create service delivery queue record for the main item
                $queueRecord = \App\Models\ServiceDeliveryQueue::create([
                    'business_id' => $invoice->business_id,
                    'branch_id' => $invoice->branch_id,
                    'service_point_id' => $branchServicePoint->service_point_id,
                    'invoice_id' => $invoice->id,
                    'client_id' => $invoice->client_id,
                    'insurance_company_id' => $insuranceCompanyId,
                    'item_id' => $itemId,
                    'item_name' => $item['name'] ?? $itemModel->name,
                    'quantity' => $quantity,
                    'price' => $item['price'] ?? $itemModel->default_price ?? 0,
                    'status' => 'pending',
                    'priority' => 'normal',
                    'notes' => "Invoice: {$invoice->invoice_number}, Client: {$invoice->client_name}",
                    'queued_at' => now(),
                    'estimated_delivery_time' => now()->addHours(2), // Default 2 hours
                ]);

                Log::info('Service delivery queue created for regular item', [
                    'queue_id' => $queueRecord->id,
                    'item_id' => $itemId,
                    'service_point_id' => $branchServicePoint->service_point_id,
                ]);
            } else {
                Log::info('Regular item has no service point for this business/branch, skipping queuing', [
                    'item_id' => $itemId,
                    'item_name' => $itemModel->name,
                    'business_id' => $invoice->business_id,
                    'branch_id' => $invoice->branch_id,
                    'branch_service_point_found' => $branchServicePoint ? 'yes' : 'no',
                ]);
            }

            // Handle package items - queue each included item at its respective service point
            if ($itemModel->type === 'package') {
                Log::info('Processing package item', [
                    'package_item_id' => $itemId,
                    'package_name' => $itemModel->name,
                ]);

                $packageItems = $itemModel->packageItems()->with('includedItem')->get();

                foreach ($packageItems as $packageItem) {
                    $includedItem = $packageItem->includedItem;
                    $maxQuantity = $packageItem->max_quantity ?? 1;
                    $totalQuantity = $maxQuantity * $quantity;

                    // Get service point for included item through BranchServicePoint relationship
                    $includedBranchServicePoint = $includedItem->branchServicePoints()
                        ->where('business_id', $invoice->business_id)
                        ->where('branch_id', $invoice->branch_id)
                        ->first();

                    Log::info('Found included item service point', [
                        'included_item_id' => $includedItem->id,
                        'included_item_name' => $includedItem->name,
                        'business_id' => $invoice->business_id,
                        'branch_id' => $invoice->branch_id,
                        'branch_service_point_found' => $includedBranchServicePoint ? 'yes' : 'no',
                        'service_point_id' => $includedBranchServicePoint ? $includedBranchServicePoint->service_point_id : null,
                    ]);

                    // Only queue if the included item has a service point for this business/branch
                    if ($includedBranchServicePoint && $includedBranchServicePoint->service_point_id) {
                        Log::info('Creating service delivery queue for included item', [
                            'included_item_id' => $includedItem->id,
                            'service_point_id' => $includedBranchServicePoint->service_point_id,
                            'quantity' => $totalQuantity,
                        ]);

                        $queueRecord = \App\Models\ServiceDeliveryQueue::create([
                            'business_id' => $invoice->business_id,
                            'branch_id' => $invoice->branch_id,
                            'service_point_id' => $includedBranchServicePoint->service_point_id,
                            'invoice_id' => $invoice->id,
                            'client_id' => $invoice->client_id,
                            'insurance_company_id' => $insuranceCompanyId,
                            'item_id' => $includedItem->id,
                            'item_name' => $includedItem->name,
                            'quantity' => $totalQuantity,
                            'price' => $includedItem->default_price ?? 0,
                            'status' => 'pending',
                            'priority' => 'normal',
                            'notes' => "Package: {$itemModel->name}, Invoice: {$invoice->invoice_number}, Client: {$invoice->client_name}",
                            'queued_at' => now(),
                            'estimated_delivery_time' => now()->addHours(2), // Default 2 hours
                        ]);

                        Log::info('Service delivery queue created for included item', [
                            'queue_id' => $queueRecord->id,
                            'included_item_id' => $includedItem->id,
                            'service_point_id' => $includedBranchServicePoint->service_point_id,
                        ]);
                    } else {
                        Log::info('Included item has no service point for this business/branch, skipping queuing', [
                            'included_item_id' => $includedItem->id,
                            'included_item_name' => $includedItem->name,
                            'business_id' => $invoice->business_id,
                            'branch_id' => $invoice->branch_id,
                            'branch_service_point_found' => $includedBranchServicePoint ? 'yes' : 'no',
                        ]);
                    }
                }
            }
        }

        Log::info('=== QUEUEING ITEMS AT SERVICE POINTS COMPLETED ===', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'total_items_processed' => count($items),
        ]);
    }

    /**
     * Update package usage for items covered by packages
     */
    private function updatePackageUsage($invoice, $items)
    {
        $clientId = $invoice->client_id;
        $businessId = $invoice->business_id;

        // Get client's valid package tracking records
        $validPackages = \App\Models\PackageTracking::where('client_id', $clientId)
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->where('remaining_quantity', '>', 0)
            ->where('valid_until', '>=', now()->toDateString())
            ->with(['packageItem.packageItems.includedItem'])
            ->get();

        foreach ($items as $item) {
            $itemId = $item['id'] ?? $item['item_id'];
            $quantity = $item['quantity'] ?? 1;
            $remainingQuantity = $quantity;

            // Check if this item is included in any valid packages
            foreach ($validPackages as $packageTracking) {
                if ($remainingQuantity <= 0) {
                    break;
                }

                // Check if the current item is included in this package
                $packageItems = $packageTracking->packageItem->packageItems;

                foreach ($packageItems as $packageItem) {
                    if ($packageItem->included_item_id == $itemId) {
                        // Check max quantity constraint from package_items table
                        $maxQuantity = $packageItem->max_quantity ?? null;
                        $fixedQuantity = $packageItem->fixed_quantity ?? null;

                        // Determine how much quantity can be used from this package
                        $availableFromPackage = $packageTracking->remaining_quantity;

                        if ($maxQuantity !== null) {
                            // If max_quantity is set, limit by that
                            $availableFromPackage = min($availableFromPackage, $maxQuantity);
                        } elseif ($fixedQuantity !== null) {
                            // If fixed_quantity is set, use that
                            $availableFromPackage = min($availableFromPackage, $fixedQuantity);
                        }

                        // Calculate how much we can actually use
                        $quantityToUse = min($remainingQuantity, $availableFromPackage);

                        if ($quantityToUse > 0) {
                            // Update package tracking record
                            $packageTracking->useQuantity($quantityToUse);

                            $remainingQuantity -= $quantityToUse;

                            // If we've used all available quantity from this package, break
                            if ($remainingQuantity <= 0) {
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Update client balance when balance adjustment is used
     */
    private function updateClientBalance($client, $balanceAdjustment)
    {
        try {
            $currentBalance = $client->balance ?? 0;
            $newBalance = $currentBalance - $balanceAdjustment;

            // Ensure balance doesn't go negative
            $newBalance = max(0, $newBalance);

            $client->update(['balance' => $newBalance]);

            // DO NOT create balance statement immediately - will be created after payment completion
            Log::info('Balance adjustment used - balance statement will be created after payment completion', [
                'client_id' => $client->id,
                'balance_adjustment' => $balanceAdjustment,
                'invoice_id' => $this->currentInvoice->id ?? null,
            ]);

            Log::info("Client balance updated: Client ID {$client->id}, Adjustment: {$balanceAdjustment}, Old Balance: {$currentBalance}, New Balance: {$newBalance}");

        } catch (\Exception $e) {
            Log::error('Error updating client balance: '.$e->getMessage());
        }
    }

    /**
     * Manually complete a transaction and send receipts (for testing)
     */
    public function manuallyCompleteTransaction(Invoice $invoice)
    {
        try {
            $user = Auth::user();

            // Check if user has access to this invoice
            if ($user->business_id !== 1 && $invoice->business_id !== $user->business_id) {
                abort(403, 'Unauthorized access to invoice.');
            }

            Log::info('=== MANUAL TRANSACTION COMPLETION STARTED ===', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'user_id' => $user->id,
                'current_invoice_status' => $invoice->status,
                'current_payment_status' => $invoice->payment_status,
            ]);

            // Update invoice status to paid
            $invoice->update([
                'status' => 'paid',
                'payment_status' => 'paid',
            ]);

            Log::info('Invoice status updated to paid manually', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ]);

            // Update related transaction if exists
            $transaction = \App\Models\Transaction::where('invoice_id', $invoice->id)->first();
            if ($transaction) {
                $transaction->update([
                    'status' => 'completed',
                ]);

                Log::info('Transaction status updated to completed manually', [
                    'transaction_id' => $transaction->id,
                    'invoice_id' => $invoice->id,
                ]);
            }

            // Process payment completion (this will trigger receipts)
            $moneyTrackingService = new \App\Services\MoneyTrackingService();

            Log::info('=== PROCESSING PAYMENT COMPLETION MANUALLY ===', [
                'invoice_id' => $invoice->id,
                'items_count' => count($invoice->items ?? []),
            ]);

            $balanceStatements = $moneyTrackingService->processPaymentCompleted($invoice, $invoice->items);

            Log::info('=== MANUAL PAYMENT COMPLETION FINISHED ===', [
                'invoice_id' => $invoice->id,
                'balance_statements_count' => count($balanceStatements),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaction completed manually and receipts sent',
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'balance_statements_count' => count($balanceStatements),
            ]);

        } catch (\Exception $e) {
            Log::error('Error completing transaction manually', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error completing transaction: '.$e->getMessage(),
                'invoice_id' => $invoice->id,
            ], 500);
        }
    }

    /**
     * Test mail configuration
     */
    public function testMail()
    {
        try {
            \Log::info('=== MAIL CONFIGURATION TEST ===', [
                'driver' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'username' => config('mail.mailers.smtp.username'),
                'password_set' => ! empty(config('mail.mailers.smtp.password')),
                'kashtre_email' => config('mail.kashtre_email'),
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
            ]);

            // Send a simple test email
            \Mail::raw('This is a test email from KashTre system to verify mail configuration.', function ($message) {
                $message->to('test@example.com')
                    ->subject('KashTre Mail Test');
            });

            return response()->json([
                'success' => true,
                'message' => 'Mail test completed - check logs for configuration details',
                'config' => [
                    'driver' => config('mail.default'),
                    'host' => config('mail.mailers.smtp.host'),
                    'port' => config('mail.mailers.smtp.port'),
                    'username' => config('mail.mailers.smtp.username'),
                    'kashtre_email' => config('mail.kashtre_email'),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Mail configuration test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Mail test failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cascade invoice authorization across multiple vendors in priority order.
     *
     * Vendor 1 receives the full invoice amount. Whatever client_total they return
     * becomes the total_amount sent to Vendor 2, and so on. The final client_total
     * is what the client actually pays out-of-pocket.
     */
    private function runMultiVendorCascade(
        Invoice $invoice,
        $client,
        $business,
        \Illuminate\Support\Collection $clientVendors,
        float $deductibleRemaining = 0
    ): ?array {
        $apiService = new \App\Services\ThirdPartyApiService();

        // Build items payload once — same for every vendor in the cascade
        $itemsForAuthorization = collect($invoice->items ?? [])->map(function (array $item) {
            $itemId = $item['id'] ?? $item['item_id'] ?? null;
            $itemModel = $itemId ? \App\Models\Item::find($itemId) : null;
            $quantity = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['price'] ?? 0);
            $total = (float) ($item['total_amount'] ?? ($price * $quantity));

            return [
                'id' => $itemId,
                'name' => $item['name'] ?? $item['displayName'] ?? null,
                'quantity' => $quantity,
                'price' => $price,
                'total_amount' => $total,
                'code' => $itemModel?->code,
                'type' => $itemModel?->type,
                'kashtre_excluded' => ! empty($item['kashtre_excluded']),
            ];
        })->values()->all();

        $vendorResults = [];
        $amountForNextVendor = (float) $invoice->total_amount;
        $totalInsuranceTotal = 0.0;
        $finalClientTotal = 0.0;
        $allWarnings = [];
        $cascadeBlocked = false;

        foreach ($clientVendors as $clientVendor) {
            $thirdPartyPayer = $clientVendor->vendor; // ThirdPartyPayer
            $insuranceCompany = $thirdPartyPayer?->insuranceCompany;

            if ($cascadeBlocked) {
                $vendorResults[] = [
                    'vendor_id' => $clientVendor->third_party_payer_id,
                    'vendor_name' => $insuranceCompany?->name ?? 'Unknown Insurance',
                    'priority' => $clientVendor->priority,
                    'is_open_enrollment' => $clientVendor->is_open_enrollment,
                    'amount_submitted' => 0.0,
                    'authorization_reference' => null,
                    'client_total' => 0.0,
                    'insurance_total' => 0.0,
                    'authorization_status' => 'skipped',
                    'error' => 'Cascade stopped because a higher-priority insurer authorization failed.',
                ];

                continue;
            }

            if ($amountForNextVendor <= 0) {
                $vendorResults[] = [
                    'vendor_id' => $clientVendor->third_party_payer_id,
                    'vendor_name' => $insuranceCompany?->name ?? 'Unknown Insurance',
                    'priority' => $clientVendor->priority,
                    'is_open_enrollment' => $clientVendor->is_open_enrollment,
                    'amount_submitted' => 0.0,
                    'authorization_reference' => null,
                    'client_total' => 0.0,
                    'insurance_total' => 0.0,
                    'authorization_status' => 'skipped',
                    'error' => 'No remaining amount to cascade.',
                ];

                continue;
            }

            if (! $insuranceCompany || ! $insuranceCompany->third_party_business_id) {
                Log::warning('[Kashtre] Multi-vendor cascade: skipping vendor — no third_party_business_id', [
                    'client_vendor_id' => $clientVendor->id,
                ]);

                continue;
            }

            $thirdPartyBusinessId = (int) $insuranceCompany->third_party_business_id;

            // Check suspended / blocked
            if ($thirdPartyPayer->isSuspended() || $thirdPartyPayer->isBlocked()) {
                Log::warning('[Kashtre] Multi-vendor cascade: skipping suspended/blocked vendor', [
                    'vendor_id' => $thirdPartyPayer->id,
                    'status' => $thirdPartyPayer->status,
                ]);
                $vendorResults[] = [
                    'vendor_id' => $clientVendor->third_party_payer_id,
                    'vendor_name' => $insuranceCompany->name,
                    'priority' => $clientVendor->priority,
                    'is_open_enrollment' => $clientVendor->is_open_enrollment,
                    'amount_submitted' => $amountForNextVendor,
                    'authorization_reference' => null,
                    'client_total' => $amountForNextVendor,
                    'insurance_total' => 0.0,
                    'authorization_status' => 'skipped',
                    'error' => "Vendor is {$thirdPartyPayer->status}",
                ];

                continue;
            }

            // Filter items for this vendor (remove vendor-specific exclusions)
            $vendorExcludedItemIds = is_array($clientVendor->excluded_items) ? $clientVendor->excluded_items : [];
            $vendorItems = array_values(array_map(function (array $item) use ($vendorExcludedItemIds) {
                // Mark items as excluded for this vendor if they're in the vendor's excluded list
                if (! empty($vendorExcludedItemIds) && in_array($item['id'], $vendorExcludedItemIds, true)) {
                    $item['kashtre_excluded'] = true;
                }

                return $item;
            }, $itemsForAuthorization));

            $vendorItems = $this->annotateCascadeItemsWithPriorCoverage($vendorItems, $vendorResults);

            $policyNumber = trim((string) ($clientVendor->policy_number ?? ''));
            if ($policyNumber === '' && $insuranceCompany?->id) {
                // Fallback: some clients have multiple vendor rows over time; pick the latest non-empty
                // policy number for the same insurer/vendor family (regardless of row status)
                // instead of failing the whole cascade.
                $fallbackPolicy = \App\Models\ClientVendor::where('client_id', $client->id)
                    ->whereNotNull('policy_number')
                    ->where('policy_number', '!=', '')
                    ->whereHas('vendor', function ($q) use ($insuranceCompany) {
                        $q->where('insurance_company_id', $insuranceCompany->id);
                    })
                    ->orderByDesc('updated_at')
                    ->first();

                if ($fallbackPolicy) {
                    $policyNumber = trim((string) $fallbackPolicy->policy_number);
                    Log::info('[Kashtre] Multi-vendor cascade: recovered policy number from alternate active mapping', [
                        'invoice_id' => $invoice->id,
                        'vendor_name' => $insuranceCompany->name,
                        'client_vendor_id' => $clientVendor->id,
                        'fallback_client_vendor_id' => $fallbackPolicy->id,
                        'policy_number' => $policyNumber,
                    ]);
                }
            }
            if ($policyNumber === '') {
                // Last resort: recover policy using alternative identity verification.
                // This handles cases where mapping rows are blank but the client is still verifiable.
                $fullName = trim(implode(' ', array_filter([
                    (string) ($client->surname ?? ''),
                    (string) ($client->first_name ?? ''),
                    (string) ($client->other_names ?? ''),
                ])));

                if ($fullName !== '' && ! empty($client->date_of_birth)) {
                    try {
                        $altVerification = $apiService->verifyAlternativeIdentity($thirdPartyBusinessId, [
                            'name' => $fullName,
                            'date_of_birth' => $client->date_of_birth,
                            'services_category' => $client->services_category,
                        ]);

                        if (! empty($altVerification['success']) && ! empty($altVerification['exists'])) {
                            $recoveredPolicy = trim((string) (
                                $altVerification['data']['policy_number']
                                ?? $altVerification['policy_number']
                                ?? ''
                            ));

                            if ($recoveredPolicy !== '') {
                                $policyNumber = $recoveredPolicy;
                                $clientVendor->policy_number = $policyNumber;
                                $clientVendor->policy_verified = true;
                                $clientVendor->save();

                                Log::info('[Kashtre] Multi-vendor cascade: recovered policy number via alternative identity verification', [
                                    'invoice_id' => $invoice->id,
                                    'vendor_name' => $insuranceCompany->name,
                                    'client_vendor_id' => $clientVendor->id,
                                    'policy_number' => $policyNumber,
                                ]);
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning('[Kashtre] Multi-vendor cascade: alternative identity policy recovery failed', [
                            'invoice_id' => $invoice->id,
                            'vendor_name' => $insuranceCompany->name,
                            'client_vendor_id' => $clientVendor->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
            if ($policyNumber === '') {
                Log::warning('[Kashtre] Multi-vendor cascade: missing policy number on client-vendor mapping', [
                    'invoice_id' => $invoice->id,
                    'vendor_name' => $insuranceCompany->name,
                    'client_vendor_id' => $clientVendor->id,
                ]);
                $vendorResults[] = [
                    'vendor_id' => $clientVendor->third_party_payer_id,
                    'vendor_name' => $insuranceCompany->name,
                    'priority' => $clientVendor->priority,
                    'is_open_enrollment' => $clientVendor->is_open_enrollment,
                    'amount_submitted' => $amountForNextVendor,
                    'authorization_reference' => null,
                    'client_total' => $amountForNextVendor,
                    'insurance_total' => 0.0,
                    'authorization_status' => 'failed',
                    'error' => 'Missing policy number for this vendor on client profile.',
                ];
                $allWarnings[] = "{$insuranceCompany->name}: Missing policy number for this vendor on client profile.";

                continue;
            }

            $authPayload = [
                'kashtre_invoice_id' => (string) $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'insurance_company_id' => $thirdPartyBusinessId,
                'policy_number' => $policyNumber,
                'services_category' => $client->services_category ?? null,
                'total_amount' => $amountForNextVendor,
                'deductible_remaining' => $deductibleRemaining,
                'copay_contributes_to_deductible' => (bool) $clientVendor->copay_contributes_to_deductible,
                'coinsurance_contributes_to_deductible' => (bool) $clientVendor->coinsurance_contributes_to_deductible,
                'items' => $vendorItems,
                'connected_business_id' => $business->id,
                'authorization_cascade' => [
                    'is_follow_up' => $vendorResults !== [],
                    'vendor_priority' => (int) $clientVendor->priority,
                    'prior_authorizations' => collect($vendorResults)->map(static function (array $prior) {
                        return [
                            'vendor_name' => $prior['vendor_name'] ?? null,
                            'authorization_reference' => $prior['authorization_reference'] ?? null,
                            'insurance_total' => (float) ($prior['insurance_total'] ?? 0),
                            'amount_submitted' => (float) ($prior['amount_submitted'] ?? 0),
                        ];
                    })->values()->all(),
                ],
            ];

            Log::info('[Kashtre] Multi-vendor cascade: authorizing vendor', [
                'invoice_id' => $invoice->id,
                'vendor_name' => $insuranceCompany->name,
                'priority' => $clientVendor->priority,
                'amount_submitted' => $amountForNextVendor,
                'third_party_business_id' => $thirdPartyBusinessId,
            ]);

            $authResult = $apiService->requestInvoiceAuthorization($authPayload);

            if ($authResult && ! empty($authResult['success'])) {
                $vendorClientTotal = (float) ($authResult['client_total'] ?? $amountForNextVendor);
                $vendorInsuranceTotal = (float) ($authResult['insurance_total'] ?? 0);

                // Ensure vendor totals are internally consistent
                $expectedClientTotal = round(max(0, $amountForNextVendor - $vendorInsuranceTotal), 2);
                if (abs($vendorClientTotal - $expectedClientTotal) > 0.02) {
                    $vendorClientTotal = $expectedClientTotal;
                }

                // Only rejected/excluded amount should cascade to the next insurer.
                // Deductible/co-pay/co-insurance stay as client responsibility.
                $breakdown = is_array($authResult['breakdown'] ?? null) ? $authResult['breakdown'] : [];
                $excludedFromBreakdown = (float) ($breakdown['excluded'] ?? 0);
                $excludedItemsTotal = 0.0;
                if (! empty($breakdown['excluded_items']) && is_array($breakdown['excluded_items'])) {
                    foreach ($breakdown['excluded_items'] as $exRow) {
                        $excludedItemsTotal += (float) ($exRow['amount'] ?? 0);
                    }
                }
                $cascadeEligible = round(max($excludedFromBreakdown, $excludedItemsTotal), 2);
                $cascadeEligible = min($cascadeEligible, $vendorClientTotal);
                $nonCascadeClient = round(max(0, $vendorClientTotal - $cascadeEligible), 2);

                $vendorResults[] = [
                    'vendor_id' => $clientVendor->third_party_payer_id,
                    'vendor_name' => $insuranceCompany->name,
                    'priority' => $clientVendor->priority,
                    'is_open_enrollment' => $clientVendor->is_open_enrollment,
                    'policy_number' => $clientVendor->policy_number,
                    'amount_submitted' => $amountForNextVendor,
                    'authorization_reference' => $authResult['authorization_reference'] ?? null,
                    'client_total' => $vendorClientTotal,
                    'insurance_total' => $vendorInsuranceTotal,
                    'breakdown' => $breakdown,
                    'client_total_cascade_eligible' => $cascadeEligible,
                    'client_total_non_cascade' => $nonCascadeClient,
                    'authorization_status' => $authResult['authorization_status'] ?? 'auto_approved',
                    'warnings' => $authResult['warnings'] ?? [],
                ];

                $totalInsuranceTotal += $vendorInsuranceTotal;
                $finalClientTotal += $nonCascadeClient;
                $amountForNextVendor = $cascadeEligible; // only rejected/excluded amount goes to next vendor
                $allWarnings = array_merge($allWarnings, $authResult['warnings'] ?? []);

                // Reset deductible_remaining for subsequent vendors (they track their own)
                $deductibleRemaining = 0;
            } else {
                // Vendor auth failed — stop cascade.
                // Multi-insurance should only pass remainders after a successful higher-priority authorization.
                $errorMessage = is_array($authResult) ? ($authResult['message'] ?? 'Authorization failed') : 'No response';
                Log::warning('[Kashtre] Multi-vendor cascade: authorization failed for vendor', [
                    'invoice_id' => $invoice->id,
                    'vendor_name' => $insuranceCompany->name,
                    'error' => $errorMessage,
                ]);
                $vendorResults[] = [
                    'vendor_id' => $clientVendor->third_party_payer_id,
                    'vendor_name' => $insuranceCompany->name,
                    'priority' => $clientVendor->priority,
                    'is_open_enrollment' => $clientVendor->is_open_enrollment,
                    'amount_submitted' => $amountForNextVendor,
                    'authorization_reference' => null,
                    'client_total' => $amountForNextVendor,
                    'insurance_total' => 0.0,
                    'authorization_status' => 'failed',
                    'error' => $errorMessage,
                ];
                $allWarnings[] = "{$insuranceCompany->name}: {$errorMessage}";
                $cascadeBlocked = true;
            }
        }

        if (empty($vendorResults)) {
            return null;
        }

        // Recalibrate: split the *final* client amount across vendors for payment collection
        // so notifications and UI never double-count client money in cascade scenarios.
        $allocationBase = $vendorResults;
        $eligibleIdx = [];
        $weights = [];
        foreach ($allocationBase as $idx => $row) {
            $status = (string) ($row['authorization_status'] ?? '');
            if (in_array($status, ['failed', 'skipped'], true)) {
                continue;
            }
            $eligibleIdx[] = $idx;
            $breakdown = is_array($row['breakdown'] ?? null) ? $row['breakdown'] : [];
            $weight = (float) ($breakdown['deductible'] ?? 0)
                + (float) ($breakdown['copay'] ?? 0)
                + (float) ($breakdown['coinsurance'] ?? 0)
                + (float) ($breakdown['excluded'] ?? 0);
            $weights[$idx] = max(0.0, $weight);
        }
        $weightTotal = array_sum($weights);
        $remainingAllocation = round(max(0.0, $finalClientTotal), 2);
        $allocatedCount = 0;
        foreach ($eligibleIdx as $idx) {
            $allocatedCount++;
            $row = $vendorResults[$idx];
            $alloc = 0.0;
            if ($remainingAllocation > 0) {
                if ($weightTotal > 0) {
                    if ($allocatedCount === count($eligibleIdx)) {
                        $alloc = $remainingAllocation;
                    } else {
                        $alloc = round(($weights[$idx] / $weightTotal) * $finalClientTotal, 2);
                        $alloc = min($alloc, $remainingAllocation);
                    }
                } else {
                    // Fallback: assign all to the last eligible vendor when no breakdown weights exist.
                    $alloc = ($allocatedCount === count($eligibleIdx)) ? $remainingAllocation : 0.0;
                }
            }
            $vendorResults[$idx]['client_portion_allocated'] = $alloc;
            $remainingAllocation = round(max(0.0, $remainingAllocation - $alloc), 2);
        }
        foreach ($vendorResults as $idx => $row) {
            if (! isset($vendorResults[$idx]['client_portion_allocated'])) {
                $vendorResults[$idx]['client_portion_allocated'] = 0.0;
            }
        }

        // Aggregate auth references (one per vendor, pipe-separated for storage)
        $authRefs = collect($vendorResults)->pluck('authorization_reference')->filter()->implode('|');

        // Add any remaining unresolved cascaded amount to final client responsibility.
        $finalClientTotal = round($finalClientTotal + max(0, $amountForNextVendor), 2);

        $cascadeLineItems = $this->buildCascadeLineItemRowsForSnapshot($itemsForAuthorization, $vendorResults);

        $snapshot = [
            'success' => true,
            'multi_vendor' => true,
            'vendors' => $vendorResults,
            'cascade_line_items' => $cascadeLineItems,
            'client_total' => $finalClientTotal,
            'client_portion_allocated_total' => (float) collect($vendorResults)->sum('client_portion_allocated'),
            'insurance_total' => $totalInsuranceTotal,
            'authorization_reference' => $authRefs,
            'authorization_status' => collect($vendorResults)->contains('authorization_status', 'auto_rejected') ? 'auto_rejected' : 'auto_approved',
            'warnings' => array_values(array_unique($allWarnings)),
        ];

        $snapshot = $this->withInvoicePricingOnAuthorizationSnapshot($invoice, $snapshot);
        $snapshot = $this->syncVendorPortionTraceInvoices($invoice, $snapshot);

        $invoice->update([
            'insurance_client_total' => $finalClientTotal,
            'insurance_insurance_total' => $totalInsuranceTotal,
            'insurance_authorized_at' => now(),
            'insurance_authorization_reference' => $authRefs ?: null,
            'insurance_authorization_snapshot' => $snapshot,
        ]);

        Log::info('[Kashtre] Multi-vendor cascade: complete', [
            'invoice_id' => $invoice->id,
            'vendor_count' => count($vendorResults),
            'total_insurance' => $totalInsuranceTotal,
            'final_client_total' => $finalClientTotal,
        ]);

        return $snapshot;
    }

    /**
     * Per invoice line: primary insurer in cascade order (first eligible vendor that did not list the line
     * in authorization excluded_items). Matches BalanceHistory statement labeling. Insurer "pays" amounts
     * remain invoice-level totals from the third-party API unless the API sends itemized breakdown.
     *
     * @param  array<int, array<string, mixed>>  $invoiceItems
     * @param  array<int, array<string, mixed>>  $vendorResults
     * @return array<int, array<string, mixed>>
     */
    private function buildCascadeLineItemRowsForSnapshot(array $invoiceItems, array $vendorResults): array
    {
        $eligible = [];
        foreach ($vendorResults as $v) {
            $status = (string) ($v['authorization_status'] ?? '');
            if (in_array($status, ['failed', 'skipped'], true)) {
                continue;
            }
            $eligible[] = $v;
        }
        usort($eligible, fn ($a, $b) => ((float) ($a['priority'] ?? 0)) <=> ((float) ($b['priority'] ?? 0)));

        $rows = [];
        foreach ($invoiceItems as $item) {
            if (! is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['name'] ?? $item['displayName'] ?? ''));
            if ($name === '') {
                $name = 'Line item';
            }
            $qty = (float) ($item['quantity'] ?? 1);
            $lineTotal = (float) ($item['total_amount'] ?? ((float) ($item['price'] ?? 0) * $qty));
            $itemId = $item['id'] ?? $item['item_id'] ?? null;

            if (! empty($item['kashtre_excluded'])) {
                $rows[] = [
                    'item_id' => $itemId,
                    'name' => $name,
                    'code' => $item['code'] ?? null,
                    'quantity' => $qty,
                    'line_total' => round($lineTotal, 2),
                    'primary_insurer' => null,
                    'attribution_label' => 'Client (excluded from insurance billing)',
                ];

                continue;
            }

            $primaryName = null;
            $primaryVendor = null;
            $primaryIdx = null;
            foreach ($eligible as $idx => $v) {
                $breakdown = is_array($v['breakdown'] ?? null) ? $v['breakdown'] : [];
                $excludedItems = is_array($breakdown['excluded_items'] ?? null) ? $breakdown['excluded_items'] : [];
                if (BalanceHistory::invoiceRowExcludedByInsuranceBreakdown($item, $excludedItems)) {
                    continue;
                }
                $primaryName = trim((string) ($v['vendor_name'] ?? ''));
                $primaryVendor = $v;
                $primaryIdx = $idx;
                break;
            }

            $partialMeta = $primaryVendor
                ? $this->partialCoverageMetaForInvoiceItem($item, $primaryVendor)
                : null;

            $secondaryName = null;
            $secondaryCoversCascade = false;
            if ($primaryIdx !== null && $partialMeta && ($partialMeta['client_portion'] ?? 0) > 0.001) {
                for ($j = $primaryIdx + 1; $j < count($eligible); $j++) {
                    $secondary = $eligible[$j];
                    $secBreakdown = is_array($secondary['breakdown'] ?? null) ? $secondary['breakdown'] : [];
                    $secExcluded = is_array($secBreakdown['excluded_items'] ?? null) ? $secBreakdown['excluded_items'] : [];
                    if (BalanceHistory::invoiceRowExcludedByInsuranceBreakdown($item, $secExcluded)) {
                        continue;
                    }
                    $secStatus = (string) ($secondary['authorization_status'] ?? '');
                    if (in_array($secStatus, ['failed', 'skipped'], true)) {
                        continue;
                    }
                    $secondaryName = trim((string) ($secondary['vendor_name'] ?? ''));
                    $secondaryCoversCascade = $secondaryName !== '';
                    break;
                }
            }

            $attributionLabel = ($primaryName !== null && $primaryName !== '') ? $primaryName : 'Client';
            $clientPortion = $partialMeta ? (float) ($partialMeta['client_portion'] ?? 0) : 0.0;
            if ($secondaryCoversCascade && $secondaryName !== '') {
                $attributionLabel = $primaryName.' · '.$secondaryName;
                $clientPortion = 0.0;
            } elseif ($partialMeta && ($partialMeta['coverage_percent'] ?? 100) < 100) {
                $attributionLabel = $primaryName;
            }

            $row = [
                'item_id' => $itemId,
                'name' => $name,
                'code' => $item['code'] ?? null,
                'quantity' => $qty,
                'line_total' => round($lineTotal, 2),
                'primary_insurer' => $primaryName !== '' ? $primaryName : null,
                'secondary_insurer' => $secondaryCoversCascade ? $secondaryName : null,
                'attribution_label' => $attributionLabel,
            ];

            if ($partialMeta) {
                $row['coverage_percent'] = $partialMeta['coverage_percent'];
                $row['covered_amount'] = $partialMeta['covered_amount'];
                $row['client_portion'] = round((float) $clientPortion, 2);
                if ($secondaryCoversCascade) {
                    $row['secondary_covered_amount'] = round((float) ($partialMeta['client_portion'] ?? 0), 2);
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * KN flag: mark lines already covered by a higher-priority insurer before cascade follow-up auth.
     *
     * @param  array<int, array<string, mixed>>  $vendorItems
     * @param  array<int, array<string, mixed>>  $priorVendorResults
     * @return array<int, array<string, mixed>>
     */
    private function annotateCascadeItemsWithPriorCoverage(array $vendorItems, array $priorVendorResults): array
    {
        if ($priorVendorResults === [] || $vendorItems === []) {
            return $vendorItems;
        }

        $priorByCode = [];
        foreach ($priorVendorResults as $prior) {
            $priorName = trim((string) ($prior['vendor_name'] ?? ''));
            if ($priorName === '') {
                continue;
            }

            $breakdown = is_array($prior['breakdown'] ?? null) ? $prior['breakdown'] : [];
            $excludedItems = is_array($breakdown['excluded_items'] ?? null) ? $breakdown['excluded_items'] : [];

            foreach ($excludedItems as $ex) {
                if (! is_array($ex) || ($ex['reason_scope'] ?? '') !== 'partial_coverage') {
                    continue;
                }

                $code = mb_strtolower(trim((string) ($ex['code'] ?? '')));
                if ($code === '') {
                    continue;
                }

                $coveredAmount = (float) ($ex['covered_amount'] ?? 0);
                if ($coveredAmount <= 0) {
                    continue;
                }

                $priorByCode[$code] = [
                    'prior_insurer_name' => $priorName,
                    'prior_covered_amount' => round($coveredAmount, 2),
                    'coverage_percent' => (float) ($ex['coverage_percent'] ?? 0),
                    'prior_authorization_reference' => $prior['authorization_reference'] ?? null,
                ];
            }
        }

        if ($priorByCode === []) {
            return $vendorItems;
        }

        return array_map(function (array $item) use ($priorByCode) {
            $code = mb_strtolower(trim((string) ($item['code'] ?? '')));
            if ($code === '' || ! isset($priorByCode[$code])) {
                return $item;
            }

            $prior = $priorByCode[$code];
            $item['prior_coverage'] = $prior;
            $item['already_covered_by_prior_insurer'] = true;
            $item['prior_insurer_name'] = $prior['prior_insurer_name'];
            $item['prior_covered_amount'] = $prior['prior_covered_amount'];
            $item['prior_coverage_percent'] = $prior['coverage_percent'];

            return $item;
        }, $vendorItems);
    }

    /**
     * @param  array<string, mixed>  $invoiceItem
     * @param  array<string, mixed>  $vendorRow
     * @return array{coverage_percent: float, covered_amount: float, client_portion: float}|null
     */
    private function partialCoverageMetaForInvoiceItem(array $invoiceItem, array $vendorRow): ?array
    {
        $breakdown = is_array($vendorRow['breakdown'] ?? null) ? $vendorRow['breakdown'] : [];
        $excludedItems = is_array($breakdown['excluded_items'] ?? null) ? $breakdown['excluded_items'] : [];
        $itemCode = trim((string) ($invoiceItem['code'] ?? ''));
        $itemName = mb_strtolower(trim((string) ($invoiceItem['name'] ?? $invoiceItem['displayName'] ?? '')));

        foreach ($excludedItems as $ex) {
            if (! is_array($ex) || ($ex['reason_scope'] ?? '') !== 'partial_coverage') {
                continue;
            }

            $exCode = trim((string) ($ex['code'] ?? ''));
            $exName = mb_strtolower(trim((string) ($ex['name'] ?? '')));

            $matches = ($itemCode !== '' && $exCode !== '' && strcasecmp($itemCode, $exCode) === 0)
                || ($itemName !== '' && $exName !== '' && $itemName === $exName);

            if (! $matches) {
                continue;
            }

            return [
                'coverage_percent' => (float) ($ex['coverage_percent'] ?? 100),
                'covered_amount' => round((float) ($ex['covered_amount'] ?? 0), 2),
                'client_portion' => round((float) ($ex['amount'] ?? 0), 2),
            ];
        }

        return null;
    }

    /**
     * When multiple insurers participate in a cascade, create labeled child invoices (A, B, C, …)
     * for traceability. The primary invoice row is unchanged; children reference it via parent_invoice_id.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    /**
     * Recreate trace child invoices when their totals no longer match the parent (e.g. after SC trace fix).
     */
    private function repairVendorPortionTraceInvoicesIfNeeded(Invoice $invoice): void
    {
        if ($invoice->parent_invoice_id) {
            return;
        }

        $snapshot = $invoice->insurance_authorization_snapshot;
        if (! is_array($snapshot) || empty($snapshot['multi_vendor']) || empty($snapshot['vendors'])) {
            return;
        }

        $invoice->loadMissing('vendorPortionInvoices');
        if ($invoice->vendorPortionInvoices->isEmpty()) {
            return;
        }

        $traceSum = round((float) $invoice->vendorPortionInvoices->sum('total_amount'), 2);
        $parentTotal = round((float) $invoice->total_amount, 2);

        if (abs($traceSum - $parentTotal) <= 0.02) {
            return;
        }

        try {
            $snapshot = $this->syncVendorPortionTraceInvoices($invoice, $snapshot);
            $invoice->update(['insurance_authorization_snapshot' => $snapshot]);
            $invoice->unsetRelation('vendorPortionInvoices');
            Log::info('[Kashtre] Repaired vendor portion trace invoices to match parent total', [
                'invoice_id' => $invoice->id,
                'parent_total' => $parentTotal,
                'previous_trace_sum' => $traceSum,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Kashtre] Vendor portion trace repair failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function syncVendorPortionTraceInvoices(Invoice $parent, array $snapshot): array
    {
        if (empty($snapshot['multi_vendor']) || empty($snapshot['vendors']) || ! is_array($snapshot['vendors'])) {
            return $snapshot;
        }

        $metaRows = [];
        foreach ($snapshot['vendors'] as $idx => $row) {
            if (! is_array($row)) {
                continue;
            }
            $status = (string) ($row['authorization_status'] ?? '');
            if (in_array($status, ['failed', 'skipped'], true)) {
                continue;
            }
            $submitted = (float) ($row['amount_submitted'] ?? 0);
            if ($submitted <= 0.001) {
                continue;
            }
            $metaRows[] = [
                'idx' => $idx,
                'priority' => (float) ($row['priority'] ?? 0),
                'row' => $row,
            ];
        }

        usort($metaRows, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        if (count($metaRows) < 2) {
            return $snapshot;
        }

        $serviceChargeVendorIdx = $this->resolveServiceChargeVendorIndex($snapshot['vendors'] ?? []);

        try {
            DB::transaction(function () use ($parent, &$snapshot, $metaRows, $serviceChargeVendorIdx) {
                Invoice::where('parent_invoice_id', $parent->id)->delete();

                foreach ($metaRows as $order => $meta) {
                    $letter = chr(65 + min($order, 25));
                    $vendorRow = $meta['row'];
                    $idx = $meta['idx'];
                    $priorityInt = (int) round((float) ($vendorRow['priority'] ?? ($order + 1)));

                    $vendorRow['receives_service_charge'] = $serviceChargeVendorIdx !== null && $serviceChargeVendorIdx === $idx;
                    $snapshot['vendors'][$idx]['receives_service_charge'] = $vendorRow['receives_service_charge'];

                    $child = $this->createVendorPortionTraceInvoice($parent, $vendorRow, $letter, $priorityInt);

                    $snapshot['vendors'][$idx]['portion_label'] = $letter;
                    $snapshot['vendors'][$idx]['kashtre_portion_invoice_id'] = $child->id;
                }
            });
        } catch (\Throwable $e) {
            Log::error('[Kashtre] Vendor portion trace invoices failed (non-blocking)', [
                'invoice_id' => $parent->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $vendorRow
     */
    private function createVendorPortionTraceInvoice(Invoice $parent, array $vendorRow, string $letter, int $cascadePriority): Invoice
    {
        $parent->refresh();
        $insuranceTotal = round((float) ($vendorRow['insurance_total'] ?? 0), 2);
        // Trace copy total = this insurer's authorized share only (A + B must sum to parent invoice total).
        $portionSubtotal = $insuranceTotal;
        $portionSc = 0.0;
        $amountSubmitted = $insuranceTotal;

        $invoiceNumber = Invoice::generateInvoiceNumber((int) $parent->business_id, 'proforma');

        $clientTotal = round((float) ($vendorRow['client_total'] ?? 0), 2);

        $portionSnap = [
            'success' => true,
            'multi_vendor' => false,
            'vendor_portion_trace' => true,
            'parent_invoice_id' => $parent->id,
            'parent_invoice_number' => $parent->invoice_number,
            'vendor_portion_label' => $letter,
            'insurance_total' => $insuranceTotal,
            'client_total' => $clientTotal,
            'authorization_reference' => $vendorRow['authorization_reference'] ?? null,
            'authorization_status' => $vendorRow['authorization_status'] ?? 'auto_approved',
            'amount_submitted' => $amountSubmitted,
            'vendor_name' => $vendorRow['vendor_name'] ?? null,
        ];

        $vendorName = trim((string) ($vendorRow['vendor_name'] ?? 'insurer'));

        $childPayload = [
            'parent_invoice_id' => $parent->id,
            'vendor_portion_label' => $letter,
            'vendor_portion_priority' => $cascadePriority,
            'client_id' => $parent->client_id,
            'business_id' => $parent->business_id,
            'branch_id' => $parent->branch_id,
            'created_by' => $parent->created_by,
            'currency' => $parent->currency,
            'client_name' => $parent->client_name,
            'client_phone' => $parent->client_phone,
            'payment_phone' => $parent->payment_phone,
            'visit_id' => $parent->visit_id,
            'items' => $parent->items,
            'subtotal' => $portionSubtotal,
            'package_adjustment' => 0,
            'account_balance_adjustment' => 0,
            'service_charge' => $portionSc,
            'total_amount' => $amountSubmitted,
            'amount_paid' => $amountSubmitted,
            'balance_due' => 0,
            'payment_methods' => ['vendor_portion'],
            'payment_status' => 'paid',
            'insurance_authorization_reference' => $vendorRow['authorization_reference'] ?? null,
            'insurance_client_total' => $clientTotal,
            'insurance_insurance_total' => $insuranceTotal,
            'insurance_confirmation_code' => null,
            'insurance_authorized_at' => $parent->insurance_authorized_at ?? now(),
            'insurance_authorization_snapshot' => $portionSnap,
            'status' => $parent->status ?? 'confirmed',
            'confirmed_at' => $parent->confirmed_at ?? now(),
        ];

        $child = null;
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $traceNote = "Vendor cascade portion {$letter} (priority {$cascadePriority}), new proforma number {$invoiceNumber}. Trace copy for follow-up with {$vendorName}. "
                ."Settlement and balances apply to primary invoice {$parent->invoice_number}.";
            try {
                $child = Invoice::create(array_merge($childPayload, [
                    'invoice_number' => $invoiceNumber,
                    'notes' => $traceNote,
                ]));
                break;
            } catch (QueryException $e) {
                $sqlState = $e->errorInfo[0] ?? '';
                $driverCode = (int) ($e->errorInfo[1] ?? 0);
                $isDuplicate = ($sqlState === '23000' || $driverCode === 1062)
                    || str_contains($e->getMessage(), 'Duplicate entry')
                    || str_contains($e->getMessage(), '1062');
                if (! $isDuplicate || $attempt >= 11) {
                    throw $e;
                }
                $invoiceNumber = Invoice::generateInvoiceNumber((int) $parent->business_id, 'proforma');
            }
        }

        if (! $child) {
            throw new \RuntimeException('Could not allocate a unique invoice number for vendor portion trace.');
        }

        return $child;
    }

    /**
     * Attach invoice subtotal, clinic service charge, and total so UIs can show fee breakdown.
     * For insurance invoices the fee is included in total_amount and allocated with the insurer/client split
     * (it is not duplicated on each clinical line in cascade_line_items).
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withInvoicePricingOnAuthorizationSnapshot(Invoice $invoice, array $snapshot): array
    {
        $snapshot['subtotal'] = round((float) ($invoice->subtotal ?? 0), 2);
        $snapshot['service_charge'] = round((float) ($invoice->service_charge ?? 0), 2);
        $snapshot['total_amount'] = round((float) ($invoice->total_amount ?? 0), 2);

        if (! empty($snapshot['multi_vendor']) && ! empty($snapshot['vendors']) && is_array($snapshot['vendors'])) {
            $scIdx = $this->resolveServiceChargeVendorIndex($snapshot['vendors']);
            if ($scIdx !== null) {
                $snapshot['service_charge_vendor_index'] = $scIdx;
                $snapshot['service_charge_vendor_name'] = $snapshot['vendors'][$scIdx]['vendor_name'] ?? null;
            }
        }

        return $snapshot;
    }

    /**
     * Clinic service charge is attributed to one insurer only (first in priority with an insurance share).
     *
     * @param  array<int, array<string, mixed>>  $vendors
     */
    private function resolveServiceChargeVendorIndex(array $vendors): ?int
    {
        $candidates = [];
        foreach ($vendors as $idx => $row) {
            if (! is_array($row)) {
                continue;
            }
            $status = (string) ($row['authorization_status'] ?? '');
            if (in_array($status, ['failed', 'skipped'], true)) {
                continue;
            }
            if ((float) ($row['insurance_total'] ?? 0) <= 0.001) {
                continue;
            }
            $candidates[] = [
                'idx' => $idx,
                'priority' => (float) ($row['priority'] ?? 999),
            ];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return $candidates[0]['idx'];
    }

    /**
     * After third-party authorization, client portion must match the invoice split:
     * invoice_total = insurance_total + client_total (same rule as vendor authorization ledger).
     */
    private function normalizeInsuranceAuthorizationClientTotals(Invoice $invoice, array $authorization): array
    {
        $invoiceTotal = (float) $invoice->total_amount;
        $insuranceTotal = (float) ($authorization['insurance_total'] ?? 0);
        $derived = round(max(0, $invoiceTotal - $insuranceTotal), 2);
        $apiClient = isset($authorization['client_total']) ? (float) $authorization['client_total'] : null;
        if ($apiClient !== null && abs($apiClient - $derived) > 0.02) {
            Log::info('[Kashtre] Insurance authorization: client_total aligned to invoice_total - insurance_total', [
                'invoice_id' => $invoice->id,
                'invoice_total' => $invoiceTotal,
                'insurance_total' => $insuranceTotal,
                'client_total_from_api' => $apiClient,
                'client_total_normalized' => $derived,
            ]);
        }
        $authorization['client_total'] = $derived;

        return $authorization;
    }

    private function normalizedInsuranceClientTotalFromSnapshot(Invoice $invoice, array $snapshot): float
    {
        $invoiceTotal = (float) $invoice->total_amount;
        $insuranceTotal = (float) ($snapshot['insurance_total'] ?? 0);

        return round(max(0, $invoiceTotal - $insuranceTotal), 2);
    }
}
