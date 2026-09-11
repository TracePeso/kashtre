<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\ThirdPartyPayer;
use App\Models\ThirdPartyPayerBalanceHistory;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Item;
use App\Models\PaymentMethodAccount;
use App\Payments\YoAPI;
use App\Services\MoneyTrackingService;
use App\Services\InsuranceClientPortionThirdPartyNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CheckPaymentStatus extends Command
{
    protected $signature = 'payments:check-status'; 
    protected $description = 'Check and update YoAPI payment statuses using external_reference field';

    public function handle()
    {
        Log::info('=== CRON JOB STARTED: CheckPaymentStatus ===', [
            'timestamp' => now(),
            'command' => 'payments:check-status',
            'server' => gethostname(),
            'php_version' => PHP_VERSION
        ]);

        // Get all pending transactions that have YoAPI references
        $pendingTransactions = Transaction::where('status', 'pending')
            ->where('method', 'mobile_money')
            ->where('provider', 'yo')
            ->where(function($query) {
                $query->whereNotNull('reference')
                      ->orWhereNotNull('external_reference');
            })
            ->with(['business', 'client'])
            ->get();

        Log::info('Found pending mobile money transactions (including 5+ minute old transactions for timeout handling)', [
            'count' => $pendingTransactions->count(),
            'timeout_threshold_minutes' => 5,
            'transactions' => $pendingTransactions->map(function($t) {
                $ageMinutes = now()->diffInMinutes($t->created_at);
                return [
                    'id' => $t->id,
                    'reference' => $t->reference,
                    'external_reference' => $t->external_reference,
                    'amount' => $t->amount,
                    'client_id' => $t->client_id,
                    'invoice_id' => $t->invoice_id,
                    'created_at' => $t->created_at->toDateTimeString(),
                    'age_minutes' => $ageMinutes,
                    'will_timeout' => $ageMinutes >= 5
                ];
            })->toArray()
        ]);

        if ($pendingTransactions->isEmpty()) {
            Log::info('No pending mobile money transactions found for status check - CRON JOB EXITING');
            return;
        }

        $yoPayments = new YoAPI(config('payments.yo_username'), config('payments.yo_password'));

        foreach ($pendingTransactions as $index => $transaction) {
            try {
                Log::info("=== PROCESSING TRANSACTION " . ($index + 1) . " OF " . $pendingTransactions->count() . " ===", [
                    'transaction_id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'external_reference' => $transaction->external_reference,
                    'amount' => $transaction->amount,
                    'client_id' => $transaction->client_id,
                    'invoice_id' => $transaction->invoice_id,
                    'created_at' => $transaction->created_at->toDateTimeString()
                ]);

                // ENABLED: Transaction timeout logic - fail transactions after 5 minutes
                // Check if transaction has timed out (older than 5 minutes)
                $transactionAge = now()->diffInMinutes($transaction->created_at);
                
                // If transaction is older than 5 minutes, mark as failed (timeout)
                // This applies to both transactions with and without external_reference
                if ($transactionAge >= 5) {
                    $reason = $transaction->external_reference 
                        ? "timed out after {$transactionAge} minutes" 
                        : "timed out after {$transactionAge} minutes (no external reference)";
                    
                    Log::warning("Transaction {$transaction->id} has {$reason} - marking as failed", [
                        'transaction_id' => $transaction->id,
                        'reference' => $transaction->reference,
                        'external_reference' => $transaction->external_reference,
                        'age_minutes' => $transactionAge,
                        'created_at' => $transaction->created_at->toDateTimeString(),
                        'reason' => $reason
                    ]);

                    DB::beginTransaction();
                    
                    try {
                        // Update transaction status to failed due to timeout
                        $transaction->update([
                            'status' => 'failed',
                            'updated_at' => now()
                        ]);

                        // Update related invoice if exists and make it available for reinitiation
                        if ($transaction->invoice_id) {
                            $invoice = Invoice::find($transaction->invoice_id);
                            if ($invoice) {
                                $invoice->update([
                                    'status' => 'draft', // Reset to draft to allow reinitiation
                                    'payment_status' => 'pending' // Reset payment status
                                ]);
                                
                                Log::info("Invoice status reset to draft for reinitiation due to transaction timeout", [
                                    'invoice_id' => $invoice->id,
                                    'invoice_number' => $invoice->invoice_number,
                                    'transaction_id' => $transaction->id,
                                    'new_status' => 'draft',
                                    'new_payment_status' => 'pending'
                                ]);
                            }
                        }

                        DB::commit();
                        
                        Log::info("Transaction ID {$transaction->id} marked as FAILED due to timeout - Order available for reinitiation", [
                            'transaction_id' => $transaction->id,
                            'reference' => $transaction->reference,
                            'timeout_minutes' => $transactionAge,
                            'order_reinitiation' => 'enabled'
                        ]);
                        
                        continue; // Skip the YoAPI status check for timed out transactions
                        
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error("Failed to mark transaction {$transaction->id} as failed due to timeout", [
                            'transaction_id' => $transaction->id,
                            'error' => $e->getMessage()
                        ]);
                        continue;
                    }
                }

                // Check if transaction has external_reference (required for YoAPI status check)
                if (!$transaction->external_reference) {
                    Log::warning("No external reference found for transaction ID: {$transaction->id} - SKIPPING (transaction is within timeout period but cannot check status without external_reference)", [
                        'transaction_id' => $transaction->id,
                        'reference' => $transaction->reference,
                        'age_minutes' => $transactionAge,
                        'created_at' => $transaction->created_at->toDateTimeString()
                    ]);
                    continue;
                }

                Log::info("Transaction within timeout period - proceeding with YoAPI status check", [
                    'transaction_id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'age_minutes' => now()->diffInMinutes($transaction->created_at),
                    'timeout_threshold' => '5 minutes'
                ]);

                Log::info("Checking payment status with YoAPI", [
                    'transaction_id' => $transaction->id,
                    'external_reference' => $transaction->external_reference
                ]);

                // Check payment status with YoAPI using external_reference
                $statusCheck = $yoPayments->ac_transaction_check_status($transaction->external_reference);

                Log::info("YoAPI status check response for transaction {$transaction->id}", [
                    'transaction_id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'external_reference' => $transaction->external_reference,
                    'response' => $statusCheck
                ]);

                if (isset($statusCheck['TransactionStatus'])) {
                    Log::info("Transaction status received from YoAPI", [
                        'transaction_id' => $transaction->id,
                        'status' => $statusCheck['TransactionStatus'],
                        'full_response' => $statusCheck
                    ]);

                    DB::beginTransaction();
                    
                    try {
                        if ($statusCheck['TransactionStatus'] === 'SUCCEEDED') {
                            Log::info("🎉 AUTOMATIC PAYMENT COMPLETION DETECTED - Transaction will be completed automatically", [
                                'transaction_id' => $transaction->id,
                                'reference' => $transaction->reference,
                                'amount' => $transaction->amount,
                                'client_id' => $transaction->client_id,
                                'invoice_id' => $transaction->invoice_id
                            ]);
                            Log::info("=== PAYMENT SUCCEEDED - Processing transaction {$transaction->id} ===", [
                                'transaction_id' => $transaction->id,
                                'reference' => $transaction->reference,
                                'external_reference' => $transaction->external_reference,
                                'amount' => $transaction->amount,
                                'client_id' => $transaction->client_id,
                                'invoice_id' => $transaction->invoice_id
                            ]);

                            // Determine payment status: PP for credit clients with balance due, otherwise Paid
                            $paymentStatus = 'Paid';
                            if ($transaction->invoice_id) {
                                $invoice = \App\Models\Invoice::find($transaction->invoice_id);
                                if ($invoice && $transaction->client_id) {
                                    $client = \App\Models\Client::find($transaction->client_id);
                                    // If invoice is fully paid (balance_due = 0), always 'Paid'
                                    // Otherwise, if client is credit-eligible and has balance due, set to 'PP'
                                    if ($invoice->balance_due > 0 && $client && $client->is_credit_eligible) {
                                        $paymentStatus = 'PP';
                                    }
                                }
                            }
                            
                            // Update transaction status
                            $transaction->update([
                                'status' => 'completed',
                                'payment_status' => $paymentStatus,
                                'updated_at' => now()
                            ]);

                            Log::info("Transaction status updated to completed", [
                                'transaction_id' => $transaction->id
                            ]);

                            // Update related invoice if exists
                            if ($transaction->invoice_id) {
                                $invoice = Invoice::find($transaction->invoice_id);
                                if ($invoice) {
                                    Log::info("Found invoice for payment completion", [
                                        'invoice_id' => $invoice->id,
                                        'invoice_number' => $invoice->invoice_number,
                                        'current_status' => $invoice->status,
                                        'payment_status' => $invoice->payment_status
                                    ]);

                                    // Update invoice status to paid
                                    $invoice->update([
                                        'status' => 'paid',
                                        'payment_status' => 'paid'
                                    ]);

                                    Log::info("Invoice status updated to paid", [
                                        'invoice_id' => $invoice->id,
                                        'invoice_number' => $invoice->invoice_number
                                    ]);

                                    $client = $invoice->client;
                                    $snapshot = is_array($invoice->insurance_authorization_snapshot ?? null) ? $invoice->insurance_authorization_snapshot : [];
                                    $clientPortionAmount = isset($snapshot['client_total']) ? (float) $snapshot['client_total'] : (float) ($invoice->insurance_client_total ?? 0);
                                    // For multi-vendor snapshots that store client_total per vendor (not at root level)
                                    if ($clientPortionAmount <= 0 && isset($snapshot['vendors']) && is_array($snapshot['vendors'])) {
                                        $clientPortionAmount = (float) array_sum(array_column($snapshot['vendors'], 'client_total'));
                                    }
                                    $snapshotInsuranceTotal = (float) ($snapshot['insurance_total'] ?? $invoice->insurance_insurance_total ?? 0);
                                    // Only treat as insurance client portion when insurers actually pay something
                                    $isInsuranceClientPortion = $client && $client->insurance_company_id && $clientPortionAmount > 0 && $snapshotInsuranceTotal > 0 && $transaction->amount >= $clientPortionAmount * 0.99;

                                    if ($isInsuranceClientPortion) {
                                        // Client paid on behalf of insurer: credit insurance company internal account, not client account
                                        $thirdPartyPayer = ThirdPartyPayer::where('insurance_company_id', $client->insurance_company_id)
                                            ->where('business_id', $invoice->business_id)
                                            ->where('type', 'insurance_company')
                                            ->whereNull('client_id')
                                            ->first();
                                        if (!$thirdPartyPayer) {
                                            $thirdPartyPayer = ThirdPartyPayer::where('client_id', $client->id)
                                                ->where('business_id', $invoice->business_id)
                                                ->where('type', 'insurance_company')
                                                ->first();
                                        }
                                        if ($thirdPartyPayer) {
                                            $ref = 'CP-' . $transaction->created_at->format('Ymd') . '-' . $transaction->id;
                                            $paymentMethodAccount = PaymentMethodAccount::forBusiness($invoice->business_id)
                                                ->forPaymentMethod('mobile_money')
                                                ->active()
                                                ->first();
                                            if ($paymentMethodAccount) {
                                                $paymentMethodAccount->debit(
                                                    $transaction->amount,
                                                    $ref,
                                                    'Client portion – ' . $invoice->invoice_number,
                                                    $client->id,
                                                    $invoice->id,
                                                    ['transaction_id' => $transaction->id]
                                                );
                                            }

                                            // Do not credit insurer insurance_total — client MM only settles client portion.
                                            // Insurer obligation stays as guarantee debit when payment completion runs elsewhere.
                                        }
                                        $invoice->update([
                                            'amount_paid' => $transaction->amount,
                                            'balance_due' => 0,
                                        ]);
                                        $moneyTrackingService = new MoneyTrackingService();
                                        $itemsForStatements = is_array($invoice->items) ? $invoice->items : json_decode($invoice->items, true) ?? [];
                                        $moneyTrackingService->processPaymentCompleted($invoice->fresh(), $itemsForStatements);
                                        if (! $invoice->isInventoryUsagePostpaid()) {
                                            $this->createPackageTrackingRecords($invoice, $invoice->items);
                                            $queuedItems = $this->queueItemsAtServicePoints($invoice, $invoice->items);
                                        } else {
                                            Log::info('Skipping service queue for inventory postpaid usage invoice', [
                                                'invoice_id' => $invoice->id,
                                                'invoice_number' => $invoice->invoice_number,
                                            ]);
                                        }
                                        InsuranceClientPortionThirdPartyNotifier::notifyIfApplicable($invoice, $transaction);
                                    } else {
                                        // Normal payment: credit client suspense then client account
                                        $moneyTrackingService = new MoneyTrackingService();
                                        $moneyTrackingService->processPaymentReceived(
                                            $client,
                                            $invoice->amount_paid,
                                            $invoice->invoice_number,
                                            'mobile_money',
                                            [
                                                'invoice_id' => $invoice->id,
                                                'transaction_id' => $transaction->id,
                                                'payment_methods' => $invoice->payment_methods ?? ['mobile_money']
                                            ]
                                        );
                                        $balanceStatements = $moneyTrackingService->processPaymentCompleted($invoice, $invoice->items);
                                        if (! $invoice->isInventoryUsagePostpaid()) {
                                            $this->createPackageTrackingRecords($invoice, $invoice->items);
                                            try {
                                                $moneyTrackingService->processSuspenseAccountMovements($invoice, $invoice->items);
                                            } catch (\Exception $e) {
                                                Log::error("Suspense account movements failed", ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
                                            }
                                            $queuedItems = $this->queueItemsAtServicePoints($invoice, $invoice->items);
                                        } else {
                                            Log::info('Skipping service queue for inventory postpaid usage invoice', [
                                                'invoice_id' => $invoice->id,
                                                'invoice_number' => $invoice->invoice_number,
                                            ]);
                                        }
                                        InsuranceClientPortionThirdPartyNotifier::notifyIfApplicable($invoice, $transaction);
                                    }
                                } else {
                                    Log::warning("Invoice not found for transaction", [
                                        'transaction_id' => $transaction->id,
                                        'invoice_id' => $transaction->invoice_id
                                    ]);
                                }
                            } else {
                                Log::warning("No invoice_id found for transaction", [
                                    'transaction_id' => $transaction->id
                                ]);
                            }

                            // Update client balance if needed
                            if ($transaction->client_id) {
                                $client = Client::find($transaction->client_id);
                                if ($client) {
                                    Log::info("Payment succeeded for client", [
                                        'client_id' => $client->id,
                                        'client_name' => $client->name,
                                        'transaction_id' => $transaction->id,
                                        'amount' => $transaction->amount
                                    ]);
                                } else {
                                    Log::warning("Client not found for transaction", [
                                        'transaction_id' => $transaction->id,
                                        'client_id' => $transaction->client_id
                                    ]);
                                }
                            }

                            Log::info("=== PAYMENT SUCCEEDED - Transaction processing completed ===", [
                                'transaction_id' => $transaction->id,
                                'reference' => $transaction->reference,
                                'amount' => $transaction->amount
                            ]);

                        } elseif ($statusCheck['TransactionStatus'] === 'PENDING') {
                            Log::info("Transaction still pending - no action taken", [
                                'transaction_id' => $transaction->id,
                                'reference' => $transaction->reference,
                                'status' => 'PENDING'
                            ]);
                        } elseif ($statusCheck['TransactionStatus'] === 'FAILED') {
                            // Update transaction status to failed
                            $transaction->update([
                                'status' => 'failed',
                                'updated_at' => now()
                            ]);

                            // Update related invoice if exists
                            if ($transaction->invoice_id) {
                                $invoice = Invoice::find($transaction->invoice_id);
                                if ($invoice) {
                                    $invoice->update(['status' => 'failed']);
                                }
                            }

                            Log::info("Transaction ID {$transaction->id} updated to FAILED", [
                                'transaction_id' => $transaction->id,
                                'reference' => $transaction->reference,
                                'amount' => $transaction->amount
                            ]);
                        }

                        DB::commit();

                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error("Failed to update transaction {$transaction->id} status", [
                            'transaction_id' => $transaction->id,
                            'error' => $e->getMessage(),
                            'status_response' => $statusCheck
                        ]);
                    }

                } else {
                    Log::warning("No valid status returned for transaction ID: {$transaction->id}", [
                        'transaction_id' => $transaction->id,
                        'reference' => $transaction->reference,
                        'response' => $statusCheck
                    ]);
                }

            } catch (\Exception $e) {
                Log::error("Error checking status for transaction {$transaction->id}", [
                    'transaction_id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('=== CRON JOB COMPLETED: CheckPaymentStatus ===', [
            'total_checked' => $pendingTransactions->count(),
            'timestamp' => now(),
            'command' => 'payments:check-status',
            'summary' => [
                'transactions_processed' => $pendingTransactions->count(),
                'server' => gethostname(),
                'execution_time' => now()->toDateTimeString()
            ]
        ]);
    }

    /**
     * Queue items at their respective service points
     * 
     * @param \App\Models\Invoice $invoice
     * @param array $items
     * @param int|null $insuranceCompanyId Optional insurance company ID to mark items as insurance items
     */
    private function queueItemsAtServicePoints($invoice, $items, $insuranceCompanyId = null)
    {
        $queuedCount = 0;
        
        Log::info("=== STARTING ITEM QUEUING PROCESS ===", [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'total_items' => count($items ?? []),
            'insurance_company_id' => $insuranceCompanyId
        ]);
        
        // If insurance_company_id not provided, try to get it from client
        if (!$insuranceCompanyId && $invoice->client) {
            $insuranceCompanyId = $invoice->client->insurance_company_id ?? null;
        }

        if (empty($items)) {
            Log::warning("No items found in invoice for queuing", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number
            ]);
            return 0;
        }

        try {
            app(\App\Services\Inventory\InventoryFulfillmentIngestService::class)
                ->ingestFromInvoice($invoice, is_array($items) ? $items : []);
        } catch (\Throwable $e) {
            Log::error('Inventory fulfillment ingest failed (CheckPaymentStatus)', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }

        foreach ($items as $index => $item) {
            $itemId = $item['id'] ?? $item['item_id'] ?? null;
            
            Log::info("Processing item " . ($index + 1), [
                'item_id' => $itemId,
                'item_name' => $item['name'] ?? 'Unknown',
                'quantity' => $item['quantity'] ?? 1
            ]);

            if (!$itemId) {
                Log::warning("Item ID not found, skipping", ['item_data' => $item]);
                continue;
            }

            // Get the item from database
            $itemModel = Item::find($itemId);
            if (!$itemModel) {
                Log::warning("Item model not found in database", ['item_id' => $itemId]);
                continue;
            }

            $quantity = $item['quantity'] ?? 1;

            // Get service point through BranchServicePoint relationship
            $branchServicePoint = $itemModel->branchServicePoints()
                ->where('business_id', $invoice->business_id)
                ->where('branch_id', $invoice->branch_id)
                ->first();

            Log::info("Found item model", [
                'item_id' => $itemModel->id,
                'item_name' => $itemModel->name,
                'item_type' => $itemModel->type,
                'business_id' => $invoice->business_id,
                'branch_id' => $invoice->branch_id,
                'branch_service_point_found' => $branchServicePoint ? 'yes' : 'no',
                'service_point_id' => $branchServicePoint ? $branchServicePoint->service_point_id : null
            ]);

            // Handle package items FIRST - packages use their own tracking system, not service point queuing
            if ($itemModel->type === 'package') {
                Log::info("Package item detected - using package tracking system instead of service point queuing", [
                    'package_item_id' => $itemId,
                    'package_name' => $itemModel->name,
                    'invoice_id' => $invoice->id,
                    'client_id' => $invoice->client_id
                ]);
                
                // Packages are handled by package tracking logic, not service point queuing
                // No queuing action needed here
                continue; // Skip to next item
            }

            // Handle bulk items - bulk items don't have service points, use service point from one of their included items
            if ($itemModel->type === 'bulk') {
                Log::info("Processing bulk item", [
                    'bulk_item_id' => $itemId,
                    'bulk_name' => $itemModel->name
                ]);

                // Get service point from one of the bulk item's contained items
                Log::info("Bulk items don't have service points, checking contained items for service point", [
                    'bulk_item_id' => $itemId,
                    'bulk_name' => $itemModel->name
                ]);

                $bulkItems = $itemModel->bulkItems()->with('includedItem')->get();
                
                Log::info("Found bulk items for service point lookup", [
                    'bulk_item_id' => $itemId,
                    'included_items_count' => $bulkItems->count()
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
                        Log::info("Found service point from included item", [
                            'included_item_id' => $includedItem->id,
                            'included_item_name' => $includedItem->name,
                            'service_point_id' => $servicePointId
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
                        'quantity' => $quantity
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

                    $queuedCount++;
                    Log::info("Service delivery queue created for bulk item using included item's service point", [
                        'queue_id' => $queueRecord->id,
                        'bulk_item_id' => $itemId,
                        'service_point_id' => $servicePointId,
                        'selected_included_item' => $selectedIncludedItem ? $selectedIncludedItem->name : 'Unknown'
                    ]);
                } else {
                    Log::info("No service point found for bulk item or any of its contained items, skipping queuing", [
                        'bulk_item_id' => $itemId,
                        'bulk_name' => $itemModel->name,
                        'business_id' => $invoice->business_id,
                        'branch_id' => $invoice->branch_id
                    ]);
                }
            }

            // Handle regular items with service points (only for non-package, non-bulk items)
            if ($branchServicePoint && $branchServicePoint->service_point_id) {
                $existingQueue = \App\Models\ServiceDeliveryQueue::where('invoice_id', $invoice->id)
                    ->where('client_id', $invoice->client_id)
                    ->where('item_id', $itemId)
                    ->where('service_point_id', $branchServicePoint->service_point_id)
                    ->first();

                if ($existingQueue) {
                    Log::info("Regular item already queued for invoice, skipping duplicate", [
                        'invoice_id' => $invoice->id,
                        'queue_id' => $existingQueue->id,
                        'item_id' => $itemId,
                        'service_point_id' => $branchServicePoint->service_point_id,
                    ]);
                    continue;
                }

                Log::info("Creating service delivery queue for regular item", [
                    'item_id' => $itemId,
                    'service_point_id' => $branchServicePoint->service_point_id,
                    'quantity' => $quantity
                ]);

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

                $queuedCount++;
                Log::info("Service delivery queue created for regular item", [
                    'queue_id' => $queueRecord->id,
                    'item_id' => $itemId,
                    'service_point_id' => $branchServicePoint->service_point_id
                ]);
            } else {
                Log::info("Regular item has no service point for this business/branch, skipping queuing", [
                    'item_id' => $itemId,
                    'item_name' => $itemModel->name,
                    'business_id' => $invoice->business_id,
                    'branch_id' => $invoice->branch_id,
                    'branch_service_point_found' => $branchServicePoint ? 'yes' : 'no'
                ]);
            }
        }

        Log::info("=== ITEM QUEUING PROCESS COMPLETED ===", [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'total_items_processed' => count($items),
            'total_items_queued' => $queuedCount
        ]);

        return $queuedCount;
    }

    /**
     * Create package tracking records for package items using new structure
     */
    private function createPackageTrackingRecords($invoice, $items)
    {
        $packageTrackingService = new \App\Services\PackageTrackingService();
        $packageTrackingCount = 0;
        
        Log::info("=== STARTING PACKAGE TRACKING CREATION (NEW STRUCTURE) ===", [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'total_items' => count($items ?? [])
        ]);

        foreach ($items as $index => $item) {
            $itemId = $item['id'] ?? $item['item_id'] ?? null;
            
            Log::info("🔍 PROCESSING ITEM " . ($index + 1) . " FOR PACKAGE TRACKING", [
                'item_id' => $itemId,
                'item_data' => $item,
                'item_name' => $item['name'] ?? 'Unknown'
            ]);
            
            if (!$itemId) {
                Log::warning("❌ No item ID found for item " . ($index + 1), [
                    'item_data' => $item
                ]);
                continue;
            }

            // Get the item from database to check if it's a package
            $itemModel = \App\Models\Item::find($itemId);
            if (!$itemModel) {
                Log::warning("❌ Item model not found", [
                    'item_id' => $itemId,
                    'item_name' => $item['name'] ?? 'Unknown'
                ]);
                continue;
            }
            
            Log::info("📦 ITEM MODEL DETAILS", [
                'item_id' => $itemModel->id,
                'item_name' => $itemModel->name,
                'item_type' => $itemModel->type,
                'is_package' => $itemModel->type === 'package'
            ]);
            
            if ($itemModel->type !== 'package') {
                Log::info("⏭️ SKIPPING NON-PACKAGE ITEM", [
                    'item_id' => $itemModel->id,
                    'item_name' => $itemModel->name,
                    'item_type' => $itemModel->type,
                    'reason' => 'Item is not a package type'
                ]);
                continue;
            }

            $quantity = $item['quantity'] ?? 1;

            // Check if package tracking record already exists to prevent duplicates
            $existingTracking = \App\Models\PackageTracking::where([
                'invoice_id' => $invoice->id,
                'package_item_id' => $itemId,
                'client_id' => $invoice->client_id
            ])->first();

            if ($existingTracking) {
                Log::warning("Package tracking record already exists - skipping creation to prevent duplicate", [
                    'existing_tracking_id' => $existingTracking->id,
                    'invoice_id' => $invoice->id,
                    'package_item_id' => $itemId,
                    'client_id' => $invoice->client_id
                ]);
                continue;
            }

            try {
                // Use the new service to create package tracking
                $packageTracking = $packageTrackingService->createPackageTracking($invoice, $item, $quantity);
                $packageTrackingCount++;
                
                Log::info("Package tracking created successfully", [
                    'package_tracking_id' => $packageTracking->id,
                    'tracking_number' => $packageTracking->tracking_number,
                    'package_item_id' => $itemId,
                    'quantity' => $quantity
                ]);

            } catch (\Exception $e) {
                Log::error("Failed to create package tracking", [
                    'error' => $e->getMessage(),
                    'invoice_id' => $invoice->id,
                    'package_item_id' => $itemId,
                    'quantity' => $quantity
                ]);
                continue;
            }
        }

        // Create business and client statement entries for package purchase if any packages were created
        // BUT only if there's actual package usage (package adjustment or package item sales)
        // Package purchase entries should only be created when there's actual package consumption
        if ($packageTrackingCount > 0) {
            // Check if there's actual package usage before creating package purchase entries
            $hasPackageUsage = $this->checkForPackageUsage($invoice);
            
            if ($hasPackageUsage) {
                Log::info("Package usage detected - creating package purchase entries", [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'package_tracking_count' => $packageTrackingCount
                ]);
                
                $this->createPackagePurchaseBusinessStatementEntry($invoice, $packageTrackingCount);
                $this->createPackagePurchaseClientStatementEntry($invoice, $packageTrackingCount);
            } else {
                Log::info("No package usage detected - skipping package purchase entries", [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'package_tracking_count' => $packageTrackingCount,
                    'reason' => 'Package tracking records created but no actual package usage/sales detected'
                ]);
            }
        }

        Log::info("=== PACKAGE TRACKING CREATION COMPLETED (NEW STRUCTURE) ===", [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'total_package_tracking_records_created' => $packageTrackingCount
        ]);

        return $packageTrackingCount;
    }

    /**
     * Create business statement entry for package purchase
     */
    private function createPackagePurchaseBusinessStatementEntry($invoice, $packageCount)
    {
        try {
            Log::info("=== CREATING PACKAGE PURCHASE BUSINESS STATEMENT ENTRY ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'package_count' => $packageCount,
                'business_id' => $invoice->business_id,
                'client_id' => $invoice->client_id
            ]);

            // Get business and business account
            $business = \App\Models\Business::find($invoice->business_id);
            if (!$business) {
                Log::error("Business not found for package purchase statement", [
                    'business_id' => $invoice->business_id,
                    'invoice_id' => $invoice->id
                ]);
                return;
            }

            $businessAccount = \App\Models\MoneyAccount::where('business_id', $business->id)
                ->where('account_type', 'business_account')
                ->first();

            if (!$businessAccount) {
                Log::error("Business account not found for package purchase statement", [
                    'business_id' => $business->id,
                    'invoice_id' => $invoice->id
                ]);
                return;
            }

            // Calculate total package amount from the invoice
            $totalPackageAmount = 0;
            foreach ($invoice->items as $item) {
                $itemModel = \App\Models\Item::find($item['id'] ?? $item['item_id'] ?? null);
                if ($itemModel && $itemModel->type === 'package') {
                    $totalPackageAmount += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                }
            }

            Log::info("Package purchase details", [
                'total_package_amount' => $totalPackageAmount,
                'package_count' => $packageCount,
                'business_account_id' => $businessAccount->id,
                'business_account_balance' => $businessAccount->balance
            ]);

            // Build proper package purchase description with package names and tracking numbers
            $packageDescriptions = $this->buildPackagePurchaseDescriptions($invoice);
            
            // Create business balance history entry for package purchase (credit type - money actually moved)
            $businessBalanceHistory = \App\Models\BusinessBalanceHistory::recordChange(
                $business->id,
                $businessAccount->id,
                $totalPackageAmount,
                'credit',
                $packageDescriptions['business_description'],
                'package_purchase',
                $invoice->id,
                [
                    'invoice_id' => $invoice->id,
                    'package_count' => $packageCount,
                    'total_package_amount' => $totalPackageAmount,
                    'note' => 'Package purchase recorded - full amount moved to business account'
                ]
            );

            Log::info("Package purchase business statement entry created successfully", [
                'business_balance_history_id' => $businessBalanceHistory->id,
                'business_id' => $business->id,
                'amount' => $totalPackageAmount,
                'type' => 'credit',
                'description' => "Package purchase from invoice {$invoice->invoice_number}",
                'business_account_balance_after' => $businessAccount->fresh()->balance,
                'balance_change' => $totalPackageAmount
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to create package purchase business statement entry", [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Create client statement entry for package purchase
     */
    private function createPackagePurchaseClientStatementEntry($invoice, $packageCount)
    {
        try {
            Log::info("=== CREATING PACKAGE PURCHASE CLIENT STATEMENT ENTRY ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'package_count' => $packageCount,
                'client_id' => $invoice->client_id
            ]);

            // Get client
            $client = \App\Models\Client::find($invoice->client_id);
            if (!$client) {
                Log::error("Client not found for package purchase statement", [
                    'client_id' => $invoice->client_id,
                    'invoice_id' => $invoice->id
                ]);
                return;
            }

            // Calculate total package amount from the invoice
            $totalPackageAmount = 0;
            foreach ($invoice->items as $item) {
                $itemModel = \App\Models\Item::find($item['id'] ?? $item['item_id'] ?? null);
                if ($itemModel && $itemModel->type === 'package') {
                    $totalPackageAmount += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                }
            }

            Log::info("Client package purchase details", [
                'total_package_amount' => $totalPackageAmount,
                'package_count' => $packageCount,
                'client_id' => $client->id
            ]);

            // Build proper package purchase description with package names and tracking numbers
            $packageDescriptions = $this->buildPackagePurchaseDescriptions($invoice);
            
            // Create client balance history entry for package purchase
            $balanceHistory = \App\Models\BalanceHistory::recordPackageUsage(
                $client,
                $totalPackageAmount,
                $packageDescriptions['client_description'],
                $invoice->invoice_number,
                $packageDescriptions['client_notes'],
                'package_purchase'
            );

            Log::info("Package purchase client statement entry created successfully", [
                'balance_history_id' => $balanceHistory->id,
                'client_id' => $client->id,
                'amount' => $totalPackageAmount,
                'transaction_type' => 'package',
                'description' => "Package purchase from invoice {$invoice->invoice_number}",
                'client_name' => $client->name,
                'invoice_number' => $invoice->invoice_number,
                'package_count' => $packageCount
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to create package purchase client statement entry", [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Check if there's actual package usage (package adjustment or package item sales)
     * Package purchase entries should only be created when there's actual package consumption
     */
    private function checkForPackageUsage($invoice)
    {
        try {
            Log::info("=== CHECKING FOR PACKAGE USAGE ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $invoice->client_id
            ]);

            // Check if there are any package sales for this invoice
            $packageSales = \App\Models\PackageSales::where('invoice_id', $invoice->id)->get();
            
            if ($packageSales->count() > 0) {
                Log::info("Package sales found - package usage detected", [
                    'invoice_id' => $invoice->id,
                    'package_sales_count' => $packageSales->count(),
                    'package_sales' => $packageSales->pluck('item_name', 'id')
                ]);
                return true;
            }

            // Check if there are any individual package item debit entries for this invoice
            $individualPackageEntries = \App\Models\BalanceHistory::where('client_id', $invoice->client_id)
                ->where('reference_number', $invoice->invoice_number)
                ->where('transaction_type', 'debit')
                ->where('description', 'like', '%(x%')
                ->exists();

            if ($individualPackageEntries) {
                Log::info("Individual package item entries found - package usage detected", [
                    'invoice_id' => $invoice->id,
                    'client_id' => $invoice->client_id,
                    'reference_number' => $invoice->invoice_number
                ]);
                return true;
            }

            Log::info("No package usage detected", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $invoice->client_id,
                'package_sales_count' => $packageSales->count(),
                'individual_package_entries_found' => $individualPackageEntries
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error("Error checking for package usage", [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Default to false to prevent creating package purchase entries when uncertain
            return false;
        }
    }

    /**
     * Build proper package purchase descriptions with package names and tracking numbers
     */
    private function buildPackagePurchaseDescriptions($invoice)
    {
        try {
            Log::info("=== BUILDING PACKAGE PURCHASE DESCRIPTIONS ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number
            ]);

            $packageDetails = [];
            
            // Get all package items from the invoice
            foreach ($invoice->items as $item) {
                $itemModel = \App\Models\Item::find($item['id'] ?? $item['item_id'] ?? null);
                if ($itemModel && $itemModel->type === 'package') {
                    $quantity = $item['quantity'] ?? 1;
                    
                    // Get package tracking records for this package
                    $packageTracking = \App\Models\PackageTracking::where('invoice_id', $invoice->id)
                        ->where('package_item_id', $itemModel->id)
                        ->first();
                    
                    $trackingNumber = $packageTracking ? ($packageTracking->tracking_number ?? "PKG-{$packageTracking->id}") : "PKG-N/A";
                    $packageName = $itemModel->name;
                    
                    $packageDetails[] = [
                        'name' => $packageName,
                        'quantity' => $quantity,
                        'tracking_number' => $trackingNumber
                    ];
                }
            }

            // Build descriptions
            if (empty($packageDetails)) {
                // Fallback to generic description if no package details found
                return [
                    'client_description' => "Package purchase from invoice {$invoice->invoice_number}",
                    'client_notes' => "Package purchased for future use",
                    'business_description' => "Package purchase from invoice {$invoice->invoice_number}"
                ];
            }

            // Build client description with package names and tracking numbers
            $clientPackageNames = [];
            foreach ($packageDetails as $package) {
                $clientPackageNames[] = "{$package['name']} (Ref: {$package['tracking_number']})";
            }
            $clientDescription = implode(', ', $clientPackageNames);
            $clientNotes = "Package purchased: " . implode(', ', array_map(function($p) {
                return "{$p['name']} (Ref: {$p['tracking_number']})";
            }, $packageDetails));

            // Build business description with package names and tracking numbers
            $businessDescription = "Package: " . implode(', ', $clientPackageNames) . " from invoice {$invoice->invoice_number}";

            Log::info("Package purchase descriptions built", [
                'invoice_id' => $invoice->id,
                'package_count' => count($packageDetails),
                'client_description' => $clientDescription,
                'business_description' => $businessDescription,
                'package_details' => $packageDetails,
                'tracking_number_format' => 'PKG-{id}-{timestamp}',
                'description_simplification' => [
                    'removed_verbose_prefixes' => true,
                    'package_name_and_ref_only' => true,
                    'timestamp_format_tracking_numbers' => true
                ]
            ]);

            return [
                'client_description' => $clientDescription,
                'client_notes' => $clientNotes,
                'business_description' => $businessDescription
            ];

        } catch (\Exception $e) {
            Log::error("Error building package purchase descriptions", [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Fallback to generic description on error
            return [
                'client_description' => "Package purchase from invoice {$invoice->invoice_number}",
                'client_notes' => "Package purchased for future use",
                'business_description' => "Package purchase from invoice {$invoice->invoice_number}"
            ];
        }
    }

}
