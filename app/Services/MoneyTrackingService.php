<?php

namespace App\Services;

// Version: 2025-09-20-20:30 - Package functionality with comprehensive logging

use App\Models\MoneyAccount;
use App\Models\MoneyTransfer;
use App\Models\PackageTracking;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Business;
use App\Models\ContractorProfile;
use App\Models\BalanceHistory;
use App\Models\CreditNote;
use App\Models\BusinessBalanceHistory;
use App\Models\ContractorBalanceHistory;
use App\Models\PaymentMethodAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class MoneyTrackingService
{
    /**
     * Initialize money accounts for a business
     */
    public function initializeBusinessAccounts(Business $business)
    {
        $currencyCode = $business->currency_code ?? 'USD';
        $accounts = [
            [
                'name' => 'Package Suspense Account',
                'type' => 'package_suspense_account',
                'description' => 'Holds funds for paid package items if nothing has been used'
            ],
            [
                'name' => 'General Suspense Account',
                'type' => 'general_suspense_account',
                'description' => 'Holds funds for Ordered items not yet offered for all clients'
            ],
            [
                'name' => 'Kashtre Suspense Account',
                'type' => 'kashtre_suspense_account',
                'description' => 'Holds all service fees charged on the invoice'
            ],
            [
                'name' => 'Business Account',
                'type' => 'business_account',
                'description' => 'Holds business funds for sales that have been made'
            ],
            [
                'name' => 'Kashtre Account',
                'type' => 'kashtre_account',
                'description' => 'Holds KASHTRE funds'
            ],
            [
                'name' => 'Withdrawal Suspense Account',
                'type' => 'withdrawal_suspense_account',
                'description' => 'Holds funds for pending withdrawal requests'
            ]
        ];

        foreach ($accounts as $accountData) {
            MoneyAccount::firstOrCreate([
                'business_id' => $business->id,
                'type' => $accountData['type']
            ], [
                'name' => $accountData['name'],
                'description' => $accountData['description'],
                'balance' => 0,
                'currency' => $currencyCode,
                'is_active' => true
            ]);
        }
    }

    /**
     * Create or get client account
     */
    public function getOrCreateClientAccount(Client $client)
    {
        $currencyCode = $client->business->currency_code ?? 'USD';
        return MoneyAccount::firstOrCreate([
            'business_id' => $client->business_id,
            'client_id' => $client->id,
            'type' => 'client_account'
        ], [
            'name' => "Client Account - {$client->name}",
            'description' => "Holds all money paid by client {$client->name}",
            'balance' => 0,
            'currency' => $currencyCode,
            'is_active' => true
        ]);
    }

    /**
     * Create or get client suspense account
     */
    public function getOrCreateClientSuspenseAccount(Client $client)
    {
        $currencyCode = $client->business->currency_code ?? 'USD';
        Log::info("Creating or getting client suspense account", [
            'client_id' => $client->id,
            'client_name' => $client->name,
            'business_id' => $client->business_id
        ]);

        $suspenseAccount = MoneyAccount::firstOrCreate([
            'business_id' => $client->business_id,
            'client_id' => $client->id,
            'type' => 'client_suspense_account'
        ], [
            'name' => "Client Suspense Account - {$client->name}",
            'description' => "Holds client money in suspense until service delivery",
            'balance' => 0,
            'currency' => $currencyCode,
            'is_active' => true
        ]);

        Log::info("Client suspense account result", [
            'client_id' => $client->id,
            'suspense_account_id' => $suspenseAccount->id,
            'suspense_account_balance' => $suspenseAccount->balance,
            'was_created' => $suspenseAccount->wasRecentlyCreated
        ]);

        return $suspenseAccount;
    }

    /**
     * Create or get contractor account
     */
    public function getOrCreateContractorAccount(ContractorProfile $contractor)
    {
        $business = $contractor->business ?? Business::find($contractor->business_id);
        $currencyCode = $business?->currency_code ?? 'USD';
        return MoneyAccount::firstOrCreate([
            'business_id' => $contractor->business_id,
            'contractor_profile_id' => $contractor->id,
            'type' => 'contractor_account'
        ], [
            'name' => "Contractor Account - {$contractor->account_name}",
            'description' => "Holds contractor funds for {$contractor->account_name}",
            'balance' => 0,
            'currency' => $currencyCode,
            'is_active' => true
        ]);
    }

    /**
     * Step 1: Payment received - Money goes to client suspense account
     */
    public function processPaymentReceived(Client $client, $amount, $reference, $paymentMethod, $metadata = [])
    {
        try {
            Log::info("=== PROCESSING PAYMENT RECEIVED ===", [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'amount' => $amount,
                'reference' => $reference,
                'payment_method' => $paymentMethod,
                'metadata' => $metadata
            ]);

            DB::beginTransaction();

            // STEP 1: Check if PaymentMethodAccount exists for this business and payment method
            // If it exists, debit it FIRST before anything else
            $paymentMethodAccount = null;
            if ($paymentMethod) {
                // Normalize payment method (e.g., 'mobile_money', 'cash', 'bank_transfer')
                $normalizedPaymentMethod = $this->normalizePaymentMethod($paymentMethod);
                
                $paymentMethodAccount = PaymentMethodAccount::forBusiness($client->business_id)
                    ->forPaymentMethod($normalizedPaymentMethod)
                    ->active()
                    ->first();
                
                if ($paymentMethodAccount) {
                    Log::info("Payment method account found - debiting first", [
                        'business_id' => $client->business_id,
                        'payment_method' => $normalizedPaymentMethod,
                        'payment_method_account_id' => $paymentMethodAccount->id,
                        'payment_method_account_name' => $paymentMethodAccount->name,
                        'amount_to_debit' => $amount
                    ]);
                    
                    // Debit the payment method account
                    $paymentMethodAccount->debit(
                        $amount,
                        $reference,
                        "Payment received from client {$client->full_name}",
                        $client->id,
                        $metadata['invoice_id'] ?? null,
                        $metadata
                    );
                    
                    Log::info("Payment method account debited successfully", [
                        'payment_method_account_id' => $paymentMethodAccount->id,
                        'amount_debited' => $amount,
                        'new_balance' => $paymentMethodAccount->fresh()->balance
                    ]);
                } else {
                    Log::info("No payment method account found for business and payment method - continuing with existing flow", [
                        'business_id' => $client->business_id,
                        'payment_method' => $normalizedPaymentMethod
                    ]);
                }
            }

            $clientSuspenseAccount = $this->getOrCreateClientSuspenseAccount($client);
            
            // Create or get Mobile Money account (legacy - keeping for backward compatibility)
            $mobileMoneyAccount = $this->getOrCreateMobileMoneyAccount($client->business);
            
            // Create transfer record
            $transfer = MoneyTransfer::create([
                'business_id' => $client->business_id,
                'from_account_id' => $mobileMoneyAccount->id, // Mobile Money account
                'to_account_id' => $clientSuspenseAccount->id,
                'amount' => $amount,
                'currency' => $client->business->currency_code ?? 'USD',
                'status' => 'completed',
                'transfer_type' => 'payment_received',
                'client_id' => $client->id,
                'reference' => $reference,
                'description' => "Mobile Money",
                'metadata' => $metadata,
                'processed_at' => now()
            ]);

            // Credit client suspense account
            $clientSuspenseAccount->credit($amount);

            Log::info("Client suspense account credited", [
                'client_id' => $client->id,
                'suspense_account_id' => $clientSuspenseAccount->id,
                'amount_credited' => $amount,
                'new_balance' => $clientSuspenseAccount->fresh()->balance
            ]);

            // DO NOT create balance statement immediately - will be created after payment completion
            Log::info("Payment received - balance statement will be created after payment completion", [
                'client_id' => $client->id,
                'amount' => $amount,
                'reference' => $reference,
                'payment_method' => $paymentMethod
            ]);

            DB::commit();
            
            Log::info("Payment received: {$amount} UGX for client {$client->name} (in suspense)", [
                'client_id' => $client->id,
                'reference' => $reference,
                'transfer_id' => $transfer->id,
                'suspense_account_id' => $clientSuspenseAccount->id
            ]);

            return $transfer;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Failed to process payment received", [
                'client_id' => $client->id,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Record a direct client deposit that should remain available for future use.
     */
    public function processClientDeposit(Client $client, float $amount, Invoice $invoice, array $items = [])
    {
        if ($amount <= 0) {
            Log::info('Skipping client deposit processing because amount is not greater than zero.', [
                'client_id' => $client->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
            ]);
            return null;
        }

        try {
            DB::beginTransaction();

            $clientAccount = $this->getOrCreateClientAccount($client);
            $clientSuspenseAccount = $this->getOrCreateClientSuspenseAccount($client);

            if ($clientSuspenseAccount->balance >= $amount) {
                Log::info('Transferring deposit from client suspense to client account', [
                    'client_id' => $client->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                    'client_suspense_balance' => $clientSuspenseAccount->balance,
                ]);

                $transfer = $this->transferMoney(
                    $clientSuspenseAccount,
                    $clientAccount,
                    $amount,
                    'client_deposit',
                    $invoice,
                    null,
                    "Client deposit credited",
                    null,
                    'credit'
                );
            } else {
                Log::warning('Client suspense balance insufficient for deposit transfer, falling back to direct credit.', [
                    'client_id' => $client->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                    'client_suspense_balance' => $clientSuspenseAccount->balance,
                ]);

                $transfer = MoneyTransfer::create([
                    'business_id' => $client->business_id,
                    'from_account_id' => null,
                    'to_account_id' => $clientAccount->id,
                    'amount' => $amount,
                    'currency' => $client->business->currency_code ?? 'USD',
                    'status' => 'completed',
                    'type' => 'credit',
                    'transfer_type' => 'client_deposit',
                    'invoice_id' => $invoice->id,
                    'client_id' => $client->id,
                    'description' => "Client deposit recorded for invoice {$invoice->invoice_number}",
                    'metadata' => [
                        'items' => $items,
                        'fallback' => true,
                    ],
                    'processed_at' => now(),
                    'source' => $invoice->client_name ?? $client->name,
                    'destination' => $client->name . ' - ' . $clientAccount->name,
                ]);

                $clientAccount->credit($amount);
            }

            $depositItems = collect($items ?? [])->filter(function ($item) {
                $name = Str::lower(trim((string)($item['displayName'] ?? $item['name'] ?? $item['item_name'] ?? '')));
                return $name === 'deposit';
            });

            $description = 'Deposit Credited';
            if ($depositItems->isNotEmpty()) {
                $deposit = $depositItems->first();
                $displayName = $deposit['displayName'] ?? $deposit['name'] ?? 'Deposit';
                $quantity = $deposit['quantity'] ?? 1;
                $description = sprintf('%s (x%s)', $displayName, $quantity);
            }

            $balanceRecord = BalanceHistory::recordCredit(
                $client,
                $amount,
                $description,
                $invoice->invoice_number,
                'Deposit captured on POS invoice',
                'deposit'
            );

            DB::commit();

            Log::info('Client deposit processed successfully.', [
                'client_id' => $client->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'money_transfer_id' => $transfer->id,
                'balance_history_id' => $balanceRecord->id ?? null,
            ]);

            return $transfer;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to process client deposit.', [
                'client_id' => $client->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process a deductible payment so that it becomes available in the client's wallet.
     *
     * Flow:
     * - Money was already received into the client suspense account via processPaymentReceived.
     * - Here we move that money from client suspense -> client account and create a balance history credit.
     */
    public function processDeductibleTopUp(Client $client, float $amount, string $reference, array $metadata = [])
    {
        if ($amount <= 0) {
            Log::info('Skipping deductible top-up because amount is not greater than zero.', [
                'client_id' => $client->id,
                'amount' => $amount,
                'reference' => $reference,
            ]);
            return null;
        }

        try {
            DB::beginTransaction();

            $clientAccount = $this->getOrCreateClientAccount($client);
            $clientSuspenseAccount = $this->getOrCreateClientSuspenseAccount($client);

            // Prefer moving funds from suspense to client account (they were received there earlier)
            if ($clientSuspenseAccount->balance >= $amount) {
                Log::info('Transferring deductible from client suspense to client account', [
                    'client_id' => $client->id,
                    'amount' => $amount,
                    'client_suspense_balance' => $clientSuspenseAccount->balance,
                    'reference' => $reference,
                ]);

                $transfer = $this->transferMoney(
                    $clientSuspenseAccount,
                    $clientAccount,
                    $amount,
                    'deductible_topup',
                    null,
                    null,
                    'Deductible payment credited to client account'
                );
            } else {
                Log::warning('Client suspense balance insufficient for deductible transfer, falling back to direct credit.', [
                    'client_id' => $client->id,
                    'amount' => $amount,
                    'client_suspense_balance' => $clientSuspenseAccount->balance,
                    'reference' => $reference,
                ]);

                $transfer = MoneyTransfer::create([
                    'business_id' => $client->business_id,
                    'from_account_id' => null,
                    'to_account_id' => $clientAccount->id,
                    'amount' => $amount,
                    'currency' => $client->business->currency_code ?? 'USD',
                    'status' => 'completed',
                    'type' => 'credit',
                    'transfer_type' => 'deductible_topup',
                    'invoice_id' => null,
                    'client_id' => $client->id,
                    'description' => "Deductible payment credited to client account",
                    'metadata' => $metadata,
                    'processed_at' => now(),
                    'source' => $client->name,
                    'destination' => $client->name . ' - ' . $clientAccount->name,
                ]);

                $clientAccount->credit($amount);
            }

            // Record in balance history so it appears on the client's balance statement
            $balanceRecord = BalanceHistory::recordCredit(
                $client,
                $amount,
                'Deductible Payment',
                $reference,
                'Deductible pre-payment captured',
                $metadata['payment_method'] ?? null
            );

            DB::commit();

            Log::info('Deductible top-up processed successfully.', [
                'client_id' => $client->id,
                'amount' => $amount,
                'reference' => $reference,
                'money_transfer_id' => $transfer->id ?? null,
                'balance_history_id' => $balanceRecord->id ?? null,
            ]);

            return $transfer;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to process deductible top-up.', [
                'client_id' => $client->id,
                'amount' => $amount,
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Step 2: Order confirmed - DO NOT create balance statements yet
     * Balance statements should only be created after payment is completed
     * This method now only logs the order confirmation
     */
    public function processOrderConfirmed(Invoice $invoice, $items)
    {
        try {
            $client = $invoice->client;
            $business = $invoice->business;

            Log::info("=== ORDER CONFIRMED - NO BALANCE STATEMENTS CREATED YET ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $client->id,
                'client_name' => $client->name,
                'business_id' => $business->id,
                'items_count' => count($items),
                'total_amount' => $invoice->total_amount,
                'service_charge' => $invoice->service_charge,
                'note' => 'Balance statements will be created only after payment completion'
            ]);

            // Log each item for tracking
            foreach ($items as $index => $itemData) {
                $itemId = $itemData['item_id'] ?? $itemData['id'] ?? null;
                if (!$itemId) continue;
                
                $item = Item::find($itemId);
                if (!$item) continue;
                
                $quantity = $itemData['quantity'] ?? 1;
                $totalAmount = $itemData['total_amount'] ?? ($item->default_price * $quantity);

                Log::info("Order confirmed item " . ($index + 1), [
                    'invoice_id' => $invoice->id,
                    'item_id' => $itemId,
                    'item_name' => $item->name,
                    'item_type' => $item->type,
                    'quantity' => $quantity,
                    'total_amount' => $totalAmount,
                    'note' => 'Balance statement will be created after payment completion'
                ]);
            }

            // Log service charge if applicable
            if ($invoice->service_charge > 0) {
                Log::info("Order confirmed service charge", [
                    'invoice_id' => $invoice->id,
                    'service_charge' => $invoice->service_charge,
                    'note' => 'Service charge balance statement will be created after payment completion'
                ]);
            }

            Log::info("=== ORDER CONFIRMED - WAITING FOR PAYMENT COMPLETION ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'note' => 'No balance statements created yet - will be created after payment completion'
            ]);

            return [
                'status' => 'order_confirmed',
                'message' => 'Order confirmed - balance statements will be created after payment completion',
                'items_count' => count($items),
                'total_amount' => $invoice->total_amount
            ];

        } catch (Exception $e) {
            Log::error("Failed to process order confirmation", [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
        
        // OLD CODE COMMENTED OUT BELOW:
        /*
        try {
            DB::beginTransaction();

            $clientAccount = $this->getOrCreateClientAccount($invoice->client);
            $business = $invoice->business;

            foreach ($items as $itemData) {
                // Handle both 'item_id' and 'id' keys for item identification
                $itemId = $itemData['item_id'] ?? $itemData['id'] ?? null;
                if (!$itemId) {
                    Log::warning("Item data missing item_id/id", ['itemData' => $itemData]);
                    continue;
                }
                
                $item = Item::find($itemId);
                if (!$item) {
                    Log::warning("Item not found", ['itemId' => $itemId]);
                    continue;
                }
                
                $quantity = $itemData['quantity'] ?? 1;
                $totalAmount = $itemData['total_amount'] ?? ($item->price * $quantity);

                // Check if item is active in package tracking
                $packageTracking = PackageTracking::where('client_id', $invoice->client_id)
                    ->where('included_item_id', $item->id)
                    ->where('status', 'active')
                    ->where('valid_until', '>=', now()->toDateString())
                    ->where('remaining_quantity', '>', 0)
                    ->first();

                if ($packageTracking) {
                    // Item is active in package tracking - use package
                    $packageTracking->useQuantity($quantity);
                    
                    // Transfer from client account to package suspense account
                    $packageSuspenseAccount = MoneyAccount::where('business_id', $business->id)
                        ->where('type', 'package_suspense_account')
                        ->first();

                    $this->transferMoney(
                        $clientAccount,
                        $packageSuspenseAccount,
                        $totalAmount,
                        'order_confirmed',
                        $invoice,
                        $item,
                        $item->name
                    );

                } else {
                    // Item not in package - transfer to general suspense account
                    $generalSuspenseAccount = MoneyAccount::where('business_id', $business->id)
                        ->where('type', 'general_suspense_account')
                        ->first();

                    $this->transferMoney(
                        $clientAccount,
                        $generalSuspenseAccount,
                        $totalAmount,
                        'order_confirmed',
                        $invoice,
                        $item,
                        "Order confirmed: {$item->name}"
                    );
                }
            }

            DB::commit();

            Log::info("Order confirmed for invoice {$invoice->invoice_number}", [
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'items_count' => count($items)
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Failed to process order confirmation", [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
        */
    }

    /**
     * Step 2.5: Payment completed - Create balance statements for client
     * This is called after payment is confirmed to create the actual balance statements
     */
    public function processPaymentCompleted(Invoice $invoice, $items)
    {
        try {
            DB::beginTransaction();

            $client = $invoice->client;
            $business = $invoice->business;
            $debitRecords = [];

            $itemsCollection = collect($items ?? []);
            $itemsToProcess = $itemsCollection->reject(function ($itemData) {
                $name = Str::lower(trim((string)($itemData['displayName'] ?? $itemData['name'] ?? $itemData['item_name'] ?? '')));
                return $name === 'deposit';
            })->values();

            $invoiceItems = collect($invoice->items ?? []);
            $isDepositOnlyInvoice = $invoiceItems->isNotEmpty() && $invoiceItems->every(function ($item) {
                $name = Str::lower(trim((string)($item['displayName'] ?? $item['name'] ?? $item['item_name'] ?? '')));
                return $name === 'deposit';
            });

            $invoiceItems = collect($invoice->items ?? []);
            $isDepositOnlyInvoice = $invoiceItems->isNotEmpty() && $invoiceItems->every(function ($item) {
                $name = Str::lower(trim((string)($item['displayName'] ?? $item['name'] ?? $item['item_name'] ?? '')));
                return $name === 'deposit';
            });

            Log::info("=== PAYMENT COMPLETED - CREATING BALANCE STATEMENTS ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $client->id,
                'client_name' => $client->name,
                'business_id' => $business->id,
                'items_count' => $itemsToProcess->count(),
                'total_amount' => $invoice->total_amount,
                'service_charge' => $invoice->service_charge,
                'amount_paid' => $invoice->amount_paid,
                'account_balance_adjustment' => $invoice->account_balance_adjustment
            ]);

            $invoiceItems = collect($invoice->items ?? []);
            $isDepositItem = static function ($item): bool {
                // Ensure item is array, handle both array and string cases
                if (!is_array($item)) {
                    if (is_string($item)) {
                        // If it's a string, it's not a valid item for checking
                        return false;
                    }
                    return false;
                }
                $name = Str::lower(trim((string)($item['displayName'] ?? $item['name'] ?? $item['item_name'] ?? '')));
                return $name === 'deposit';
            };
            $isDepositOnlyInvoice = $invoiceItems->isNotEmpty() && $invoiceItems->every($isDepositItem);

            // Check if this is an insurance invoice — client credits/debits are handled separately
            $authSnapshotForGuard = is_array($invoice->insurance_authorization_snapshot)
                ? $invoice->insurance_authorization_snapshot
                : json_decode($invoice->insurance_authorization_snapshot, true);
            $isInsuranceInvoice = !empty($authSnapshotForGuard) && (
                isset($authSnapshotForGuard['vendors']) || isset($authSnapshotForGuard['insurance_total'])
            ) && (float) ($authSnapshotForGuard['insurance_total'] ?? 0) > 0;

            // First, create CREDIT record for payment received (skip for insurance invoices)
            if ($invoice->amount_paid > 0 && !$isInsuranceInvoice) {
                $paymentMethods = $invoice->payment_methods ?? [];
                $primaryMethod = !empty($paymentMethods) ? $paymentMethods[0] : 'cash';

                $creditRecord = BalanceHistory::recordCredit(
                    $client,
                    $invoice->amount_paid,
                    "Mobile Money",
                    $invoice->invoice_number,
                    "Mobile Money",
                    $primaryMethod
                );

                Log::info("Payment received credit created", [
                    'invoice_id' => $invoice->id,
                    'amount' => $invoice->amount_paid,
                    'payment_method' => $primaryMethod,
                    'balance_history_id' => $creditRecord->id ?? null
                ]);
            }

            // Create CREDIT record for balance adjustment if used (skip for insurance invoices)
            if ($invoice->account_balance_adjustment > 0 && ! $isDepositOnlyInvoice && !$isInsuranceInvoice) {
                $balanceCreditRecord = BalanceHistory::recordCredit(
                    $client,
                    $invoice->account_balance_adjustment,
                    "Balance Adjustment Used",
                    $invoice->invoice_number,
                    "POS Balance Adjustment"
                );

                Log::info("Balance adjustment credit created", [
                    'invoice_id' => $invoice->id,
                    'amount' => $invoice->account_balance_adjustment,
                    'balance_history_id' => $balanceCreditRecord->id ?? null
                ]);
            }

            // Check if this is a multi-vendor insurance invoice
            $authSnapshot = is_array($invoice->insurance_authorization_snapshot) 
                ? $invoice->insurance_authorization_snapshot 
                : json_decode($invoice->insurance_authorization_snapshot, true);
            $isMultiVendorInsurance = $authSnapshot && ($authSnapshot['multi_vendor'] ?? false);
            
            if ($isMultiVendorInsurance && isset($authSnapshot['vendors'])) {
                // For multi-vendor insurance, keep vendor-side ledger updates.
                // Client statement rows are itemized later in this method.
                Log::info("=== MULTI-VENDOR INSURANCE DETECTED - Processing vendor ledger entries ===", [
                    'invoice_id' => $invoice->id,
                    'vendor_count' => count($authSnapshot['vendors'])
                ]);
                
                foreach ($authSnapshot['vendors'] as $vendor) {
                    $vendorStatus = (string) ($vendor['authorization_status'] ?? '');
                    if (in_array($vendorStatus, ['failed', 'skipped'], true)) {
                        continue;
                    }

                    $vendorName = $vendor['vendor_name'] ?? $vendor['insurance_company_name'] ?? 'Unknown Insurance';
                    // Use per-vendor allocated client share for multi-vendor cascades.
                    $clientPortion = $vendor['client_portion_allocated'] ?? $vendor['client_total'] ?? $vendor['client_portion'] ?? 0;
                    $insurancePortion = $vendor['insurance_total'] ?? $vendor['insurance_portion'] ?? 0;
                    $totalVendorAmount = $clientPortion + $insurancePortion;
                    Log::info("Insurance vendor allocation tracked for ledger", [
                        'invoice_id' => $invoice->id,
                        'vendor_name' => $vendorName,
                        'client_portion' => $clientPortion,
                        'insurance_portion' => $insurancePortion,
                        'total_amount' => $totalVendorAmount
                    ]);
                    
                    // Also create vendor account entry (ThirdPartyPayerBalanceHistory)
                    // Find the vendor by name (vendor_id in snapshot is unreliable)
                    if ($vendorName && $insurancePortion > 0) {
                        $thirdPartyPayer = \App\Models\ThirdPartyPayer::where('name', $vendorName)
                            ->where('business_id', $business->id)
                            ->where('type', 'insurance_company')
                            ->whereNull('client_id')
                            ->first();

                        if ($thirdPartyPayer) {
                            // Create debit entry (insurance guarantee/invoice) if not already recorded
                            $existingDebit = \App\Models\ThirdPartyPayerBalanceHistory::where('third_party_payer_id', $thirdPartyPayer->id)
                                ->where('invoice_id', $invoice->id)
                                ->where('transaction_type', 'debit')
                                ->first();
                            if (!$existingDebit) {
                                \App\Models\ThirdPartyPayerBalanceHistory::recordDebit(
                                    $thirdPartyPayer,
                                    $insurancePortion,
                                    'Insurance guarantee for invoice ' . $invoice->invoice_number,
                                    $invoice->invoice_number,
                                    'Posted on payment completion',
                                    'insurance',
                                    $invoice->id,
                                    $client->id
                                );
                                Log::info("Vendor debit entry created", [
                                    'invoice_id' => $invoice->id,
                                    'vendor_id' => $thirdPartyPayer->id,
                                    'vendor_name' => $vendorName,
                                    'amount' => $insurancePortion
                                ]);
                            }

                            // Do not post an insurer "payment received" credit here. Client MM/cash only covers
                            // the client portion; the guarantee debit above remains until insurer settlement.
                        }
                    }
                }
                
            }

            foreach ($itemsToProcess as $index => $itemData) {
                $itemId = $itemData['item_id'] ?? $itemData['id'] ?? null;
                if (!$itemId) continue;
                
                $item = Item::find($itemId);
                if (!$item) continue;
                
                $quantity = $itemData['quantity'] ?? 1;
                $totalAmount = $itemData['total_amount'] ?? ($item->default_price * $quantity);

                // Check if this item is part of a package adjustment
                $isPackageAdjustmentItem = false;
                if ($invoice->package_adjustment > 0) {
                    // Check if this specific item is included in any valid packages
                    $validPackages = \App\Models\PackageTracking::where('client_id', $invoice->client_id)
                        ->where('business_id', $invoice->business_id)
                        ->where('status', 'active')
                        ->where('remaining_quantity', '>', 0)
                        ->get();
                    
                    foreach ($validPackages as $packageTracking) {
                        $packageItems = $packageTracking->packageItem->packageItems;
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
                    Log::info("SKIPPING CLIENT DEBIT FOR PACKAGE ITEM", [
                        'item_id' => $itemId,
                        'item_name' => $item->name,
                        'reason' => 'Item is covered by package adjustment - no debit needed'
                    ]);
                    continue;
                }

                // Create balance statement record.
                // Insurance invoices should still be itemized but must not affect client balance.
                $itemDisplayName = $item->name;
                $debitRecord = null;
                if ($isInsuranceInvoice) {
                    $currentBalance = BalanceHistory::where('client_id', $client->id)
                        ->orderBy('created_at', 'desc')
                        ->value('new_balance') ?? 0;

                    $debitRecord = BalanceHistory::create([
                        'client_id' => $client->id,
                        'business_id' => $business->id,
                        'branch_id' => $client->branch_id,
                        'invoice_id' => $invoice->id,
                        'user_id' => auth()->id() ?? 1,
                        'transaction_type' => 'debit',
                        'payment_method' => 'insurance',
                        'description' => "{$itemDisplayName} (x{$quantity}) [Insurance]",
                        'previous_balance' => $currentBalance,
                        'change_amount' => $totalAmount,
                        'new_balance' => $currentBalance,
                        'reference_number' => $invoice->invoice_number,
                        'notes' => "{$itemDisplayName} (x{$quantity})",
                        'payment_status' => 'Paid',
                    ]);
                } else {
                    $debitRecord = BalanceHistory::recordDebit(
                        $client,
                        $totalAmount,
                        "{$itemDisplayName} (x{$quantity})",
                        $invoice->invoice_number,
                        "{$itemDisplayName} (x{$quantity})"
                    );
                }

                $debitRecords[] = [
                    'item_name' => $item->name,
                    'quantity' => $quantity,
                    'amount' => $totalAmount,
                    'type' => 'item_payment',
                    'status' => 'completed',
                    'balance_history_id' => $debitRecord->id ?? null
                ];

                Log::info("Balance statement created for item " . ($index + 1), [
                    'invoice_id' => $invoice->id,
                    'item_id' => $itemId,
                    'item_name' => $item->name,
                    'item_display_name' => $itemDisplayName,
                    'quantity' => $quantity,
                    'amount' => $totalAmount,
                    'balance_history_id' => $debitRecord->id ?? null,
                    'note' => 'Using actual item name from database'
                ]);
            }

            // Create debit record for service charge if applicable.
            // For insurance invoices, service fee is part of insurance/client-allocation tracking
            // and must not hit client balance as a standalone debit.
            if ($invoice->service_charge > 0 && !$isInsuranceInvoice) {
                $serviceChargeRecord = BalanceHistory::recordDebit(
                    $client,
                    $invoice->service_charge,
                    "Service Fee",
                    $invoice->invoice_number,
                    "Service Fee"
                );

                $debitRecords[] = [
                    'item_name' => 'Service Charge',
                    'quantity' => 1,
                    'amount' => $invoice->service_charge,
                    'type' => 'service_charge_payment',
                    'status' => 'completed',
                    'balance_history_id' => $serviceChargeRecord->id ?? null
                ];

                Log::info("Balance statement created for service charge", [
                    'invoice_id' => $invoice->id,
                    'service_charge' => $invoice->service_charge,
                    'balance_history_id' => $serviceChargeRecord->id ?? null
                ]);
            }

            DB::commit();

            Log::info("=== PAYMENT COMPLETED - BALANCE STATEMENTS CREATED ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $client->id,
                'debit_records_count' => count($debitRecords),
                'total_amount' => $invoice->total_amount
            ]);

            // Send electronic receipts to all parties
            try {
                Log::info("=== STARTING ELECTRONIC RECEIPTS PROCESS ===", [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $invoice->client_id,
                    'business_id' => $invoice->business_id,
                    'total_amount' => $invoice->total_amount,
                    'service_charge' => $invoice->service_charge,
                    'payment_status' => $invoice->payment_status
                ]);

                $receiptService = new \App\Services\ReceiptService();
                $result = $receiptService->sendElectronicReceipts($invoice);
                
                Log::info("=== ELECTRONIC RECEIPTS PROCESS COMPLETED ===", [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'receipt_service_result' => $result ? 'success' : 'failed'
                ]);
            } catch (\Exception $e) {
                Log::error("=== ELECTRONIC RECEIPTS PROCESS FAILED ===", [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'error' => $e->getMessage(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Don't throw the exception - receipt failure shouldn't stop the payment completion
            }

            return $debitRecords;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Failed to process payment completion balance statements", [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Process suspense account movements after payment completion
     * This moves money from client suspense account to appropriate suspense accounts
     */
    public function processSuspenseAccountMovements(Invoice $invoice, $items)
    {
        $isDepositOnlyInvoice = false;

        try {
            DB::beginTransaction();

            $client = $invoice->client;
            $business = $invoice->business;
            $clientSuspenseAccount = $this->getOrCreateClientSuspenseAccount($client);
            $suspenseMovements = [];

            $itemsCollection = collect($items ?? []);
            $itemsToProcess = $itemsCollection->reject(function ($itemData) {
                $name = Str::lower(trim((string)($itemData['displayName'] ?? $itemData['name'] ?? $itemData['item_name'] ?? '')));
                return $name === 'deposit';
            })->values();

            Log::info("=== PROCESSING SUSPENSE ACCOUNT MOVEMENTS ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $client->id,
                'business_id' => $business->id,
                'items_count' => $itemsToProcess->count(),
                'client_suspense_balance_before' => $clientSuspenseAccount->balance
            ]);

            // Process regular items
            foreach ($itemsToProcess as $index => $itemData) {
                $itemId = $itemData['item_id'] ?? $itemData['id'] ?? null;
                if (!$itemId) continue;
                
                $item = Item::find($itemId);
                if (!$item) continue;
                
                // Skip payment-related items (Mobile Money, Cash, etc.)
                if ($item->name === 'Mobile Money' || 
                    $item->name === 'Cash' || 
                    str_contains(strtolower($item->name), 'payment') ||
                    str_contains(strtolower($item->name), 'mobile money')) {
                    Log::info("⏭️ SKIPPING PAYMENT ITEM", [
                        'item_id' => $item->id,
                        'item_name' => $item->name,
                        'reason' => 'Payment method item, not a product/service'
                    ]);
                    continue;
                }
                
                $quantity = $itemData['quantity'] ?? 1;
                $totalAmount = $itemData['total_amount'] ?? ($item->default_price * $quantity);
                $displayQuantity = $this->resolveQuantityForDisplay($quantity, $itemData, $totalAmount, $item);

                Log::info("🔍 PROCESSING SUSPENSE MOVEMENT FOR ITEM " . ($index + 1), [
                    'item_id' => $itemId,
                    'item_name' => $item->name,
                    'item_type' => $item->type,
                    'quantity' => $displayQuantity,
                    'total_amount' => $totalAmount,
                    'item_data' => $itemData
                ]);

                // Determine destination suspense account based on item type
                $destinationAccount = null;
                $transferDescription = "";
                $routingReason = "";
                
                // Check if this is a service fee (should go to Kashtre Suspense)
                if ($item->name === 'Service Fee' || str_contains(strtolower($item->name), 'service fee')) {
                    $destinationAccount = $this->getOrCreateKashtreSuspenseAccount($business, $client->id);
                    $transferDescription = "Service Fee - {$item->name} ({$displayQuantity})";
                    $routingReason = "Service fee detected by name match";
                    
                    Log::info("✅ ITEM ROUTED TO KASHTRE SUSPENSE", [
                        'item_id' => $item->id,
                        'item_name' => $item->name,
                        'item_type' => $item->type,
                        'destination_account_type' => 'kashtre_suspense_account',
                        'routing_reason' => $routingReason,
                        'amount' => $totalAmount
                    ]);
                }
                // Check if this is a package item (should go to Package Suspense)
                elseif ($item->type === 'package' || 
                        str_contains(strtolower($item->name), 'package') || 
                        str_contains(strtolower($item->name), 'procedure') ||
                        str_contains(strtolower($item->name), 'minor procedure') ||
                        str_contains(strtolower($item->name), 'major procedure')) {
                    
                    // Get package tracking number for this package item
                    $packageTrackingNumber = null;
                    $packageTracking = \App\Models\PackageTracking::where('invoice_id', $invoice->id)
                        ->where('package_item_id', $itemId)
                        ->where('client_id', $client->id)
                        ->first();
                    
                    if ($packageTracking) {
                        $packageTrackingNumber = $packageTracking->tracking_number;
                        Log::info("📦 PACKAGE TRACKING NUMBER FOUND", [
                            'package_tracking_id' => $packageTracking->id,
                            'tracking_number' => $packageTrackingNumber,
                            'package_item_id' => $itemId,
                            'invoice_id' => $invoice->id
                        ]);
                    } else {
                        Log::warning("⚠️ PACKAGE TRACKING NOT FOUND", [
                            'package_item_id' => $itemId,
                            'invoice_id' => $invoice->id,
                            'client_id' => $client->id,
                            'item_name' => $item->name,
                            'item_type' => $item->type
                        ]);
                    }
                    
                    $destinationAccount = $this->getOrCreatePackageSuspenseAccount($business, $client->id);
                    $transferDescription = "Package Item - {$item->name} ({$displayQuantity})" . 
                        ($packageTrackingNumber ? " - Tracking: {$packageTrackingNumber}" : "");
                    $routingReason = "Package/procedure item detected by name or type";
                    
                    Log::info("✅ ITEM ROUTED TO PACKAGE SUSPENSE", [
                        'item_id' => $item->id,
                        'item_name' => $item->name,
                        'item_type' => $item->type,
                        'destination_account_type' => 'package_suspense_account',
                        'routing_reason' => $routingReason,
                        'amount' => $totalAmount,
                        'package_tracking_number' => $packageTrackingNumber,
                        'package_tracking_id' => $packageTracking ? $packageTracking->id : null
                    ]);
                }
                // Check if this item is part of a package adjustment (should be SKIPPED from suspense movements)
                elseif ($invoice->package_adjustment > 0) {
                    // Check if this specific item is included in any valid packages
                    $isPackageAdjustmentItem = false;
                    $validPackages = \App\Models\PackageTracking::where('client_id', $client->id)
                        ->where('business_id', $business->id)
                        ->where('status', 'active')
                        ->where('remaining_quantity', '>', 0)
                        ->get();
                    
                    foreach ($validPackages as $packageTracking) {
                        $packageItems = $packageTracking->packageItem->packageItems;
                        foreach ($packageItems as $packageItem) {
                            if ($packageItem->included_item_id == $itemId) {
                                $isPackageAdjustmentItem = true;
                                break 2;
                            }
                        }
                    }
                    
                    if ($isPackageAdjustmentItem) {
                        // SKIP package adjustment items - they should not create suspense account entries
                        Log::info("⏭️ SKIPPING PACKAGE ADJUSTMENT ITEM FROM SUSPENSE MOVEMENTS", [
                            'item_id' => $item->id,
                            'item_name' => $item->name,
                            'item_type' => $item->type,
                            'reason' => 'Package adjustment items should not create suspense account entries',
                            'amount' => $totalAmount,
                            'package_adjustment' => $invoice->package_adjustment
                        ]);
                        continue; // Skip this item entirely
                    } else {
                        // Not a package adjustment item, route to general suspense
                        $destinationAccount = $this->getOrCreateGeneralSuspenseAccount($business, $client->id);
                        $transferDescription = "General Item - {$item->name} ({$displayQuantity})";
                        $routingReason = "Item not part of package adjustment";
                        
                        Log::info("✅ ITEM ROUTED TO GENERAL SUSPENSE (NOT PACKAGE ADJUSTMENT)", [
                            'item_id' => $item->id,
                            'item_name' => $item->name,
                            'item_type' => $item->type,
                            'destination_account_type' => 'general_suspense_account',
                            'routing_reason' => $routingReason,
                            'amount' => $totalAmount,
                            'package_adjustment' => $invoice->package_adjustment
                        ]);
                    }
                }
                // All other items go to General Suspense
                else {
                    $destinationAccount = $this->getOrCreateGeneralSuspenseAccount($business, $client->id);
                    $transferDescription = "General Item - {$item->name} ({$displayQuantity})";
                    $routingReason = "Default routing for non-package, non-service items";
                    
                    Log::info("✅ ITEM ROUTED TO GENERAL SUSPENSE", [
                        'item_id' => $item->id,
                        'item_name' => $item->name,
                        'item_type' => $item->type,
                        'destination_account_type' => 'general_suspense_account',
                        'routing_reason' => $routingReason,
                        'amount' => $totalAmount
                    ]);
                }

                // Transfer from client suspense to destination suspense (same for all clients)
                Log::info("💰 INITIATING SUSPENSE ACCOUNT TRANSFER", [
                    'from_account_id' => $clientSuspenseAccount->id,
                    'from_account_name' => $clientSuspenseAccount->name,
                    'from_account_balance_before' => $clientSuspenseAccount->balance,
                    'to_account_id' => $destinationAccount->id,
                    'to_account_name' => $destinationAccount->name,
                    'to_account_balance_before' => $destinationAccount->balance,
                    'amount' => $totalAmount,
                    'description' => $transferDescription,
                    'routing_reason' => $routingReason,
                    'item_type' => $item->type,
                    'item_name' => $item->name
                ]);

                $transfer = $this->transferMoney(
                    $clientSuspenseAccount,
                    $destinationAccount,
                    $totalAmount,
                    'suspense_movement',
                    $invoice,
                    $item,
                    $transferDescription,
                    $packageTrackingNumber ?? null
                );

                $suspenseMovements[] = [
                    'item_name' => $item->name,
                    'quantity' => $displayQuantity,
                    'amount' => $totalAmount,
                    'source_account' => $clientSuspenseAccount->name,
                    'destination_account' => $destinationAccount->name,
                    'transfer_id' => $transfer->id,
                    'package_tracking_number' => $packageTrackingNumber ?? null
                ];

                Log::info("✅ SUSPENSE ACCOUNT TRANSFER COMPLETED", [
                    'transfer_id' => $transfer->id,
                    'from_account_balance_after' => $clientSuspenseAccount->fresh()->balance,
                    'to_account_balance_after' => $destinationAccount->fresh()->balance,
                    'amount_transferred' => $totalAmount,
                    'item_name' => $item->name,
                    'item_type' => $item->type,
                    'routing_reason' => $routingReason,
                    'package_tracking_number' => $packageTrackingNumber ?? null
                ]);
            }

            // Process Service Fee separately if it exists
            if ($invoice->service_charge > 0) {
                Log::info("🔍 PROCESSING SERVICE FEE FOR SUSPENSE MOVEMENT", [
                    'invoice_id' => $invoice->id,
                    'service_charge' => $invoice->service_charge,
                    'invoice_number' => $invoice->invoice_number
                ]);

                $destinationAccount = $isDepositOnlyInvoice
                    ? $this->getOrCreateKashtreAccount()
                    : $this->getOrCreateKashtreSuspenseAccount($business, $client->id);

                // Keep description currency-neutral; amount column already renders canonical account currency.
                $transferDescription = 'Service Fee';
                $routingReason = "Service fee from invoice service_charge field";

                Log::info("✅ SERVICE FEE ROUTED", [
                    'service_charge' => $invoice->service_charge,
                    'destination_account_type' => $isDepositOnlyInvoice ? 'kashtre_account' : 'kashtre_suspense_account',
                    'routing_reason' => $routingReason,
                    'is_deposit_only_invoice' => $isDepositOnlyInvoice
                ]);

                // Transfer from client suspense to destination suspense (same for all clients)
                Log::info("Initiating service fee suspense account transfer", [
                    'from_account_id' => $clientSuspenseAccount->id,
                    'from_account_name' => $clientSuspenseAccount->name,
                    'to_account_id' => $destinationAccount->id,
                    'to_account_name' => $destinationAccount->name,
                    'amount' => $invoice->service_charge,
                    'description' => $transferDescription
                ]);

                $transfer = $this->transferMoney(
                    $clientSuspenseAccount,
                    $destinationAccount,
                    $invoice->service_charge,
                    'service_charge',
                    $invoice,
                    null,
                    $transferDescription
                );

                $suspenseMovements[] = [
                    'item_name' => 'Service Fee',
                    'quantity' => 1,
                    'amount' => $invoice->service_charge,
                    'source_account' => $clientSuspenseAccount->name,
                    'destination_account' => $destinationAccount->name,
                    'transfer_id' => $transfer->id
                ];

                Log::info("Service fee suspense account transfer completed", [
                    'transfer_id' => $transfer->id,
                    'from_account_balance_after' => $clientSuspenseAccount->fresh()->balance,
                    'to_account_balance_after' => $destinationAccount->fresh()->balance
                ]);
            }

            DB::commit();

            // Get final account balances for summary
            $finalClientSuspenseBalance = $clientSuspenseAccount->fresh()->balance;
            $finalPackageSuspenseBalance = $this->getOrCreatePackageSuspenseAccount($business, $client->id)->fresh()->balance;
            $finalGeneralSuspenseBalance = $this->getOrCreateGeneralSuspenseAccount($business, $client->id)->fresh()->balance;
            $finalKashtreSuspenseBalance = $this->getOrCreateKashtreSuspenseAccount($business, $client->id)->fresh()->balance;

            Log::info("🎉 === SUSPENSE ACCOUNT MOVEMENTS COMPLETED ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $client->id,
                'business_id' => $business->id,
                'movements_count' => count($suspenseMovements),
                'total_amount_moved' => array_sum(array_column($suspenseMovements, 'amount')),
                'client_suspense_balance_after' => $finalClientSuspenseBalance,
                'package_suspense_balance_after' => $finalPackageSuspenseBalance,
                'general_suspense_balance_after' => $finalGeneralSuspenseBalance,
                'kashtre_suspense_balance_after' => $finalKashtreSuspenseBalance,
                'total_suspense_balance' => $finalPackageSuspenseBalance + $finalGeneralSuspenseBalance + $finalKashtreSuspenseBalance,
                'movements_summary' => $suspenseMovements
            ]);

            return $suspenseMovements;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("❌ FAILED TO PROCESS SUSPENSE ACCOUNT MOVEMENTS", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number ?? 'Unknown',
                'client_id' => $invoice->client_id ?? 'Unknown',
                'business_id' => $invoice->business_id ?? 'Unknown',
                'items_count' => count($items ?? []),
                'error' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Step 3: Service delivered - Money moves to final accounts
     */
    public function processServiceDelivered(Invoice $invoice, $itemId, $quantity = 1)
    {
        try {
            DB::beginTransaction();

            $item = Item::find($itemId);
            $business = $invoice->business;
            $client = $invoice->client;

            // Calculate amount based on item price and quantity
            $amount = $item->price * $quantity;

            // Determine source account (package suspense or general suspense)
            $packageSuspenseAccount = MoneyAccount::where('business_id', $business->id)
                ->where('type', 'package_suspense_account')
                ->first();

            $generalSuspenseAccount = MoneyAccount::where('business_id', $business->id)
                ->where('type', 'general_suspense_account')
                ->first();

            $sourceAccount = null;
            $transferDescription = "";

            // Check if item is from package
            $packageTracking = PackageTracking::where('client_id', $client->id)
                ->where('included_item_id', $item->id)
                ->where('status', 'active')
                ->where('remaining_quantity', '>', 0)
                ->first();

            if ($packageTracking) {
                $sourceAccount = $packageSuspenseAccount;
                $transferDescription = "Package service delivered: {$item->name}";
            } else {
                $sourceAccount = $generalSuspenseAccount;
                $transferDescription = "Service delivered: {$item->name}";
            }

            // Determine destination account based on item ownership
            if ($item->contractor_account_id) {
                // Contractor item
                $contractor = ContractorProfile::find($item->contractor_account_id);
                $destinationAccount = $this->getOrCreateContractorAccount($contractor);
            } else {
                // Business item
                $destinationAccount = MoneyAccount::where('business_id', $business->id)
                    ->where('type', 'business_account')
                    ->first();
            }

            // Transfer money
            $this->transferMoney(
                $sourceAccount,
                $destinationAccount,
                $amount,
                'service_delivered',
                $invoice,
                $item,
                $transferDescription
            );

            DB::commit();

            Log::info("Service delivered", [
                'invoice_id' => $invoice->id,
                'item_id' => $itemId,
                'quantity' => $quantity,
                'amount' => $amount
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Failed to process service delivery", [
                'invoice_id' => $invoice->id,
                'item_id' => $itemId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Process service charge - Show immediate debit to client
     * Money stays in client suspense account, but debit is shown immediately
     * Actual money movement happens later when "save and exit" is clicked
     */
    public function processServiceCharge(Invoice $invoice, $serviceChargeAmount)
    {
        try {
            if ($serviceChargeAmount <= 0) {
                return null;
            }

            $client = $invoice->client;

            // Create debit record for service charge to show client where their money is allocated
            // Money stays in suspense account, but client sees the debit
            $debitRecord = BalanceHistory::recordDebit(
                $client,
                $serviceChargeAmount,
                "Service Charge Allocated",
                $invoice->invoice_number,
                "Service charge allocated for invoice {$invoice->invoice_number}"
            );

            Log::info("Service charge debit recorded for client (money in suspense)", [
                'invoice_id' => $invoice->id,
                'client_id' => $client->id,
                'service_charge_amount' => $serviceChargeAmount
            ]);

            return $debitRecord;

        } catch (Exception $e) {
            Log::error("Failed to process service charge debit", [
                'invoice_id' => $invoice->id,
                'service_charge_amount' => $serviceChargeAmount,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
        
        // OLD CODE COMMENTED OUT BELOW:
        /*
        try {
            DB::beginTransaction();

            $clientAccount = $this->getOrCreateClientAccount($invoice->client);
            $kashtreSuspenseAccount = MoneyAccount::where('business_id', $invoice->business_id)
                ->where('type', 'kashtre_suspense_account')
                ->first();

            $this->transferMoney(
                $clientAccount,
                $kashtreSuspenseAccount,
                $serviceChargeAmount,
                'service_charge',
                $invoice,
                null,
                "Service charge for invoice {$invoice->invoice_number}"
            );

            DB::commit();

            Log::info("Service charge processed", [
                'invoice_id' => $invoice->id,
                'amount' => $serviceChargeAmount
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Failed to process service charge", [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
        */
    }

    /**
     * Process refund
     */
    public function processRefund(Client $client, $amount, $reason, $approvedBy = null, CreditNote $creditNote = null)
    {
        try {
            DB::beginTransaction();

            $clientAccount = $this->getOrCreateClientAccount($client);
            $previousBalance = $clientAccount->balance;

            $loadedCreditNote = $creditNote?->loadMissing([
                'serviceDeliveryQueue.item',
                'serviceDeliveryQueue.invoice',
                'invoice',
                'business',
            ]);

            $invoice = $loadedCreditNote?->invoice ?? $loadedCreditNote?->serviceDeliveryQueue?->invoice;
            $item = $loadedCreditNote?->serviceDeliveryQueue?->item;
            $business = $invoice?->business ?? $loadedCreditNote?->business;

            if (! $business && $client->business_id) {
                $business = Business::find($client->business_id);
            }

            $sourceAccount = $this->resolveRefundSourceAccount($client, $loadedCreditNote, $invoice, $item);

            if (! $sourceAccount) {
                throw new Exception('Unable to determine suspense account for refund processing.');
            }

            $sourceAccountStartingBalance = $sourceAccount->balance;
            $sourceAccountType = $sourceAccount->type;
            $sourceAccountBusinessId = $sourceAccount->business_id;
            $sourceAccountId = $sourceAccount->id;

            $description = "Refund: {$reason}";

            $transfer = $this->transferMoney(
                $sourceAccount,
                $clientAccount,
                $amount,
                'refund_approved',
                $invoice,
                $item,
                $description,
                null,
                'debit'
            );

            $newBalance = $clientAccount->fresh()->balance;

            BalanceHistory::create([
                'client_id' => $client->id,
                'business_id' => $client->business_id,
                'branch_id' => $client->branch_id,
                'user_id' => $approvedBy,
                'previous_balance' => $previousBalance,
                'change_amount' => $amount,
                'new_balance' => $newBalance,
                'transaction_type' => 'credit',
                'description' => "Refund credited for {$reason}",
                'reference_number' => $loadedCreditNote?->credit_note_number,
                'notes' => 'Credit note workflow refund',
                'payment_method' => 'refund',
            ]);

            $sourceAccountFresh = $sourceAccount->fresh();
            $sourceAccountEndingBalance = $sourceAccountFresh->balance;

            if (in_array($sourceAccountType, [
                'general_suspense_account',
                'package_suspense_account',
                'kashtre_suspense_account',
            ])) {
                $rawQuantity = $loadedCreditNote?->serviceDeliveryQueue?->quantity ?? 1;
                $displayQuantity = $this->resolveQuantityForDisplay($rawQuantity, [], null, $item);

                $shouldRecordSuspenseHistory = true;

                if ($loadedCreditNote) {
                    $shouldRecordSuspenseHistory = ! BusinessBalanceHistory::where('money_account_id', $sourceAccountId)
                        ->where('reference_type', 'credit_note')
                        ->where('reference_id', $loadedCreditNote->id)
                        ->where('type', 'debit')
                        ->exists();
                }

                if ($shouldRecordSuspenseHistory) {
                    $history = new BusinessBalanceHistory();
                    $history->business_id = $sourceAccountBusinessId;
                    $history->money_account_id = $sourceAccountId;
                    $history->previous_balance = $sourceAccountStartingBalance;
                    $history->amount = -1 * abs($amount);
                    $history->new_balance = $sourceAccountEndingBalance;
                    $history->type = 'debit';
                    $history->description = $item
                        ? "Refund debit for {$item->name} ({$displayQuantity})"
                        : "Refund debit processed";
                    $history->reference_type = $loadedCreditNote ? 'credit_note' : null;
                    $history->reference_id = $loadedCreditNote?->id;
                    $history->metadata = [
                        'credit_note_number' => $loadedCreditNote?->credit_note_number,
                        'transfer_id' => $transfer->id,
                        'invoice_id' => $invoice?->id,
                        'item_id' => $item?->id,
                        'type' => 'item_refund',
                    ];
                    $history->user_id = $approvedBy;
                    $history->save();

                    $sourceAccountFresh->forceFill(['balance' => $sourceAccountEndingBalance])->save();
                }
            }

            if ($invoice && $invoice->service_charge > 0 && $business) {
                $invoiceItems = collect($invoice->items ?? []);
                $billableItemsCount = $invoiceItems->filter(function ($invoiceItem) {
                    return ! empty($invoiceItem['id']);
                })->count();

                $refundItemMatchesInvoice = $item
                    ? $invoiceItems->contains(function ($invoiceItem) use ($item) {
                        return (string) ($invoiceItem['id'] ?? null) === (string) $item->id;
                    })
                    : false;

                if ($billableItemsCount <= 1 && $refundItemMatchesInvoice) {
                    $kashtreSuspenseAccount = $this->getOrCreateKashtreSuspenseAccount($business, $client->id);
                    $kashtreStartingBalance = $kashtreSuspenseAccount->balance;

                    $serviceFeeDescription = "Service fee refund for {$invoice->invoice_number}";

                    $hasExistingServiceFeeHistory = BusinessBalanceHistory::where('money_account_id', $kashtreSuspenseAccount->id)
                        ->where('reference_type', 'credit_note')
                        ->where('reference_id', $loadedCreditNote?->id)
                        ->where('type', 'debit')
                        ->where('description', 'like', 'Service fee refund%')
                        ->exists();

                    if (! $hasExistingServiceFeeHistory) {
                        $serviceFeeTransfer = $this->transferMoney(
                            $kashtreSuspenseAccount,
                            $clientAccount,
                            $invoice->service_charge,
                            'service_fee_refund',
                            $invoice,
                            null,
                            $serviceFeeDescription,
                            null,
                            'debit'
                        );

                        $kashtreEndingBalance = $kashtreSuspenseAccount->fresh()->balance;

                        $serviceFeeHistory = new BusinessBalanceHistory();
                        $serviceFeeHistory->business_id = $kashtreSuspenseAccount->business_id;
                        $serviceFeeHistory->money_account_id = $kashtreSuspenseAccount->id;
                        $serviceFeeHistory->previous_balance = $kashtreStartingBalance;
                        $serviceFeeHistory->amount = -1 * abs($invoice->service_charge);
                        $serviceFeeHistory->new_balance = $kashtreEndingBalance;
                        $serviceFeeHistory->type = 'debit';
                        $serviceFeeHistory->description = $serviceFeeDescription;
                        $serviceFeeHistory->reference_type = $loadedCreditNote ? 'credit_note' : null;
                        $serviceFeeHistory->reference_id = $loadedCreditNote?->id;
                        $serviceFeeHistory->metadata = [
                            'credit_note_number' => $loadedCreditNote?->credit_note_number,
                            'transfer_id' => $serviceFeeTransfer->id ?? null,
                            'invoice_id' => $invoice->id,
                            'type' => 'service_fee_refund',
                        ];
                        $serviceFeeHistory->user_id = $approvedBy;
                        $serviceFeeHistory->save();

                        $kashtreSuspenseAccount->forceFill(['balance' => $kashtreEndingBalance])->save();

                        $hasClientServiceFeeHistory = BalanceHistory::where('client_id', $client->id)
                            ->where('transaction_type', 'credit')
                            ->where('description', $serviceFeeDescription)
                            ->where('reference_number', $loadedCreditNote?->credit_note_number)
                            ->exists();

                        if (! $hasClientServiceFeeHistory) {
                            BalanceHistory::create([
                                'client_id' => $client->id,
                                'business_id' => $client->business_id,
                                'branch_id' => $client->branch_id,
                                'user_id' => $approvedBy,
                                'previous_balance' => $newBalance,
                                'change_amount' => $invoice->service_charge,
                                'new_balance' => $newBalance + $invoice->service_charge,
                                'transaction_type' => 'credit',
                                'description' => $serviceFeeDescription,
                                'reference_number' => $loadedCreditNote?->credit_note_number,
                                'notes' => 'Credit note workflow service fee refund',
                                'payment_method' => 'refund',
                            ]);

                            $newBalance = $clientAccount->fresh()->balance;
                        }
                    }
                }
            }

            DB::commit();

            Log::info("Refund processed", [
                'client_id' => $client->id,
                'amount' => $amount,
                'reason' => $reason,
                'approved_by' => $approvedBy,
                'transfer_id' => $transfer->id,
            ]);

            return $transfer;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Failed to process refund", [
                'client_id' => $client->id,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Helper method to transfer money between accounts
     */
    private function resolveRefundSourceAccount(Client $client, ?CreditNote $creditNote, ?Invoice $invoice, ?Item $item): ?MoneyAccount
    {
        $business = null;

        if ($invoice) {
            $invoice->loadMissing('business');
            $business = $invoice->business;
        }

        if (! $business && $creditNote) {
            $creditNote->loadMissing('business');
            $business = $creditNote->business;
        }

        if (! $business && $client->business_id) {
            $business = Business::find($client->business_id);
        }

        $suspenseAccount = null;

        if ($invoice || $item) {
            $suspenseTransferQuery = MoneyTransfer::where('transfer_type', 'suspense_movement');

            if ($invoice) {
                $suspenseTransferQuery->where('invoice_id', $invoice->id);
            }

            if ($item) {
                $suspenseTransferQuery->where('item_id', $item->id);
            }

            $suspenseTransfer = $suspenseTransferQuery->latest()->first();

            if ($suspenseTransfer && $suspenseTransfer->to_account_id) {
                $suspenseAccount = MoneyAccount::find($suspenseTransfer->to_account_id);
            }
        }

        if (! $suspenseAccount && $business) {
            if ($item && $item->type === 'package') {
                $suspenseAccount = $this->getOrCreatePackageSuspenseAccount($business, $client->id);
            } else {
                $suspenseAccount = $this->getOrCreateGeneralSuspenseAccount($business, $client->id);
            }
        }

        if (! $suspenseAccount && $business) {
            $suspenseAccount = $this->getOrCreateGeneralSuspenseAccount($business, $client->id);
        }

        return $suspenseAccount;
    }

    /**
     * Helper method to transfer money between accounts
     */
    private function transferMoney($fromAccount, $toAccount, $amount, $transferType, $invoice = null, $item = null, $description = '', $packageTrackingNumber = null, $type = 'credit')
    {
        $isCreditEntry = ($fromAccount === null);
        $isInsuranceFlow = false;
        $insuranceLabel = null;
        if ($invoice) {
            $paymentMethods = $invoice->payment_methods ?? [];
            if (is_string($paymentMethods)) {
                $paymentMethods = json_decode($paymentMethods, true) ?? [];
            }
            $isInsuranceFlow = in_array('insurance', (array) $paymentMethods, true);
            if ($isInsuranceFlow) {
                $insuranceLabel = $this->resolveInsuranceCounterpartyLabel($invoice);
            }
        }
        
        Log::info("=== MONEY TRANSFER STARTED ===", [
            'from_account_id' => $fromAccount ? $fromAccount->id : 'null (credit entry)',
            'from_account_name' => $fromAccount ? $fromAccount->name : 'Credit Entry',
            'from_account_balance_before' => $fromAccount ? $fromAccount->balance : 0,
            'to_account_id' => $toAccount->id,
            'to_account_name' => $toAccount->name,
            'to_account_balance_before' => $toAccount->balance,
            'amount' => $amount,
            'transfer_type' => $transferType,
            'description' => $description,
            'is_credit_entry' => $isCreditEntry
        ]);

        // Create transfer record with specified type
        Log::info("=== TRANSFERMONEY TYPE DEBUG ===", [
            'type_parameter' => $type,
            'from_account' => $fromAccount ? $fromAccount->name : 'Credit Entry',
            'to_account' => $toAccount->name,
            'transfer_type' => $transferType,
            'invoice_id' => $invoice ? $invoice->id : 'null',
            'client_id' => $invoice ? $invoice->client_id : 'null',
            'client_name' => $invoice && $invoice->client ? $invoice->client->name : 'null',
            'is_credit_entry' => $isCreditEntry
        ]);
        
        // Generate source description
        if ($isCreditEntry) {
            $source = 'Credit Entry';
            if ($isInsuranceFlow && $insuranceLabel) {
                $source = $insuranceLabel . " (Insurance Funding)";
                if ($invoice && $invoice->invoice_number) {
                    $source .= " (Invoice: {$invoice->invoice_number})";
                }
            } elseif ($invoice && $invoice->client) {
                $source = $invoice->client->name . " (Credit)";
                if ($invoice->invoice_number) {
                    $source .= " (Invoice: {$invoice->invoice_number})";
                }
            }
        } else {
            $source = $fromAccount->name;
            if ($isInsuranceFlow && $insuranceLabel) {
                $source = $insuranceLabel;
                if ($invoice && $invoice->invoice_number) {
                    $source .= " (Invoice: {$invoice->invoice_number})";
                }
            } elseif ($invoice && $invoice->client) {
                $source = $invoice->client->name;
                if ($invoice->invoice_number) {
                    $source .= " (Invoice: {$invoice->invoice_number})";
                }
            } elseif ($fromAccount->client) {
                $source = $fromAccount->client->name . " - {$fromAccount->name}";
            }
        }
        
        // Generate destination description
        $destination = $toAccount->name;
        if ($isInsuranceFlow && $insuranceLabel) {
            $destination = $insuranceLabel . " - {$toAccount->name}";
            if ($invoice && $invoice->invoice_number) {
                $destination .= " (Invoice: {$invoice->invoice_number})";
            }
        } elseif (in_array($toAccount->type, ['business_account', 'kashtre_account'])) {
            if ($invoice && $invoice->invoice_number) {
                $destination .= " (Invoice: {$invoice->invoice_number})";
            }
        } elseif ($toAccount->client) {
            $destination = $toAccount->client->name . " - {$toAccount->name}";
        }
        
        $transfer = MoneyTransfer::create([
            'business_id' => $isCreditEntry ? ($invoice ? $invoice->business_id : $toAccount->business_id) : $fromAccount->business_id,
            'from_account_id' => $fromAccount ? $fromAccount->id : null,
            'to_account_id' => $toAccount->id,
            'amount' => $amount,
            'currency' => $fromAccount->currency ?? $toAccount->currency ?? 'USD',
            'status' => 'completed',
            'type' => $type,
            'transfer_type' => $transferType,
            'package_tracking_number' => $packageTrackingNumber,
            'invoice_id' => $invoice ? $invoice->id : null,
            'client_id' => $invoice ? $invoice->client_id : null,
            'item_id' => $item ? $item->id : null,
            'description' => $description,
            'source' => $source,
            'destination' => $destination,
            'processed_at' => now()
        ]);

        Log::info("Money transfer record created", [
            'transfer_id' => $transfer->id,
            'is_credit_entry' => $isCreditEntry
        ]);

        // Update account balances
        if (!$isCreditEntry) {
            $fromAccount->debit($amount);  // Money goes out of source account
        }
        $toAccount->credit($amount);   // Money comes into destination account

        // Determine payment_status and payment_method based on client type and transfer type
        $paymentStatus = 'paid'; // Default to paid
        $paymentMethod = null;
        
        // Current valid enum values (before migration adds insurance/credit_arrangement)
        $currentValidMethods = ['account_balance', 'mobile_money', 'bank_transfer', 'v_card', 'p_card'];
        // Future valid enum values (after migration)
        $futureValidMethods = ['account_balance', 'mobile_money', 'bank_transfer', 'v_card', 'p_card', 'insurance', 'credit_arrangement'];
        
        if ($invoice && $invoice->client) {
            $client = $invoice->client;
            $paymentMethods = $invoice->payment_methods ?? [];
            
            // Ensure payment_methods is an array (handle JSON string case)
            if (is_string($paymentMethods)) {
                $paymentMethods = json_decode($paymentMethods, true) ?? [];
            }
            
            // Check for insurance payments
            if (in_array('insurance', $paymentMethods)) {
                $paymentStatus = 'paid';
                // Try to use 'insurance' if enum supports it, otherwise fallback to 'mobile_money'
                // Check if enum has been updated by trying to determine current enum values
                $paymentMethod = 'insurance'; // Will be validated below
            }
            // Check for credit arrangements
            elseif (in_array('credit_arrangement', $paymentMethods) || $client->is_credit_eligible) {
                // If this is a suspense_to_final transfer (services being delivered) and client is credit-eligible
                if ($transferType === 'suspense_to_final') {
                    $paymentStatus = 'pending_payment';
                    $paymentMethod = 'credit_arrangement'; // Will be validated below
                } else {
                    $paymentStatus = $invoice->payment_status ?? 'paid';
                    $paymentMethod = 'credit_arrangement'; // Will be validated below
                }
            }
            // For other payment methods, use invoice payment status
            else {
                $paymentStatus = $invoice->payment_status ?? 'paid';
                if (!empty($paymentMethods)) {
                    $paymentMethod = $paymentMethods[0] ?? null;
                }
            }
        }
        
        // Validate and fix payment_method to ensure it's a valid enum value
        // IMPORTANT: The database enum currently only supports: account_balance, mobile_money, bank_transfer, v_card, p_card
        // Until the migration is run, we must use one of these values
        if ($paymentMethod) {
            if (!in_array($paymentMethod, $currentValidMethods)) {
                // The enum doesn't support this value yet - use fallback and log
                $originalMethod = $paymentMethod;
                $paymentMethod = 'mobile_money'; // Safe fallback that's always valid
                
                Log::warning("Payment method enum not updated - using fallback", [
                    'invoice_id' => $invoice->id ?? null,
                    'invoice_number' => $invoice->invoice_number ?? null,
                    'attempted_method' => $originalMethod,
                    'fallback_method' => $paymentMethod,
                    'action_required' => 'URGENT: Run migration or visit http://127.0.0.1:8000/fix-payment-method-enum to add insurance and credit_arrangement to enum',
                    'migration_file' => '2026_02_15_183500_add_insurance_to_business_balance_histories_payment_method_enum.php',
                    'fix_url' => 'http://127.0.0.1:8000/fix-payment-method-enum'
                ]);
            }
        } else {
            // If payment_method is null, set a default
            $paymentMethod = 'mobile_money';
        }

        // Create BusinessBalanceHistory records for business accounts
        if ($toAccount->type === 'business_account' && $toAccount->business_id) {
            \App\Models\BusinessBalanceHistory::recordChange(
                $toAccount->business_id,
                $toAccount->id,
                $amount,
                'credit',
                $description,
                $invoice ? 'invoice' : 'MoneyTransfer',
                $invoice ? $invoice->id : $transfer->id,
                [
                    'from_account' => $fromAccount->name,
                    'to_account' => $toAccount->name,
                    'transfer_type' => $transferType,
                    'invoice_id' => $invoice ? $invoice->id : null,
                    'invoice_number' => $invoice ? $invoice->invoice_number : null
                ],
                auth()->id(),
                $paymentStatus,
                $paymentMethod
            );
        }

        // Create BusinessBalanceHistory records for Kashtre account
        if ($toAccount->type === 'kashtre_account') {
            \App\Models\BusinessBalanceHistory::recordChange(
                1, // Kashtre business ID
                $toAccount->id,
                $amount,
                'credit',
                $description,
                $invoice ? 'invoice' : 'MoneyTransfer',
                $invoice ? $invoice->id : $transfer->id,
                [
                    'from_account' => $fromAccount->name,
                    'to_account' => $toAccount->name,
                    'transfer_type' => $transferType,
                    'invoice_id' => $invoice ? $invoice->id : null,
                    'invoice_number' => $invoice ? $invoice->invoice_number : null
                ],
                auth()->id(),
                $paymentStatus,
                $paymentMethod
            );
        }

        Log::info("=== MONEY TRANSFER COMPLETED ===", [
            'transfer_id' => $transfer->id,
            'from_account_balance_after' => $fromAccount->fresh()->balance,
            'to_account_balance_after' => $toAccount->fresh()->balance,
            'amount_transferred' => $amount
        ]);

        return $transfer;
    }

    /**
     * Resolve a readable insurance counterparty label for transfer source/destination.
     */
    private function resolveInsuranceCounterpartyLabel(Invoice $invoice): string
    {
        $snapshot = is_array($invoice->insurance_authorization_snapshot ?? null)
            ? $invoice->insurance_authorization_snapshot
            : json_decode($invoice->insurance_authorization_snapshot ?? '[]', true);

        $vendors = is_array($snapshot['vendors'] ?? null) ? $snapshot['vendors'] : [];
        if (!empty($vendors)) {
            $names = collect($vendors)
                ->filter(function ($vendor) {
                    $status = strtolower((string) ($vendor['authorization_status'] ?? ''));
                    if (in_array($status, ['skipped', 'failed'], true)) {
                        return false;
                    }

                    $submitted = (float) ($vendor['amount_submitted'] ?? 0);
                    $insuranceTotal = (float) ($vendor['insurance_total'] ?? $vendor['insurance_portion'] ?? 0);
                    $clientAllocated = (float) ($vendor['client_portion_allocated'] ?? $vendor['client_total'] ?? 0);

                    // Only include insurers that actually participated financially.
                    return ($submitted > 0) || ($insuranceTotal > 0) || ($clientAllocated > 0);
                })
                ->map(function ($vendor) {
                    return trim((string) ($vendor['vendor_name'] ?? $vendor['insurance_company_name'] ?? ''));
                })
                ->filter()
                ->unique()
                ->values();

            if ($names->count() === 1) {
                return $names->first();
            }
            if ($names->count() > 1) {
                return 'Insurance Companies: ' . $names->implode(', ');
            }

            // Fallback: even when financial fields are missing, use any provided vendor names.
            $fallbackNames = collect($vendors)
                ->map(function ($vendor) {
                    return trim((string) (
                        $vendor['vendor_name']
                        ?? $vendor['insurance_company_name']
                        ?? $vendor['name']
                        ?? ''
                    ));
                })
                ->filter()
                ->unique()
                ->values();

            if ($fallbackNames->count() === 1) {
                return $fallbackNames->first();
            }
            if ($fallbackNames->count() > 1) {
                return 'Insurance Companies: ' . $fallbackNames->implode(', ');
            }
        }

        // Additional snapshot fallbacks (legacy payload shapes).
        foreach ([
            'insurance_company_name',
            'vendor_name',
            'third_party_payer_name',
            'payer_name',
            'insurance_name',
        ] as $key) {
            $value = trim((string) ($snapshot[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $client = $invoice->client;
        if (!$client && $invoice->client_id) {
            $client = \App\Models\Client::with('insuranceCompany')->find($invoice->client_id);
        }

        if ($client && $client->insuranceCompany) {
            return (string) $client->insuranceCompany->name;
        }

        return 'Insurance Company';
    }

    /**
     * Get account balance
     */
    public function getAccountBalance($businessId, $accountType, $clientId = null, $contractorId = null)
    {
        $query = MoneyAccount::where('business_id', $businessId)
            ->where('type', $accountType);

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        if ($contractorId) {
            $query->where('contractor_profile_id', $contractorId);
        }

        $account = $query->first();
        return $account ? $account->balance : 0;
    }

    /**
     * Get client account balance
     */
    public function getClientBalance(Client $client)
    {
        $account = $this->getOrCreateClientAccount($client);
        return $account->balance;
    }

    /**
     * Get business account balance
     */
    public function getBusinessBalance(Business $business)
    {
        return $this->getAccountBalance($business->id, 'business_account');
    }

    /**
     * Get contractor account balance
     */
    public function getContractorBalance(ContractorProfile $contractor)
    {
        $account = $this->getOrCreateContractorAccount($contractor);
        return $account->balance;
    }

    /**
     * Calculate business available balance from BusinessBalanceHistory (source of truth)
     * This method ensures consistent calculation across all controllers
     * 
     * @param Business $business
     * @return array Returns ['totalBalance', 'withdrawalSuspenseBalance', 'availableBalance', 'totalCredits', 'totalDebits']
     */
    public function calculateBusinessAvailableBalance(Business $business)
    {
        // Get business account
        $businessAccount = MoneyAccount::where('business_id', $business->id)
            ->where('type', 'business_account')
            ->first();

        $totalCredits = 0;
        $totalDebits = 0;
        $totalBalance = 0;

        if ($businessAccount) {
            // Calculate total credits - exclude pending payments (they haven't been paid yet)
            $totalCredits = BusinessBalanceHistory::where('business_id', $business->id)
                ->where('money_account_id', $businessAccount->id)
                ->where('type', 'credit')
                ->where(function($query) {
                    $query->where('payment_status', 'paid')
                        ->orWhereNull('payment_status'); // Include records without payment_status (legacy data)
                })
                ->sum('amount');

            // Calculate total debits - include all debits
            $totalDebits = BusinessBalanceHistory::where('business_id', $business->id)
                ->where('money_account_id', $businessAccount->id)
                ->where('type', 'debit')
                ->sum('amount');

            $totalBalance = $totalCredits - $totalDebits;
        }

        // Get withdrawal suspense account balance calculated from BusinessBalanceHistory
        $withdrawalSuspenseAccount = $this->getOrCreateWithdrawalSuspenseAccount($business);
        
        $withdrawalSuspenseBalance = 0;
        if ($withdrawalSuspenseAccount) {
            $suspenseCredits = BusinessBalanceHistory::where('money_account_id', $withdrawalSuspenseAccount->id)
                ->where('type', 'credit')
                ->sum('amount');
            
            $suspenseDebits = BusinessBalanceHistory::where('money_account_id', $withdrawalSuspenseAccount->id)
                ->where('type', 'debit')
                ->sum('amount');
            
            // Available Balance = Total - (Credits - Debits in suspense)
            $withdrawalSuspenseBalance = $suspenseCredits - $suspenseDebits;
        }

        // Available Balance = Total Balance - Withdrawal Suspense Balance
        $availableBalance = $totalBalance - $withdrawalSuspenseBalance;

        return [
            'totalBalance' => $totalBalance,
            'withdrawalSuspenseBalance' => $withdrawalSuspenseBalance,
            'availableBalance' => $availableBalance,
            'totalCredits' => $totalCredits,
            'totalDebits' => $totalDebits,
        ];
    }

    /**
     * Process money transfers when service delivery item moves to in-progress status
     */
    public function processServiceDeliveryMoneyTransfer($serviceDeliveryQueue, $user = null)
    {
        DB::beginTransaction();
        
        try {
            $invoice = $serviceDeliveryQueue->invoice;
            $item = $serviceDeliveryQueue->item;
            $business = Business::find($invoice->business_id);
            
            Log::info("=== PACKAGE ADJUSTMENT MONEY TRANSFER START ===", [
                'service_delivery_queue_id' => $serviceDeliveryQueue->id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'item_id' => $item->id,
                'item_name' => $item->name,
                'item_type' => $item->type,
                'business_id' => $business->id,
                'business_name' => $business->name,
                'user_id' => $user ? $user->id : null,
                'user_name' => $user ? $user->name : null,
                'timestamp' => now()->toISOString()
            ]);
            
            // Get the item amount from the service delivery queue record
            $itemAmount = $serviceDeliveryQueue->price * $serviceDeliveryQueue->quantity;
            
            Log::info("Service delivery queue item details", [
                'service_delivery_queue_id' => $serviceDeliveryQueue->id,
                'queue_price' => $serviceDeliveryQueue->price,
                'queue_quantity' => $serviceDeliveryQueue->quantity,
                'calculated_item_amount' => $itemAmount,
                'invoice_package_adjustment' => $invoice->package_adjustment ?? 0,
                'invoice_subtotal' => $invoice->subtotal ?? 0,
                'invoice_total_amount' => $invoice->total_amount ?? 0
            ]);
            
            // Get service charge for this invoice
            $serviceCharge = $invoice->service_charge ?? 0;
            
            // Get business account
            $businessAccount = $this->getOrCreateBusinessAccount($business);
            
            // Get Kashtre account (business_id = 1)
            $kashtreAccount = $this->getOrCreateKashtreAccount();
            
            // Check if this is a package item and log package adjustment details
            $isPackageItem = $invoice->package_adjustment > 0;
            
            Log::info("Package adjustment analysis", [
                'is_package_item' => $isPackageItem,
                'invoice_package_adjustment' => $invoice->package_adjustment ?? 0,
                'item_amount_from_queue' => $itemAmount,
                'item_amount_for_display' => $isPackageItem ? $this->calculateItemAmount($invoice, $item) : $itemAmount,
                'money_transfer_amount' => $itemAmount,
                'note' => $isPackageItem ? 'Package item: Display shows item amount, money transfer uses full package amount' : 'Regular item: Same amount for display and transfer'
            ]);
            
            // Calculate business and contractor shares FIRST
            $businessShare = $itemAmount;
            $contractorShare = 0;
            
            // Check if item has contractor and calculate shares
            if ($item->contractor_account_id && $item->hospital_share < 100) {
                $contractor = ContractorProfile::find($item->contractor_account_id);
                if ($contractor) {
                    $contractorShare = ($itemAmount * (100 - $item->hospital_share)) / 100;
                    $businessShare = $itemAmount - $contractorShare;
                    
                    Log::info("Calculating shares for service delivery", [
                        'total_amount' => $itemAmount,
                        'hospital_share_percentage' => $item->hospital_share,
                        'contractor_share_percentage' => 100 - $item->hospital_share,
                        'contractor_share_amount' => $contractorShare,
                        'business_share_amount' => $businessShare
                    ]);
                }
            }
            
            // Transfer ONLY business share to business account
            $generalSuspenseAccount = $this->getOrCreateGeneralSuspenseAccount($business);
            $this->transferMoney(
                $generalSuspenseAccount,
                $businessAccount,
                $businessShare,
                'service_delivered',
                $invoice,
                $item,
                "Service delivery payment for {$item->name} ({$serviceDeliveryQueue->quantity})"
            );
            
            // Determine payment status and method from invoice
            $paymentStatus = 'paid'; // Default to paid for service delivery
            $paymentMethod = null;
            
            if ($invoice) {
                // Check invoice payment methods
                $paymentMethods = $invoice->payment_methods ?? [];
                if (in_array('insurance', $paymentMethods)) {
                    $paymentMethod = 'insurance';
                    $paymentStatus = 'paid'; // Insurance payments are considered paid
                } elseif (in_array('credit_arrangement', $paymentMethods) || $invoice->client->is_credit_eligible ?? false) {
                    $paymentStatus = 'pending_payment';
                    $paymentMethod = 'credit_arrangement';
                } else {
                    $paymentStatus = $invoice->payment_status ?? 'paid';
                    // Try to get payment method from invoice
                    if (!empty($paymentMethods)) {
                        $paymentMethod = $paymentMethods[0] ?? null;
                    }
                }
            }
            
            // Record business balance statement
            BusinessBalanceHistory::recordChange(
                $business->id,
                $businessAccount->id,
                $businessShare,
                'credit',
                "Service delivery payment for {$item->name} ({$serviceDeliveryQueue->quantity}) - Business share",
                'service_delivery_queue',
                $serviceDeliveryQueue->id,
                [
                    'invoice_id' => $invoice->id,
                    'item_id' => $item->id,
                    'client_id' => $invoice->client_id
                ],
                $user ? $user->id : null,
                $paymentStatus,
                $paymentMethod
            );
            
            // Transfer service charge to Kashtre account
            if ($serviceCharge > 0) {
                $this->transferMoney(
                    $this->getOrCreateKashtreSuspenseAccount($business, $invoice->client_id),
                    $kashtreAccount,
                    $serviceCharge,
                    'service_charge',
                    $invoice,
                    $item,
                    "Service charge for invoice #{$invoice->invoice_number}"
                );
                
                // Record Kashtre balance statement
                BusinessBalanceHistory::recordChange(
                    1, // Kashtre business_id
                    $kashtreAccount->id,
                    $serviceCharge,
                    'credit',
                    "Service charge for invoice #{$invoice->invoice_number}",
                    'service_delivery_queue',
                    $serviceDeliveryQueue->id,
                    [
                        'invoice_id' => $invoice->id,
                        'item_id' => $item->id,
                        'client_id' => $invoice->client_id,
                        'business_id' => $business->id
                    ],
                    $user ? $user->id : null,
                    $paymentStatus,
                    $paymentMethod
                );
            }
            
            // Transfer ONLY contractor share to contractor account (if exists)
            if ($contractorShare > 0 && $item->contractor_account_id) {
                $contractor = ContractorProfile::find($item->contractor_account_id);
                if ($contractor) {
                    $contractorAccount = $this->getOrCreateContractorAccount($contractor);
                    
                    // Transfer contractor share directly from suspense to contractor account
                    $this->transferMoney(
                        $generalSuspenseAccount,
                        $contractorAccount,
                        $contractorShare,
                        'contractor_share',
                        $invoice,
                        $item,
                        "{$item->name}"
                    );
                    
                    // Record contractor balance statement
                    ContractorBalanceHistory::recordChange(
                        $contractor->id,
                        $contractorAccount->id,
                        $contractorShare,
                        'credit',
                        "{$item->name}",
                        'service_delivery_queue',
                        $serviceDeliveryQueue->id,
                        [
                            'invoice_id' => $invoice->id,
                            'item_id' => $item->id,
                            'client_id' => $invoice->client_id,
                            'business_id' => $business->id
                        ],
                        $user ? $user->id : null
                    );
                    
                    // Update contractor account balance
                    $contractor->increment('account_balance', $contractorShare);
                    
                    // Sync with money account if it exists
                    if ($contractor->moneyAccount) {
                        $contractor->moneyAccount->credit($contractorShare);
                    }
                    
                    Log::info("Contractor share processed", [
                        'contractor_id' => $contractor->id,
                        'contractor_name' => $contractor->name,
                        'contractor_share_amount' => $contractorShare,
                        'contractor_new_balance' => $contractor->fresh()->account_balance
                    ]);
                }
            }
            
            // Update business account balance
            $business->increment('account_balance', $businessShare);
            
            // Sync with money account if it exists
            if ($business->businessMoneyAccount) {
                $business->businessMoneyAccount->credit($businessShare);
            }
            
            DB::commit();
            
            Log::info("=== PACKAGE ADJUSTMENT MONEY TRANSFER COMPLETED ===", [
                'service_delivery_queue_id' => $serviceDeliveryQueue->id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'item_id' => $item->id,
                'item_name' => $item->name,
                'item_type' => $item->type,
                'item_amount_transferred' => $itemAmount,
                'item_amount_for_display' => $isPackageItem ? $this->calculateItemAmount($invoice, $item) : $itemAmount,
                'service_charge' => $serviceCharge,
                'business_id' => $business->id,
                'business_name' => $business->name,
                'is_package_item' => $isPackageItem,
                'package_adjustment_total' => $invoice->package_adjustment ?? 0,
                'timestamp' => now()->toISOString()
            ]);
            
            // Final summary log for easy tracking
            Log::info("=== PACKAGE ADJUSTMENT SUMMARY ===", [
                'action' => 'ITEM_COMPLETED',
                'invoice_number' => $invoice->invoice_number,
                'item_name' => $item->name,
                'is_package_item' => $isPackageItem,
                'money_transferred_to_business' => $itemAmount,
                'display_amount_for_adjustments' => $isPackageItem ? $this->calculateItemAmount($invoice, $item) : $itemAmount,
                'package_adjustment_total_in_invoice' => $invoice->package_adjustment ?? 0,
                'note' => $isPackageItem ? 'Package item: Full package amount transferred, item amount shown in adjustments' : 'Regular item: Same amount for transfer and display',
                'timestamp' => now()->toISOString()
            ]);
            
            return true;
            
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Failed to process service delivery money transfer", [
                'service_delivery_queue_id' => $serviceDeliveryQueue->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Calculate item amount considering package adjustments
     * For package items: returns the item amount (what customer paid for this specific item)
     * For regular items: returns the original item amount
     */
    private function calculateItemAmount($invoice, $item)
    {
        Log::info("=== CALCULATING ITEM AMOUNT FOR DISPLAY ===", [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'item_id' => $item->id,
            'item_name' => $item->name,
            'item_type' => $item->type,
            'invoice_package_adjustment' => $invoice->package_adjustment ?? 0,
            'invoice_subtotal' => $invoice->subtotal ?? 0,
            'timestamp' => now()->toISOString()
        ]);
        
        // Get the original item price from invoice items array
        $invoiceItem = null;
        if ($invoice->items && is_array($invoice->items)) {
            foreach ($invoice->items as $invItem) {
                if (isset($invItem['id']) && $invItem['id'] == $item->id) {
                    $invoiceItem = $invItem;
                    break;
                }
            }
        }
        
        if (!$invoiceItem) {
            Log::warning("Invoice item not found in invoice items array", [
                'invoice_id' => $invoice->id,
                'item_id' => $item->id,
                'invoice_items_count' => is_array($invoice->items) ? count($invoice->items) : 0
            ]);
            return 0;
        }
        
        $originalAmount = ($invoiceItem['quantity'] ?? 0) * ($invoiceItem['price'] ?? 0);
        
        Log::info("Invoice item details found", [
            'invoice_id' => $invoice->id,
            'item_id' => $item->id,
            'invoice_item_quantity' => $invoiceItem['quantity'] ?? 0,
            'invoice_item_price' => $invoiceItem['price'] ?? 0,
            'original_amount' => $originalAmount
        ]);
        
        // For package items, the adjustment should show the item amount (what customer paid)
        // The actual money transfer still uses the full package amount
        if ($invoice->package_adjustment > 0) {
            // For display purposes, return the item amount (what customer paid for this specific item)
            $itemAmount = $this->calculateItemPackageAdjustment($invoice, $item);
            
            Log::info('=== PACKAGE ITEM AMOUNT CALCULATION ===', [
                'item_id' => $item->id,
                'item_name' => $item->name,
                'original_amount' => $originalAmount,
                'item_amount_for_display' => $itemAmount,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'package_adjustment_total' => $invoice->package_adjustment,
                'note' => 'PACKAGE ITEM: Display shows item amount, money transfer uses full package amount'
            ]);
            
            return $itemAmount;
        }
        
        Log::info('=== REGULAR ITEM AMOUNT CALCULATION ===', [
            'item_id' => $item->id,
            'item_name' => $item->name,
            'original_amount' => $originalAmount,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'note' => 'REGULAR ITEM: Same amount for display and transfer'
        ]);
        
        return max(0, $originalAmount);
    }
    
    /**
     * Calculate package adjustment for a specific item
     * For display purposes, this should return the item amount (what customer paid for this specific item)
     * The actual money transfer still uses the full package amount
     */
    private function calculateItemPackageAdjustment($invoice, $item)
    {
        Log::info("=== CALCULATING PACKAGE ADJUSTMENT FOR ITEM ===", [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'item_id' => $item->id,
            'item_name' => $item->name,
            'item_type' => $item->type,
            'invoice_package_adjustment_total' => $invoice->package_adjustment ?? 0,
            'timestamp' => now()->toISOString()
        ]);
        
        // Get the item from invoice items array
        $invoiceItem = null;
        if ($invoice->items && is_array($invoice->items)) {
            foreach ($invoice->items as $invItem) {
                if (isset($invItem['id']) && $invItem['id'] == $item->id) {
                    $invoiceItem = $invItem;
                    break;
                }
            }
        }
        
        if (!$invoiceItem) {
            Log::warning("Invoice item not found for package adjustment calculation", [
                'invoice_id' => $invoice->id,
                'item_id' => $item->id,
                'invoice_items_count' => is_array($invoice->items) ? count($invoice->items) : 0
            ]);
            return 0;
        }
        
        // Return the actual item amount (what the customer paid for this specific item)
        // This is what should be displayed in package adjustments
        $itemAmount = ($invoiceItem['quantity'] ?? 0) * ($invoiceItem['price'] ?? 0);
        
        Log::info('=== PACKAGE ADJUSTMENT CALCULATION RESULT ===', [
            'item_id' => $item->id,
            'item_name' => $item->name,
            'invoice_item_quantity' => $invoiceItem['quantity'] ?? 0,
            'invoice_item_price' => $invoiceItem['price'] ?? 0,
            'calculated_item_amount' => $itemAmount,
            'invoice_package_adjustment_total' => $invoice->package_adjustment ?? 0,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'note' => 'DISPLAY AMOUNT: Shows item amount (what customer paid), money transfer uses full package amount'
        ]);
        
        return $itemAmount;
    }
    
    /**
     * Get or create business account
     */
    public function getOrCreateBusinessAccount(Business $business)
    {
        return MoneyAccount::firstOrCreate([
            'business_id' => $business->id,
            'type' => 'business_account'
        ], [
            'name' => "Business Account - {$business->name}",
            'description' => "Business account for {$business->name}",
            'balance' => 0,
            'currency' => $business->currency_code ?? 'USD',
            'is_active' => true
        ]);
    }
    
    /**
     * Get or create Kashtre account
     */
    public function getOrCreateKashtreAccount()
    {
        $kashtreCurrencyCode = Business::find(1)?->currency_code ?? 'USD';
        return MoneyAccount::firstOrCreate([
            'business_id' => 1, // Kashtre business_id
            'type' => 'kashtre_account'
        ], [
            'name' => "Kashtre Account",
            'description' => "Kashtre platform account",
            'balance' => 0,
            'currency' => $kashtreCurrencyCode,
            'is_active' => true
        ]);
    }
    
    /**
     * Get or create mobile money account
     */
    private function getOrCreateMobileMoneyAccount(Business $business)
    {
        return MoneyAccount::firstOrCreate([
            'business_id' => $business->id,
            'client_id' => null, // Mobile Money is business-level
            'type' => 'mobile_money_account'
        ], [
            'name' => "Mobile Money Account - {$business->name}",
            'description' => "Mobile money payment account for {$business->name}",
            'balance' => 0,
            'currency' => $business->currency_code ?? 'USD',
            'is_active' => true
        ]);
    }
    
    /**
     * Get or create general suspense account
     */
    private function getOrCreateGeneralSuspenseAccount(Business $business, $clientId = null)
    {
        return MoneyAccount::firstOrCreate([
            'business_id' => $business->id,
            'client_id' => $clientId, // General suspense accounts are client-specific
            'type' => 'general_suspense_account'
        ], [
            'name' => "General Suspense Account - {$business->name}" . ($clientId ? " - Client {$clientId}" : ""),
            'description' => "General suspense account for {$business->name}" . ($clientId ? " - Client {$clientId}" : ""),
            'balance' => 0,
            'currency' => $business->currency_code ?? 'USD',
            'is_active' => true
        ]);
    }
    
    /**
     * Get or create Kashtre suspense account
     */
    private function getOrCreateKashtreSuspenseAccount(Business $business, $clientId = null)
    {
        return MoneyAccount::firstOrCreate([
            'business_id' => $business->id,
            'client_id' => $clientId,
            'type' => 'kashtre_suspense_account'
        ], [
            'name' => "Kashtre Suspense Account - {$business->name}" . ($clientId ? " - Client {$clientId}" : ""),
            'description' => "Kashtre suspense account for {$business->name}" . ($clientId ? " - Client {$clientId}" : ""),
            'balance' => 0,
            'currency' => $business->currency_code ?? 'USD',
            'is_active' => true
        ]);
    }

    /**
     * Get or create Package suspense account
     */
    private function getOrCreatePackageSuspenseAccount(Business $business, $clientId = null)
    {
        return MoneyAccount::firstOrCreate([
            'business_id' => $business->id,
            'client_id' => $clientId,
            'type' => 'package_suspense_account'
        ], [
            'name' => "Package Suspense Account - {$business->name}" . ($clientId ? " - Client {$clientId}" : ""),
            'description' => "Package suspense account for {$business->name}" . ($clientId ? " - Client {$clientId}" : ""),
            'balance' => 0,
            'currency' => $business->currency_code ?? 'USD',
            'is_active' => true
        ]);
    }

    /**
     * Get or create withdrawal suspense account
     */
    public function getOrCreateWithdrawalSuspenseAccount(Business $business)
    {
        return MoneyAccount::firstOrCreate([
            'business_id' => $business->id,
            'type' => 'withdrawal_suspense_account'
        ], [
            'name' => "Withdrawal Suspense Account - {$business->name}",
            'description' => "Holds funds for pending withdrawal requests for {$business->name}",
            'balance' => 0,
            'currency' => $business->currency_code ?? 'USD',
            'is_active' => true
        ]);
    }

    /**
     * Process "Save and Exit" - Move money from suspense accounts to final accounts
     * This is called when the user clicks "save and exit" to finalize the order
     */
    public function processSaveAndExit(Invoice $invoice, $items, $itemStatus = null)
    {
        try {
            Log::info("=== SAVE AND EXIT PROCESSING STARTED ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $invoice->client_id,
                'client_name' => $invoice->client->name ?? 'Unknown',
                'business_id' => $invoice->business_id,
                'business_name' => $invoice->business->name ?? 'Unknown',
                'branch_id' => $invoice->branch_id,
                'items_count' => count($items),
                'items' => $items,
                'service_charge' => $invoice->service_charge ?? 0,
                'package_adjustment' => $invoice->package_adjustment ?? 0,
                'item_status' => $itemStatus,
                'total_amount' => $invoice->total_amount ?? 0,
                'subtotal_1' => $invoice->subtotal_1 ?? 0,
                'subtotal_2' => $invoice->subtotal_2 ?? 0,
                'invoice_status' => $invoice->status,
                'invoice_created_at' => $invoice->created_at,
                'invoice_updated_at' => $invoice->updated_at,
                'timestamp' => now()->toDateTimeString()
            ]);

            Log::info("Items being processed in Save & Exit", [
                'invoice_id' => $invoice->id,
                'items' => $items
            ]);

            DB::beginTransaction();

            $client = $invoice->client;
            $business = $invoice->business;
            $transferRecords = [];

            Log::info("Starting Save & Exit processing", [
                'client_id' => $client->id,
                'business_id' => $business->id,
                'item_status' => $itemStatus
            ]);

            // Note: Credit clients now also process suspense account movements
            // Money will move to suspense accounts even though no payment was received
            // This allows proper tracking of items through the system

            // Process money movement from suspense accounts to final accounts
            Log::info("🚀 === SAVE & EXIT: CALLING SUSPENSE TO FINAL MONEY MOVEMENT ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'items_count' => count($items),
                'item_status' => $itemStatus
            ]);
            
            $this->processSuspenseToFinalMoneyMovement($invoice, $items, $itemStatus, $transferRecords);
            
            Log::info("✅ === SAVE & EXIT: SUSPENSE TO FINAL MONEY MOVEMENT RETURNED ===", [
                'invoice_id' => $invoice->id,
                'transfer_records_count' => count($transferRecords)
            ]);

            // Handle package adjustment money movement from Package Suspense to final accounts
            // BUT ONLY if package items were actually updated
            $hasPackageItemsUpdated = false;
            if ($invoice->package_adjustment > 0 && in_array($itemStatus, ['completed', 'partially_done'])) {
                // Check if any of the updated items are package items
                foreach ($items as $itemData) {
                    $itemId = $itemData['item_id'] ?? null;
                    if (!$itemId) continue;
                    
                    // Check if this item is part of a package adjustment
                    $validPackages = \App\Models\PackageTracking::where('client_id', $invoice->client_id)
                        ->where('business_id', $invoice->business_id)
                        ->where('status', 'active')
                        ->where('remaining_quantity', '>', 0)
                        ->get();
                    
                    foreach ($validPackages as $packageTracking) {
                        $packageItems = $packageTracking->packageItem->packageItems;
                        foreach ($packageItems as $packageItem) {
                            if ($packageItem->included_item_id == $itemId) {
                                $hasPackageItemsUpdated = true;
                                break 3;
                            }
                        }
                    }
                }
            }
            
            if ($invoice->package_adjustment > 0 && in_array($itemStatus, ['completed', 'partially_done']) && $hasPackageItemsUpdated) {
                Log::info("=== PROCESSING PACKAGE ADJUSTMENT FROM SUSPENSE ===", [
                    'invoice_id' => $invoice->id,
                    'package_adjustment' => $invoice->package_adjustment,
                    'item_status' => $itemStatus
                ]);

                // Get Package Suspense account
                $packageSuspenseAccount = $this->getOrCreatePackageSuspenseAccount($business, $client->id);
                
                // Check if money is available in Package Suspense
                if ($packageSuspenseAccount->balance >= $invoice->package_adjustment) {
                    // Move package adjustment from Package Suspense to Business Account
                    $businessAccount = $this->getOrCreateBusinessAccount($business);
                
                $transfer = $this->transferMoney(
                        $packageSuspenseAccount,
                        $businessAccount,
                        $invoice->package_adjustment,
                        'suspense_to_final',
                        $invoice,
                        null,
                        "Package Adjustment - Final Transfer"
                    );

                    $transfer->markMoneyMovedToFinalAccount();

                $transferRecords[] = [
                        'item_name' => 'Package Adjustment',
                        'amount' => $invoice->package_adjustment,
                        'source_suspense' => $packageSuspenseAccount->name,
                        'destination' => $businessAccount->name,
                    'transfer_id' => $transfer->id
                ];

                    Log::info("Package adjustment money moved from suspense to final account", [
                        'amount' => $invoice->package_adjustment,
                        'transfer_id' => $transfer->id
                    ]);
                } else {
                    Log::warning("Insufficient funds in Package Suspense for package adjustment", [
                        'required' => $invoice->package_adjustment,
                        'available' => $packageSuspenseAccount->balance
                    ]);
                }
            } else {
                Log::info("⏭️ SKIPPING PACKAGE ADJUSTMENT PROCESSING", [
                    'invoice_id' => $invoice->id,
                    'package_adjustment' => $invoice->package_adjustment,
                    'item_status' => $itemStatus,
                    'has_package_items_updated' => $hasPackageItemsUpdated,
                    'reason' => 'No package items were updated or package adjustment is zero'
                ]);
            }

            // Service charge is now handled in the processSuspenseToFinalMoneyMovement method
            // No additional service charge processing needed here

            DB::commit();

            Log::info("=== SAVE AND EXIT PROCESSING COMPLETED SUCCESSFULLY ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $client->id,
                'client_name' => $client->name ?? 'Unknown',
                'business_id' => $invoice->business_id,
                'business_name' => $invoice->business->name ?? 'Unknown',
                'branch_id' => $invoice->branch_id,
                'item_status' => $itemStatus,
                'package_adjustment' => $invoice->package_adjustment ?? 0,
                'transfer_records_count' => count($transferRecords),
                'client_suspense_balance_final' => 0, // No longer using client suspense account
                'total_amount_processed' => array_sum(array_column($transferRecords, 'amount')),
                'transfer_records' => $transferRecords,
                'items_processed' => count($items),
                'timestamp' => now()->toDateTimeString()
            ]);

            return $transferRecords;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("=== SAVE AND EXIT PROCESSING FAILED ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $invoice->client_id,
                'client_name' => $invoice->client->name ?? 'Unknown',
                'business_id' => $invoice->business_id,
                'business_name' => $invoice->business->name ?? 'Unknown',
                'branch_id' => $invoice->branch_id,
                'item_status' => $itemStatus,
                'package_adjustment' => $invoice->package_adjustment ?? 0,
                'items_count' => count($items),
                'items' => $items,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toDateTimeString()
            ]);
            throw $e;
        }
    }

    /**
     * Process a bulk item - move entire bulk amount when any included item is processed
     */
    private function processBulkItem($bulkItem, $totalAmount, $clientSuspenseAccount, $business, $invoice, &$transferRecords, $quantity)
    {
        Log::info("=== PROCESSING BULK ITEM ===", [
            'bulk_item_id' => $bulkItem->id,
            'bulk_item_name' => $bulkItem->name,
            'bulk_total_amount' => $totalAmount
        ]);

        // Get all included items in this bulk
        $includedItems = \App\Models\BulkItem::where('bulk_item_id', $bulkItem->id)
            ->with('includedItem')
            ->get();

        Log::info("Bulk item contains included items", [
            'bulk_item_id' => $bulkItem->id,
            'included_items_count' => $includedItems->count(),
            'included_items' => $includedItems->pluck('includedItem.name')->toArray()
        ]);

        // Determine destination account based on bulk item type
        if ($bulkItem->contractor_account_id) {
            // Contractor bulk item - move to contractor account
            $contractor = ContractorProfile::find($bulkItem->contractor_account_id);
            $destinationAccount = $this->getOrCreateContractorAccount($contractor);
            $transferDescription = "Contractor payment for bulk: {$bulkItem->name}";
            
            Log::info("Bulk item assigned to contractor", [
                'bulk_item_id' => $bulkItem->id,
                'contractor_id' => $contractor->id,
                'contractor_name' => $contractor->name,
                'destination_account_id' => $destinationAccount->id,
                'destination_account_balance_before' => $destinationAccount->balance
            ]);
        } else {
            // Business bulk item - move to business account
            $destinationAccount = $this->getOrCreateBusinessAccount($business);
            $transferDescription = "Business payment for bulk: {$bulkItem->name}";
            
            Log::info("Bulk item assigned to business", [
                'bulk_item_id' => $bulkItem->id,
                'business_id' => $business->id,
                'business_name' => $business->name,
                'destination_account_id' => $destinationAccount->id,
                'destination_account_balance_before' => $destinationAccount->balance
            ]);
        }

        // Transfer entire bulk amount from client suspense to destination account
        Log::info("Initiating bulk money transfer", [
            'from_account_id' => $clientSuspenseAccount->id,
            'to_account_id' => $destinationAccount->id,
            'amount' => $totalAmount,
            'description' => $transferDescription
        ]);

        $transfer = $this->transferMoney(
            $clientSuspenseAccount,
            $destinationAccount,
            $totalAmount,
            'save_and_exit',
            $invoice,
            $bulkItem,
            $transferDescription
        );

        Log::info("Bulk money transfer completed", [
            'transfer_id' => $transfer->id,
            'from_account_balance_after' => $clientSuspenseAccount->fresh()->balance,
            'to_account_balance_after' => $destinationAccount->fresh()->balance
        ]);

        // Create balance statement for the destination account
        if ($bulkItem->contractor_account_id) {
            Log::info("Creating contractor balance statement for bulk", [
                'contractor_id' => $contractor->id,
                'amount' => $totalAmount,
                'type' => 'credit'
            ]);
            
            ContractorBalanceHistory::recordChange(
                $contractor->id,
                $destinationAccount->id,
                $totalAmount,
                'credit',
                "{$bulkItem->name} ({$quantity})",
                'invoice',
                $invoice->id,
                [
                    'invoice_number' => $invoice->invoice_number,
                    'bulk_item_name' => $bulkItem->name,
                    'description' => "Service delivery completed - Bulk: {$bulkItem->name}"
                ]
            );
            
            // Update contractor account balance
            $contractor->increment('account_balance', $totalAmount);
            
            Log::info("Contractor account balance updated for bulk item", [
                'contractor_id' => $contractor->id,
                'contractor_name' => $contractor->name,
                'bulk_item_name' => $bulkItem->name,
                'amount_added' => $totalAmount,
                'new_balance' => $contractor->fresh()->account_balance
            ]);
        } else {
            Log::info("Creating business balance statement for bulk", [
                'business_id' => $business->id,
                'amount' => $totalAmount,
                'type' => 'credit'
            ]);
            
            // Determine payment status and method from invoice
            $paymentStatus = 'paid'; // Default to paid for service delivery
            $paymentMethod = null;
            
            if ($invoice) {
                $paymentMethods = $invoice->payment_methods ?? [];
                if (in_array('insurance', $paymentMethods)) {
                    $paymentMethod = 'insurance';
                    $paymentStatus = 'paid';
                } elseif (in_array('credit_arrangement', $paymentMethods) || $invoice->client->is_credit_eligible ?? false) {
                    $paymentStatus = 'pending_payment';
                    $paymentMethod = 'credit_arrangement';
                } else {
                    $paymentStatus = $invoice->payment_status ?? 'paid';
                    if (!empty($paymentMethods)) {
                        $paymentMethod = $paymentMethods[0] ?? null;
                    }
                }
            }
            
            BusinessBalanceHistory::recordChange(
                $business->id,
                $destinationAccount->id,
                $totalAmount,
                'credit',
                "{$bulkItem->name} ({$quantity})",
                'invoice',
                $invoice->id,
                [
                    'invoice_number' => $invoice->invoice_number,
                    'bulk_item_name' => $bulkItem->name,
                    'description' => "Service delivery completed - Bulk: {$bulkItem->name}"
                ],
                auth()->id(),
                $paymentStatus,
                $paymentMethod
            );
        }

        $transferRecords[] = [
            'item_name' => "Bulk: {$bulkItem->name}",
            'amount' => $totalAmount,
            'destination' => $destinationAccount->name,
            'transfer_id' => $transfer->id
        ];

        Log::info("=== BULK ITEM PROCESSING COMPLETED ===", [
            'bulk_item_id' => $bulkItem->id,
            'bulk_total_amount' => $totalAmount,
            'included_items_count' => $includedItems->count()
        ]);
    }

    /**
     * Process a regular item (good, service) - standard money movement
     */
    private function processRegularItem($item, $totalAmount, $clientSuspenseAccount, $business, $invoice, &$transferRecords, $quantity)
    {
        Log::info("=== PROCESSING REGULAR ITEM ===", [
            'item_id' => $item->id,
            'item_name' => $item->name,
            'item_type' => $item->type,
            'total_amount' => $totalAmount,
            'hospital_share' => $item->hospital_share,
            'contractor_account_id' => $item->contractor_account_id
        ]);

        // Calculate business and contractor shares FIRST
        $businessAccount = $this->getOrCreateBusinessAccount($business);
        $businessShare = $totalAmount;
        $contractorShare = 0;

        // Check if item has contractor and calculate shares
        if ($item->contractor_account_id && $item->hospital_share < 100) {
            $contractor = ContractorProfile::find($item->contractor_account_id);
            
            if ($contractor) {
                $contractorShare = ($totalAmount * (100 - $item->hospital_share)) / 100;
                $businessShare = $totalAmount - $contractorShare;
                
                Log::info("Calculating hospital share", [
                    'total_amount' => $totalAmount,
                    'hospital_share_percentage' => $item->hospital_share,
                    'contractor_share_percentage' => 100 - $item->hospital_share,
                    'contractor_share_amount' => $contractorShare,
                    'business_share_amount' => $businessShare
                ]);
            }
        }
        
        // Transfer ONLY business share from client suspense to business account
        Log::info("Transferring money from client suspense to business account", [
            'from_account_id' => $clientSuspenseAccount->id,
            'to_account_id' => $businessAccount->id,
            'amount' => $businessShare,
            'description' => "{$item->name} ({$quantity})"
        ]);

        $transfer = $this->transferMoney(
            $clientSuspenseAccount,
            $businessAccount,
            $businessShare,
            'save_and_exit',
            $invoice,
            $item,
            "{$item->name} ({$quantity})"
        );

        Log::info("Money transfer completed", [
            'transfer_id' => $transfer->id,
            'from_account_balance_after' => $clientSuspenseAccount->fresh()->balance,
            'to_account_balance_after' => $businessAccount->fresh()->balance,
            'business_share_transferred' => $businessShare
        ]);

        // Transfer ONLY contractor share to contractor account (if exists)
        if ($contractorShare > 0 && $item->contractor_account_id) {
            $contractor = ContractorProfile::find($item->contractor_account_id);
            
            if ($contractor) {
                // Transfer contractor share directly from client suspense to contractor account
                $contractorAccount = $this->getOrCreateContractorAccount($contractor);
                
                $contractorTransfer = $this->transferMoney(
                    $clientSuspenseAccount,
                    $contractorAccount,
                    $contractorShare,
                    'contractor_share',
                    $invoice,
                    $item,
                    "{$item->name}"
                );
                
                // Record contractor balance statement
                ContractorBalanceHistory::recordChange(
                    $contractor->id,
                    $contractorAccount->id,
                    $contractorShare,
                    'credit',
                    "{$item->name} ({$quantity})",
                    'invoice',
                    $invoice->id,
                    [
                        'invoice_number' => $invoice->invoice_number,
                        'item_name' => $item->name,
                        'description' => "{$item->name}"
                    ]
                );
                
                // Update contractor account balance
                $contractor->increment('account_balance', $contractorShare);
                
                // Sync with money account if it exists
                if ($contractor->moneyAccount) {
                    $contractor->moneyAccount->credit($contractorShare);
                }
                
                Log::info("Contractor share processed", [
                    'contractor_id' => $contractor->id,
                    'contractor_name' => $contractor->name,
                    'contractor_share_amount' => $contractorShare,
                    'contractor_new_balance' => $contractor->fresh()->account_balance,
                    'contractor_transfer_id' => $contractorTransfer->id
                ]);
            } else {
                Log::error("Contractor not found for item", [
                    'item_id' => $item->id,
                    'contractor_account_id' => $item->contractor_account_id,
                    'item_name' => $item->name
                ]);
            }
        }

        // Note: BusinessBalanceHistory record is already created by transferMoney() method
        // No need to create it again here

        // Update business account balance
        $business->increment('account_balance', $businessShare);
        
        // Sync with money account if it exists
        if ($business->businessMoneyAccount) {
            $business->businessMoneyAccount->credit($businessShare);
        }

        $transferRecords[] = [
            'item_name' => $item->name,
            'amount' => $totalAmount,
            'business_share' => $businessShare,
            'contractor_share' => $contractorShare,
            'destination' => $businessAccount->name,
            'transfer_id' => $transfer->id
        ];

        Log::info("=== REGULAR ITEM PROCESSING COMPLETED ===", [
            'item_id' => $item->id,
            'item_name' => $item->name,
            'total_amount' => $totalAmount,
            'business_share' => $businessShare,
            'contractor_share' => $contractorShare,
            'hospital_share_percentage' => $item->hospital_share
        ]);
    }

    /**
     * Check if an item is covered by the current package adjustment
     * This helps determine if we should skip individual processing for package items
     */
    private function isItemCoveredByPackageAdjustment($item, $invoice)
    {
        Log::info("=== CHECKING IF ITEM IS COVERED BY PACKAGE ADJUSTMENT ===", [
            'item_id' => $item->id,
            'item_name' => $item->name,
            'invoice_id' => $invoice->id,
            'invoice_package_adjustment' => $invoice->package_adjustment,
            'client_id' => $invoice->client_id,
            'business_id' => $invoice->business_id
        ]);
        
        // Get client's valid package tracking records
        $validPackages = \App\Models\PackageTracking::where('client_id', $invoice->client_id)
            ->where('business_id', $invoice->business_id)
            ->where('status', 'active')
            ->where('remaining_quantity', '>', 0)
            ->where('valid_until', '>=', now()->toDateString())
            ->with(['packageItem.packageItems.includedItem'])
            ->get();
            
        Log::info("Found valid packages for coverage check", [
            'item_id' => $item->id,
            'valid_packages_count' => $validPackages->count(),
            'valid_packages' => $validPackages->map(function($pkg) {
                return [
                    'id' => $pkg->id,
                    'package_price' => $pkg->package_price,
                    'remaining_quantity' => $pkg->remaining_quantity,
                    'status' => $pkg->status
                ];
            })
        ]);

        foreach ($validPackages as $packageTracking) {
            // Check if the current item is included in this package
            $packageItems = $packageTracking->packageItem->packageItems;
            
            foreach ($packageItems as $packageItem) {
                if ($packageItem->included_item_id == $item->id) {
                    // This item is included in a valid package
                    // Check if the package adjustment amount matches this package's price
                    $packagePrice = $packageTracking->package_price;
                    
                    Log::info("Checking if item is covered by package adjustment", [
                        'item_id' => $item->id,
                        'item_name' => $item->name,
                        'package_tracking_id' => $packageTracking->id,
                        'package_price' => $packagePrice,
                        'invoice_package_adjustment' => $invoice->package_adjustment,
                        'is_covered' => $invoice->package_adjustment >= $packagePrice
                    ]);
                    
                    // If the package adjustment amount is at least the package price, 
                    // then this item is covered by the package adjustment
                    return $invoice->package_adjustment >= $packagePrice;
                }
            }
        }
        
        return false;
    }

    /**
     * Get the actual package amount for an invoice
     * This is needed because invoice.package_adjustment contains the item amount,
     * but for money movement we need the actual package amount
     */
    private function getPackageAmountForInvoice($invoice)
    {
        // Get client's valid package tracking records
        $validPackages = \App\Models\PackageTracking::where('client_id', $invoice->client_id)
            ->where('business_id', $invoice->business_id)
            ->where('status', 'active')
            ->where('remaining_quantity', '>', 0)
            ->where('valid_until', '>=', now()->toDateString())
            ->get();
            
        Log::info("Getting package amount for invoice", [
            'invoice_id' => $invoice->id,
            'invoice_package_adjustment' => $invoice->package_adjustment,
            'valid_packages_count' => $validPackages->count(),
            'valid_packages' => $validPackages->map(function($pkg) {
                return [
                    'id' => $pkg->id,
                    'package_price' => $pkg->package_price,
                    'remaining_quantity' => $pkg->remaining_quantity,
                    'status' => $pkg->status
                ];
            })
        ]);

        // Return the package price from the first valid package
        // In most cases, there should be only one active package per client
        if ($validPackages->count() > 0) {
            $packageAmount = $validPackages->first()->package_price;
            Log::info("Package amount determined", [
                'invoice_id' => $invoice->id,
                'package_amount' => $packageAmount,
                'invoice_package_adjustment' => $invoice->package_adjustment,
                'note' => 'Using package amount for money movement, not item amount'
            ]);
            return $packageAmount;
        }
        
        // Fallback to invoice package adjustment if no valid packages found
        Log::warning("No valid packages found, using invoice package adjustment as fallback", [
            'invoice_id' => $invoice->id,
            'fallback_amount' => $invoice->package_adjustment
        ]);
        return $invoice->package_adjustment;
    }

    /**
     * Get package information for invoice descriptions
     */
    private function getPackageInfoForInvoice($invoice)
    {
        try {
            // Get client's valid package tracking records
            $validPackages = \App\Models\PackageTracking::where('client_id', $invoice->client_id)
                ->where('business_id', $invoice->business_id)
                ->where('status', 'active')
                ->where('remaining_quantity', '>', 0)
                ->where('valid_until', '>=', now()->toDateString())
                ->with(['packageItem.packageItems.includedItem'])
                ->get();

            $packageDescriptions = [];
            $packageTrackingNumbers = [];
            
            foreach ($validPackages as $packageTracking) {
                if ($packageTracking->tracking_number) {
                    $packageName = $packageTracking->packageItem->name ?? 'Unknown Package';
                    $trackingNumber = $packageTracking->tracking_number; // Use the actual tracking number from database (format: PKG-X-YmdHis)
                    $packageDescriptions[] = "{$packageName} (Ref: {$trackingNumber})";
                    $packageTrackingNumbers[] = $trackingNumber;
                }
            }
            
            // Simplify description - use first package, add "and X more" if multiple
            if (count($packageDescriptions) == 1) {
                $description = $packageDescriptions[0];
            } else {
                $description = $packageDescriptions[0] . " and " . (count($packageDescriptions) - 1) . " more";
            }
            $trackingNumbers = implode(', ', array_unique($packageTrackingNumbers));
            
            return [
                'description' => $description ?: 'Package items',
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

    /**
     * Process package adjustment money movement from client suspense to business account
     * This is called only when package items are actually used (Save & Exit)
     */
    private function processPackageAdjustmentMoneyMovement($invoice, $clientSuspenseAccount, $business, &$transferRecords)
    {
        Log::info("=== PACKAGE ADJUSTMENT MONEY MOVEMENT START ===", [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $invoice->client_id,
            'business_id' => $business->id,
            'business_name' => $business->name,
            'description_format_update' => 'Updated package descriptions: Business=Package name + ref, Client=Item + quantity + ref',
            'amount_calculation_update' => 'Business statement now uses sum of item amounts (actual revenue) instead of package price',
            'timestamp' => now()->toDateTimeString()
        ]);

        // Check if package money has already been moved to prevent double movement
        $packageTracking = \App\Models\PackageTracking::where('client_id', $invoice->client_id)
            ->where('business_id', $business->id)
            ->where('invoice_id', $invoice->id)
            ->where('package_money_moved', true)
            ->first();

        if ($packageTracking) {
            Log::info("Package money already moved - skipping to prevent double movement", [
                'invoice_id' => $invoice->id,
                'package_tracking_id' => $packageTracking->id,
                'money_moved_at' => $packageTracking->money_moved_at,
                'money_movement_notes' => $packageTracking->money_movement_notes,
                'reason' => 'Package money has already been moved for this package tracking record'
            ]);
            return; // Skip money movement to prevent double processing
        }
        
        // Check if this invoice already has individual package item entries to prevent duplicates
        $hasIndividualPackageEntries = \App\Models\BalanceHistory::where('client_id', $invoice->client_id)
            ->where('reference_number', $invoice->invoice_number)
            ->where('transaction_type', 'debit')
            ->where('description', 'like', '%(x%')
            ->exists();

        if ($hasIndividualPackageEntries) {
            Log::info("Skipping package purchase entry - individual package items already recorded", [
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $invoice->client_id,
                'reason' => 'Individual package items already recorded as debits - avoiding duplicate package purchase entry'
            ]);
            return; // Skip creating package purchase entry
        }

        // For package items, we need to use the actual package amount, not the item amount
        // The invoice package_adjustment contains the item amount (113), but we need the package amount (120)
        $packageAdjustmentAmount = $this->getPackageAmountForInvoice($invoice);
        
        Log::info("=== PROCESSING PACKAGE ADJUSTMENT MONEY MOVEMENT START ===", [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'package_adjustment_amount' => $packageAdjustmentAmount,
            'client_suspense_account_id' => $clientSuspenseAccount->id,
            'client_suspense_account_name' => $clientSuspenseAccount->name,
            'client_suspense_balance_before' => $clientSuspenseAccount->balance,
            'business_id' => $business->id,
            'business_name' => $business->name
        ]);

        // Get business account
        $businessAccount = $this->getOrCreateBusinessAccount($business);
        
        Log::info("Business account details", [
            'business_account_id' => $businessAccount->id,
            'business_account_name' => $businessAccount->name,
            'business_account_balance_before' => $businessAccount->balance
        ]);
        
        // Create transfer record from client suspense to business account
        Log::info("Creating package adjustment transfer record", [
            'from_account_id' => $clientSuspenseAccount->id,
            'to_account_id' => $businessAccount->id,
            'amount' => $packageAdjustmentAmount,
            'invoice_id' => $invoice->id
        ]);

        // Get package information for description
        $packageInfo = $this->getPackageInfoForInvoice($invoice);
        
        $transfer = MoneyTransfer::create([
            'business_id' => $business->id,
            'from_account_id' => $clientSuspenseAccount->id,
            'to_account_id' => $businessAccount->id,
            'amount' => $packageAdjustmentAmount,
            'type' => 'package_adjustment_transfer',
            'description' => $packageInfo['description'],
            'reference' => $invoice->invoice_number,
            'invoice_id' => $invoice->id,
            'metadata' => [
                'invoice_number' => $invoice->invoice_number,
                'description' => 'Package adjustment money moved to business account when package items were used',
                'package_tracking_numbers' => $packageInfo['tracking_numbers']
            ]
        ]);

        Log::info("Transfer record created successfully", [
            'transfer_id' => $transfer->id,
            'transfer_type' => $transfer->type,
            'transfer_description' => $transfer->description
        ]);

        // Update account balances
        Log::info("Updating account balances", [
            'client_suspense_account_id' => $clientSuspenseAccount->id,
            'business_account_id' => $businessAccount->id,
            'amount' => $packageAdjustmentAmount
        ]);

        // Update account balances using the proper debit/credit methods
        Log::info("Debiting from client suspense account", [
            'account_id' => $clientSuspenseAccount->id,
            'amount' => $packageAdjustmentAmount,
            'balance_before' => $clientSuspenseAccount->balance
        ]);
        $clientSuspenseAccount->debit($packageAdjustmentAmount);  // Money goes out of client suspense
        
        Log::info("Crediting to business account", [
            'account_id' => $businessAccount->id,
            'amount' => $packageAdjustmentAmount,
            'balance_before' => $businessAccount->balance
        ]);
        $businessAccount->credit($packageAdjustmentAmount);       // Money comes into business account
        
        // Record the balance changes in BusinessBalanceHistory for dashboard display
        // Determine payment status and method from invoice
        $paymentStatus = 'paid'; // Default to paid for package adjustments
        $paymentMethod = null;
        
        if ($invoice) {
            $paymentMethods = $invoice->payment_methods ?? [];
            if (in_array('insurance', $paymentMethods)) {
                $paymentMethod = 'insurance';
                $paymentStatus = 'paid';
            } elseif (in_array('credit_arrangement', $paymentMethods) || $invoice->client->is_credit_eligible ?? false) {
                $paymentStatus = 'pending_payment';
                $paymentMethod = 'credit_arrangement';
            } else {
                $paymentStatus = $invoice->payment_status ?? 'paid';
                if (!empty($paymentMethods)) {
                    $paymentMethod = $paymentMethods[0] ?? null;
                }
            }
        }
        
        BusinessBalanceHistory::recordChange(
            $business->id,
            $businessAccount->id,
            $packageAdjustmentAmount,
            'credit',
            $transfer->description,
            'MoneyTransfer',
            $transfer->id,
            [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'package_adjustment_amount' => $packageAdjustmentAmount,
                'from_account' => $clientSuspenseAccount->name,
                'to_account' => $businessAccount->name
            ],
            auth()->id(),
            $paymentStatus,
            $paymentMethod
        );
        
        Log::info("Account balances after debit/credit operations", [
            'client_suspense_balance_after_debit' => $clientSuspenseAccount->fresh()->balance,
            'business_account_balance_after_credit' => $businessAccount->fresh()->balance
        ]);

        Log::info("=== PACKAGE ADJUSTMENT MONEY MOVEMENT COMPLETED ===", [
            'transfer_id' => $transfer->id,
            'amount' => $packageAdjustmentAmount,
            'from_account' => $clientSuspenseAccount->name,
            'to_account' => $businessAccount->name,
            'client_suspense_balance_before' => $clientSuspenseAccount->balance,
            'client_suspense_balance_after' => $clientSuspenseAccount->fresh()->balance,
            'business_account_balance_before' => $businessAccount->balance,
            'business_account_balance_after' => $businessAccount->fresh()->balance,
            'transfer_record_created' => true,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'timestamp' => now()->toISOString()
        ]);

        $transferRecords[] = [
            'item_name' => 'Package Adjustment',
            'amount' => $packageAdjustmentAmount,
            'destination' => $businessAccount->name,
            'transfer_id' => $transfer->id,
            'description' => "Package adjustment money moved to business account"
        ];

        // Mark package money as moved to prevent double movement
        $packageTracking = \App\Models\PackageTracking::where('client_id', $invoice->client_id)
            ->where('business_id', $business->id)
            ->where('invoice_id', $invoice->id)
            ->first();

        if ($packageTracking) {
            $packageTracking->markMoneyMoved("Package adjustment money moved to business account via Save & Exit");
            Log::info("Package money movement marked as completed", [
                'package_tracking_id' => $packageTracking->id,
                'invoice_id' => $invoice->id,
                'money_moved_at' => $packageTracking->money_moved_at
            ]);
        }
    }

    /**
     * Update package tracking records when package adjustments are used
     * This is called when items are actually completed/delivered (Save & Exit)
     */
    private function updatePackageTrackingForAdjustments($invoice, $items)
    {
        try {
            $clientId = $invoice->client_id;
            $businessId = $invoice->business_id;
            $branchId = $invoice->branch_id;
            
            Log::info("=== PACKAGE TRACKING UPDATE STARTED (Save & Exit) ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $clientId,
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'package_adjustment' => $invoice->package_adjustment,
                'items_count' => count($items)
            ]);
            
            // Get client's valid package tracking records
            $validPackages = \App\Models\PackageTracking::where('client_id', $clientId)
                ->where('business_id', $businessId)
                ->where('status', 'active')
                ->where('remaining_quantity', '>', 0)
                ->where('valid_until', '>=', now()->toDateString())
                ->with(['packageItem.packageItems.includedItem'])
                ->get();
                
            Log::info("Found valid packages for client", [
                'client_id' => $clientId,
                'valid_packages_count' => $validPackages->count(),
                'package_details' => $validPackages->map(function($pkg) {
                    return [
                        'id' => $pkg->id,
                        'package_name' => $pkg->packageItem->name,
                        'remaining_quantity' => $pkg->remaining_quantity,
                        'used_quantity' => $pkg->used_quantity,
                        'status' => $pkg->status,
                        'valid_until' => $pkg->valid_until
                    ];
                })
            ]);

            foreach ($items as $item) {
                $itemId = $item['id'] ?? $item['item_id'];
                $quantity = $item['quantity'] ?? 1;
                $remainingQuantity = $quantity;
                
                Log::info("Processing item for package tracking update", [
                    'item_id' => $itemId,
                    'item_name' => $item['name'] ?? 'Unknown',
                    'quantity' => $quantity,
                    'remaining_quantity' => $remainingQuantity
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
                    Log::info("Using branch-specific price for item", [
                        'item_id' => $itemId,
                        'branch_id' => $branchId,
                        'branch_price' => $price,
                        'default_price' => $itemModel->default_price
                    ]);
                } else {
                    Log::info("Using default price for item", [
                        'item_id' => $itemId,
                        'default_price' => $price
                    ]);
                }

                // Check if this item is included in any valid packages
                foreach ($validPackages as $packageTracking) {
                    if ($remainingQuantity <= 0) break;

                    Log::info("Checking package for item match", [
                        'package_tracking_id' => $packageTracking->id,
                        'package_name' => $packageTracking->packageItem->name,
                        'item_id' => $itemId,
                        'package_remaining_quantity' => $packageTracking->remaining_quantity
                    ]);

                    // Check if the current item is included in this package
                    $packageItems = $packageTracking->packageItem->packageItems;
                    
                    foreach ($packageItems as $packageItem) {
                        if ($packageItem->included_item_id == $itemId) {
                            Log::info("Item found in package", [
                                'package_tracking_id' => $packageTracking->id,
                                'package_item_id' => $packageItem->id,
                                'included_item_id' => $packageItem->included_item_id,
                                'max_quantity' => $packageItem->max_quantity,
                                'fixed_quantity' => $packageItem->fixed_quantity
                            ]);
                            
                            // Check max quantity constraint from package_items table
                            $maxQuantity = $packageItem->max_quantity ?? null;
                            $fixedQuantity = $packageItem->fixed_quantity ?? null;
                            
                            // Determine how much quantity can be used from this package
                            $availableFromPackage = $packageTracking->remaining_quantity;
                            
                            if ($maxQuantity !== null) {
                                // If max_quantity is set, limit by that
                                $availableFromPackage = min($availableFromPackage, $maxQuantity);
                                Log::info("Limited by max_quantity constraint", [
                                    'max_quantity' => $maxQuantity,
                                    'available_from_package' => $availableFromPackage
                                ]);
                            } elseif ($fixedQuantity !== null) {
                                // If fixed_quantity is set, use that
                                $availableFromPackage = min($availableFromPackage, $fixedQuantity);
                                Log::info("Limited by fixed_quantity constraint", [
                                    'fixed_quantity' => $fixedQuantity,
                                    'available_from_package' => $availableFromPackage
                                ]);
                            }
                            
                            // Calculate how much we can actually use
                            $quantityToUse = min($remainingQuantity, $availableFromPackage);
                            
                            Log::info("Calculated quantity to use from package", [
                                'remaining_quantity_needed' => $remainingQuantity,
                                'available_from_package' => $availableFromPackage,
                                'quantity_to_use' => $quantityToUse
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
                                
                                Log::info("Successfully updated package tracking for adjustment", [
                                    'package_tracking_id' => $packageTracking->id,
                                    'package_name' => $packageTracking->packageItem->name,
                                    'item_name' => $item['name'] ?? 'Unknown',
                                    'quantity_used' => $quantityToUse,
                                    'old_used_quantity' => $oldUsedQuantity,
                                    'new_used_quantity' => $packageTracking->used_quantity,
                                    'old_remaining_quantity' => $oldRemainingQuantity,
                                    'new_remaining_quantity' => $packageTracking->remaining_quantity,
                                    'old_status' => $oldStatus,
                                    'new_status' => $packageTracking->status
                                ]);
                                
                                $remainingQuantity -= $quantityToUse;
                                
                                // If we've used all available quantity from this package, break
                                if ($remainingQuantity <= 0) break;
                            } else {
                                Log::info("No quantity to use from package", [
                                    'package_tracking_id' => $packageTracking->id,
                                    'reason' => 'quantity_to_use is 0'
                                ]);
                            }
                        }
                    }
                }
                
                Log::info("Finished processing item for package tracking", [
                    'item_id' => $itemId,
                    'original_quantity' => $quantity,
                    'remaining_quantity_after_package_usage' => $remainingQuantity
                ]);
            }
            
            Log::info("=== PACKAGE TRACKING UPDATE COMPLETED (Save & Exit) ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total_items_processed' => count($items)
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error updating package tracking for adjustments", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number ?? 'Unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Create package sales records and account statements for package items
     * This is called when package items are actually used (Save & Exit)
     */
    private function createPackageSalesAndStatements($invoice, $items)
    {
        try {
            Log::info("=== PACKAGE SALES AND STATEMENTS CREATION STARTED ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $invoice->client_id,
                'business_id' => $invoice->business_id,
                'branch_id' => $invoice->branch_id,
                'package_adjustment' => $invoice->package_adjustment,
                'items_count' => count($items)
            ]);

            // Get client's valid package tracking records
            $validPackages = \App\Models\PackageTracking::where('client_id', $invoice->client_id)
                ->where('business_id', $invoice->business_id)
                ->where('status', 'active')
                ->where('remaining_quantity', '>', 0)
                ->where('valid_until', '>=', now()->toDateString())
                ->with(['packageItem.packageItems.includedItem', 'client'])
                ->get();
                
            Log::info("🔍 === PACKAGE SALES: CHECKING VALID PACKAGES ===", [
                'client_id' => $invoice->client_id,
                'business_id' => $invoice->business_id,
                'valid_packages_count' => $validPackages->count(),
                'valid_packages' => $validPackages->pluck('id')->toArray(),
                'invoice_package_adjustment' => $invoice->package_adjustment
            ]);

            $packageSalesCreated = [];
            $totalPackageAmount = 0;

            foreach ($items as $item) {
                $itemId = $item['id'] ?? $item['item_id'];
                $quantity = $item['quantity'] ?? 1;
                $remainingQuantity = $quantity;
                
                Log::info("Processing item for package sales creation", [
                    'item_id' => $itemId,
                    'item_name' => $item['name'] ?? 'Unknown',
                    'quantity' => $quantity,
                    'remaining_quantity' => $remainingQuantity
                ]);

                // Get the item price (branch-specific or default)
                $itemModel = \App\Models\Item::find($itemId);
                $price = $itemModel ? $itemModel->default_price : 0;
                
                Log::info("Item price calculation for package sales", [
                    'item_id' => $itemId,
                    'item_name' => $itemModel->name ?? 'Unknown',
                    'default_price' => $price,
                    'branch_id' => $invoice->branch_id
                ]);
                
                // Check for branch-specific price
                $branchPrice = \App\Models\BranchItemPrice::where('branch_id', $invoice->branch_id)
                    ->where('item_id', $itemId)
                    ->first();
                
                if ($branchPrice) {
                    $price = $branchPrice->price;
                    Log::info("Using branch-specific price for package sales", [
                        'item_id' => $itemId,
                        'branch_id' => $invoice->branch_id,
                        'branch_price' => $price,
                        'default_price' => $itemModel->default_price
                    ]);
                } else {
                    Log::info("Using default price for package sales", [
                        'item_id' => $itemId,
                        'default_price' => $price
                    ]);
                }

                // Check if this item is included in any valid packages
                foreach ($validPackages as $packageTracking) {
                    if ($remainingQuantity <= 0) break;

                    Log::info("Checking package for item match in package sales", [
                        'package_tracking_id' => $packageTracking->id,
                        'package_name' => $packageTracking->packageItem->name,
                        'item_id' => $itemId,
                        'package_remaining_quantity' => $packageTracking->remaining_quantity,
                        'package_status' => $packageTracking->status
                    ]);

                    // Check if the current item is included in this package
                    $packageItems = $packageTracking->packageItem->packageItems;
                    
                    Log::info("Package items in this package", [
                        'package_tracking_id' => $packageTracking->id,
                        'package_items_count' => $packageItems->count(),
                        'package_items' => $packageItems->map(function($pi) {
                            return [
                                'id' => $pi->id,
                                'included_item_id' => $pi->included_item_id,
                                'max_quantity' => $pi->max_quantity,
                                'fixed_quantity' => $pi->fixed_quantity
                            ];
                        })
                    ]);
                    
                    foreach ($packageItems as $packageItem) {
                        if ($packageItem->included_item_id == $itemId) {
                            Log::info("Item found in package for sales creation", [
                                'package_tracking_id' => $packageTracking->id,
                                'package_item_id' => $packageItem->id,
                                'included_item_id' => $packageItem->included_item_id,
                                'max_quantity' => $packageItem->max_quantity,
                                'fixed_quantity' => $packageItem->fixed_quantity
                            ]);
                            // Check max quantity constraint from package_items table
                            $maxQuantity = $packageItem->max_quantity ?? null;
                            $fixedQuantity = $packageItem->fixed_quantity ?? null;
                            
                            Log::info("Package quantity constraints", [
                                'package_tracking_id' => $packageTracking->id,
                                'max_quantity' => $maxQuantity,
                                'fixed_quantity' => $fixedQuantity,
                                'package_remaining_quantity' => $packageTracking->remaining_quantity
                            ]);
                            
                            // Determine how much quantity can be used from this package
                            $availableFromPackage = $packageTracking->remaining_quantity;
                            
                            if ($maxQuantity !== null) {
                                $availableFromPackage = min($availableFromPackage, $maxQuantity);
                                Log::info("Limited by max_quantity constraint for sales", [
                                    'max_quantity' => $maxQuantity,
                                    'available_from_package' => $availableFromPackage
                                ]);
                            } elseif ($fixedQuantity !== null) {
                                $availableFromPackage = min($availableFromPackage, $fixedQuantity);
                                Log::info("Limited by fixed_quantity constraint for sales", [
                                    'fixed_quantity' => $fixedQuantity,
                                    'available_from_package' => $availableFromPackage
                                ]);
                            }
                            
                            // Calculate how much we can actually use
                            $quantityToUse = min($remainingQuantity, $availableFromPackage);
                            
                            Log::info("Calculated quantity to use for package sales", [
                                'remaining_quantity_needed' => $remainingQuantity,
                                'available_from_package' => $availableFromPackage,
                                'quantity_to_use' => $quantityToUse
                            ]);
                            
                            if ($quantityToUse > 0) {
                                // Calculate amount for this package sale
                                $itemAmount = $price * $quantityToUse;
                                $totalPackageAmount += $itemAmount;

                                Log::info("Calculating package sale amount", [
                                    'item_id' => $itemId,
                                    'item_price' => $price,
                                    'quantity_to_use' => $quantityToUse,
                                    'item_amount' => $itemAmount,
                                    'total_package_amount' => $totalPackageAmount
                                ]);

                                // Create PackageSales record
                                // Use tracking_number from database, or generate if null (for backwards compatibility)
                                $trackingNumber = $packageTracking->tracking_number 
                                    ?? "PKG-{$packageTracking->id}-{$packageTracking->created_at->format('YmdHis')}";
                                
                                $packageSaleData = [
                                    'name' => $packageTracking->client->name ?? 'Unknown Client',
                                    'invoice_number' => $invoice->invoice_number,
                                    'pkn' => $trackingNumber,
                                    'date' => now()->toDateString(),
                                    'qty' => $quantityToUse,
                                    'item_name' => $itemModel->name ?? 'Unknown Item',
                                    'amount' => $itemAmount,
                                    'business_id' => $invoice->business_id,
                                    'branch_id' => $invoice->branch_id,
                                    'client_id' => $invoice->client_id,
                                    'package_tracking_id' => $packageTracking->id,
                                    'item_id' => $itemId,
                                    'status' => 'completed',
                                    'notes' => "Package item sale from invoice {$invoice->invoice_number}"
                                ];

                                Log::info("Creating PackageSales record", [
                                    'package_sale_data' => $packageSaleData
                                ]);

                                $packageSale = \App\Models\PackageSales::create($packageSaleData);

                                Log::info("Package sales record created", [
                                    'package_sale_id' => $packageSale->id,
                                    'package_tracking_id' => $packageTracking->id,
                                    'item_id' => $itemId,
                                    'item_name' => $itemModel->name,
                                    'quantity' => $quantityToUse,
                                    'amount' => $itemAmount,
                                    'pkn' => $packageSale->pkn
                                ]);

                                $packageSalesCreated[] = [
                                    'package_sale_id' => $packageSale->id,
                                    'package_tracking_id' => $packageTracking->id,
                                    'item_id' => $itemId,
                                    'item_name' => $itemModel->name,
                                    'quantity' => $quantityToUse,
                                    'amount' => $itemAmount,
                                    'pkn' => $packageSale->pkn
                                ];

                                $remainingQuantity -= $quantityToUse;
                                
                                // If we've used all available quantity from this package, break
                                if ($remainingQuantity <= 0) break;
                            }
                        }
                    }
                }
            }

            // Create client account statement entry (type: package)
            if (count($packageSalesCreated) > 0) {
                $this->createClientPackageStatementEntry($invoice, $packageSalesCreated);
            }

            // Create business account statement entry (type: package)
            if (count($packageSalesCreated) > 0) {
                $this->createBusinessPackageStatementEntry($invoice, $packageSalesCreated);
            }

            Log::info("=== PACKAGE SALES AND STATEMENTS CREATION COMPLETED ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'package_sales_created' => count($packageSalesCreated),
                'total_package_amount' => $totalPackageAmount,
                'package_sales_details' => $packageSalesCreated,
                'timestamp' => now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            Log::error("Error creating package sales and statements", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number ?? 'Unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Create client account statement entry for package usage (type: package)
     */
    private function createClientPackageStatementEntry($invoice, $packageSales)
    {
        try {
            $totalAmount = array_sum(array_column($packageSales, 'amount'));
            
            Log::info("=== CREATING CLIENT PACKAGE STATEMENT ENTRY ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $invoice->client_id,
                'client_name' => $invoice->client->name ?? 'Unknown',
                'total_amount' => $totalAmount,
                'package_sales_count' => count($packageSales),
                'package_sales_breakdown' => $packageSales,
                'description_format' => 'Item + quantity + ref (PKG tracking number)',
                'timestamp' => now()->toDateTimeString()
            ]);

            // Create BalanceHistory record for client statement (type: package)
            // This represents the client using their package items
            
            // Build description with constituent items and quantities in brackets, with package tracking numbers as ref
            $constituentItems = [];
            $packageTrackingNumbers = [];
            
            foreach ($packageSales as $sale) {
                $packageTracking = \App\Models\PackageTracking::find($sale['package_tracking_id']);
                if ($packageTracking) {
                    // Use tracking_number from database, or generate if null (for backwards compatibility)
                    $trackingNumber = $packageTracking->tracking_number 
                        ?? "PKG-{$packageTracking->id}-{$packageTracking->created_at->format('YmdHis')}";
                    $constituentItems[] = "{$sale['item_name']} ({$sale['quantity']}) (Ref: {$trackingNumber})";
                    $packageTrackingNumbers[] = $trackingNumber;
                }
            }
            
            $constituentItemsDescription = implode(', ', $constituentItems);
            $trackingNumbersRef = implode(', ', array_unique($packageTrackingNumbers));
            
            $balanceHistory = \App\Models\BalanceHistory::recordPackageUsage(
                $invoice->client,
                $totalAmount,
                "{$constituentItemsDescription} from invoice {$invoice->invoice_number}",
                $trackingNumbersRef, // Use package tracking numbers as reference instead of invoice number
                $constituentItemsDescription,
                'package_usage'
            );

            Log::info("Client package statement entry created successfully", [
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'balance_history_id' => $balanceHistory->id,
                'total_amount' => $totalAmount,
                'transaction_type' => 'package',
                'description' => $constituentItemsDescription,
                'description_format' => 'Item + quantity + ref (PKG tracking number)',
                'reference_number' => $invoice->invoice_number,
                'client_name' => $invoice->client->name ?? 'Unknown',
                'package_sales_count' => count($packageSales),
                'package_items_used' => $constituentItemsDescription,
                'package_tracking_numbers' => $trackingNumbersRef,
                'note' => 'Package usage shows item amount for display but does not affect client balance'
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to create client package statement entry", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'package_sales_data' => $packageSales,
                'timestamp' => now()->toDateTimeString()
            ]);
            throw $e;
        }
    }

    /**
     * Create business account statement entry for package sales (type: package)
     */
    private function createBusinessPackageStatementEntry($invoice, $packageSales)
    {
        try {
            // For business statement, we should use the sum of item amounts, not the package amount
            // The business should see the actual revenue from individual items sold (113), not the package price (120)
            $totalAmount = array_sum(array_column($packageSales, 'amount'));
            
            Log::info("=== CREATING BUSINESS PACKAGE STATEMENT ENTRY ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'business_id' => $invoice->business_id,
                'business_name' => $invoice->business->name ?? 'Unknown',
                'branch_id' => $invoice->branch_id,
                'business_revenue_amount' => $totalAmount,
                'item_amounts_sum' => array_sum(array_column($packageSales, 'amount')),
                'package_sales_count' => count($packageSales),
                'package_sales_breakdown' => $packageSales,
                'description_format' => 'Package name + ref (PKG tracking number)',
                'note' => 'Business statement uses sum of item amounts (actual revenue), not package price',
                'timestamp' => now()->toDateTimeString(),
                'fixes_applied' => [
                    'removed_plus_sign_from_package_amounts' => true,
                    'simplified_package_descriptions' => true,
                    'updated_description_format' => 'Package name + ref (PKG tracking number)',
                    'removed_verbose_prefixes' => true
                ]
            ]);

            // Create BusinessBalanceHistory record for business statement (type: package)
            // This represents the business receiving revenue from package item sales
            $business = $invoice->business;
            $businessAccount = $this->getOrCreateBusinessAccount($business);
            
            Log::info("Business account details for package statement", [
                'business_id' => $business->id,
                'business_account_id' => $businessAccount->id,
                'business_account_balance' => $businessAccount->balance,
                'business_account_name' => $businessAccount->name
            ]);
            
            // For package sales revenue, we should use 'package' type to avoid double crediting
            // The actual money transfer already happened in the package adjustment money movement
            
            // Build simplified description with package names and tracking numbers
            $packageDescriptions = [];
            $trackingNumbers = [];
            
            foreach ($packageSales as $sale) {
                $packageTracking = \App\Models\PackageTracking::find($sale['package_tracking_id']);
                if ($packageTracking) {
                    // Show individual item name and quantity for package sales revenue
                    $itemName = $sale['item_name'] ?? 'Unknown Item';
                    $itemQuantity = $sale['quantity'] ?? 1;
                    // Use tracking_number from database, or generate if null (for backwards compatibility)
                    $trackingNumber = $packageTracking->tracking_number 
                        ?? "PKG-{$packageTracking->id}-{$packageTracking->created_at->format('YmdHis')}";
                    $packageDescriptions[] = "{$itemName} ({$itemQuantity}) (Ref: {$trackingNumber})";
                    $trackingNumbers[] = $trackingNumber;
                }
            }
            
            // Create tracking numbers reference string
            $trackingNumbersRef = implode(', ', $trackingNumbers);
            
            // Use first package for main description, add "and X more" if multiple packages
            if (count($packageDescriptions) > 0) {
                if (count($packageDescriptions) == 1) {
                    $packageDescription = $packageDescriptions[0];
                } else {
                    $packageDescription = $packageDescriptions[0] . " and " . (count($packageDescriptions) - 1) . " more";
                }
            } else {
                // Fallback if no package descriptions were created
                $packageDescription = "Package items";
            }
            
            $businessBalanceHistory = \App\Models\BusinessBalanceHistory::recordPackageTransaction(
                $business->id,
                $businessAccount->id,
                $totalAmount,
                "{$packageDescription} from invoice {$invoice->invoice_number}",
                'package_sales',
                $invoice->id,
                [
                    'invoice_id' => $invoice->id,
                    'package_sales_count' => count($packageSales),
                    'package_items_sold' => implode(', ', array_column($packageSales, 'item_name')),
                    'package_amount' => $totalAmount,
                    'item_amounts_sum' => array_sum(array_column($packageSales, 'amount')),
                    'package_tracking_numbers' => $trackingNumbersRef,
                    'note' => 'Record only - money already transferred via package adjustment'
                ],
                null
            );

            Log::info("Business package statement entry created successfully", [
                'invoice_id' => $invoice->id,
                'business_id' => $invoice->business_id,
                'business_balance_history_id' => $businessBalanceHistory->id,
                'total_amount' => $totalAmount,
                'transaction_type' => 'package',
                'description_used' => "{$packageDescription} from invoice {$invoice->invoice_number}",
                'business_statement_fixes' => [
                    'no_plus_sign_on_package_amounts' => true,
                    'simplified_descriptions' => true,
                    'timestamp_format_tracking_numbers' => true
                ],
                'description' => "Package sales revenue: {$packageDescription} from invoice {$invoice->invoice_number}",
                'description_format' => 'Package name + ref (PKG tracking number)',
                'reference_type' => 'package_sales',
                'reference_id' => $invoice->id,
                'business_account_balance' => $businessAccount->balance,
                'package_sales_count' => count($packageSales),
                'package_items_sold' => implode(', ', array_column($packageSales, 'item_name')),
                'package_tracking_numbers' => $trackingNumbersRef,
                'note' => 'Package sales revenue recorded as package type (no balance change) - money already transferred via package adjustment'
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to create business package statement entry", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'invoice_id' => $invoice->id,
                'business_id' => $invoice->business_id,
                'package_sales_data' => $packageSales,
                'timestamp' => now()->toDateTimeString()
            ]);
            throw $e;
        }
    }

    /**
     * Process money movement from suspense accounts to final accounts
     * This is the core logic for Save & Exit functionality
     */
    private function processSuspenseToFinalMoneyMovement(Invoice $invoice, $items, $itemStatus, &$transferRecords)
    {
        try {
            Log::info("🎯 === SAVE & EXIT: SUSPENSE TO FINAL MONEY MOVEMENT STARTED ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $invoice->client_id,
                'client_name' => $invoice->client->name ?? 'Unknown',
                'business_id' => $invoice->business_id,
                'business_name' => $invoice->business->name ?? 'Unknown',
                'item_status' => $itemStatus,
                'items_count' => count($items),
                'items_data' => $items,
                'timestamp' => now()->toDateTimeString()
            ]);

            // Enhanced logging for debugging
            Log::info("🔍 === SAVE & EXIT: DETAILED ITEM ANALYSIS ===", [
                'items_being_processed' => $items,
                'item_status_being_applied' => $itemStatus,
                'total_items_count' => count($items),
                'invoice_details' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'total_amount' => $invoice->total_amount,
                    'status' => $invoice->status
                ]
            ]);

            $client = $invoice->client;
            $business = $invoice->business;

            // Get all suspense accounts for this client
            $packageSuspenseAccount = $this->getOrCreatePackageSuspenseAccount($business, $client->id);
            $generalSuspenseAccount = $this->getOrCreateGeneralSuspenseAccount($business, $client->id);
            $kashtreSuspenseAccount = $this->getOrCreateKashtreSuspenseAccount($business, $client->id);

            Log::info("💰 === SAVE & EXIT: SUSPENSE ACCOUNT BALANCES BEFORE PROCESSING ===", [
                'package_suspense_account_id' => $packageSuspenseAccount->id,
                'package_suspense_account_name' => $packageSuspenseAccount->name,
                'package_suspense_balance' => $packageSuspenseAccount->balance,
                'general_suspense_account_id' => $generalSuspenseAccount->id,
                'general_suspense_account_name' => $generalSuspenseAccount->name,
                'general_suspense_balance' => $generalSuspenseAccount->balance,
                'kashtre_suspense_account_id' => $kashtreSuspenseAccount->id,
                'kashtre_suspense_account_name' => $kashtreSuspenseAccount->name,
                'kashtre_suspense_balance' => $kashtreSuspenseAccount->balance,
                'total_suspense_balance' => $packageSuspenseAccount->balance + $generalSuspenseAccount->balance + $kashtreSuspenseAccount->balance
            ]);

            // Process ALL money in suspense accounts, not just the items being updated
            Log::info("🔄 === SAVE & EXIT: STARTING SUSPENSE ACCOUNT PROCESSING ===", [
                'package_suspense_balance' => $packageSuspenseAccount->balance,
                'general_suspense_balance' => $generalSuspenseAccount->balance,
                'kashtre_suspense_balance' => $kashtreSuspenseAccount->balance,
                'item_status' => $itemStatus
            ]);

            // Check if any package items are being consumed in this Save & Exit operation
            $hasPackageItemsBeingConsumed = false;
            $packageItemsFound = [];
            $nonPackageItemsFound = [];
            
            // Check if any items being updated are package items
            if (!empty($items)) {
                Log::info("🔍 === SAVE & EXIT: ANALYZING EACH ITEM FOR PACKAGE TYPE ===", [
                    'total_items_to_check' => count($items),
                    'items_structure' => $items
                ]);
                
                foreach ($items as $index => $item) {
                    $itemId = $item['item_id'] ?? $item['id'] ?? null;
                    Log::info("🔍 === SAVE & EXIT: CHECKING ITEM #{$index} ===", [
                        'item_id' => $itemId,
                        'item_data' => $item,
                        'item_name' => $item['name'] ?? 'Unknown'
                    ]);
                    
                    // Get the actual item from database to check its type
                    $actualItem = \App\Models\Item::find($itemId);
                    if ($actualItem) {
                        Log::info("🔍 === SAVE & EXIT: ITEM #{$index} DATABASE LOOKUP ===", [
                            'item_id' => $actualItem->id,
                            'item_name' => $actualItem->name,
                            'item_type' => $actualItem->type,
                            'item_price' => $actualItem->price,
                            'is_package_type' => $actualItem->type === 'package'
                        ]);
                        
                        // Check if this is a package item OR if it's part of a package adjustment
                        $isPackageItem = $actualItem->type === 'package';
                        
                        // Check if this specific item is included in any valid packages
                        $isPackageAdjustmentItem = false;
                        if ($invoice->package_adjustment > 0) {
                            $validPackages = \App\Models\PackageTracking::where('client_id', $invoice->client_id)
                                ->where('business_id', $invoice->business_id)
                                ->where('status', 'active')
                                ->where('remaining_quantity', '>', 0)
                                ->get();
                            
                            foreach ($validPackages as $packageTracking) {
                                $packageItems = $packageTracking->packageItem->packageItems;
                                foreach ($packageItems as $packageItem) {
                                    if ($packageItem->included_item_id == $actualItem->id) {
                                        $isPackageAdjustmentItem = true;
                                        break 2;
                                    }
                                }
                            }
                        }
                        
                        if ($isPackageItem || $isPackageAdjustmentItem) {
                            $hasPackageItemsBeingConsumed = true;
                            $packageItemsFound[] = [
                                'item_id' => $actualItem->id,
                                'item_name' => $actualItem->name,
                                'item_type' => $actualItem->type,
                                'item_price' => $actualItem->price,
                                'is_package_type' => $isPackageItem,
                                'is_package_adjustment' => $isPackageAdjustmentItem
                            ];
                            Log::info("✅ === SAVE & EXIT: PACKAGE ITEM FOUND ===", [
                                'item_id' => $actualItem->id,
                                'item_name' => $actualItem->name,
                                'item_type' => $actualItem->type,
                                'is_package_type' => $isPackageItem,
                                'is_package_adjustment' => $isPackageAdjustmentItem,
                                'invoice_package_adjustment' => $invoice->package_adjustment
                            ]);
                        } else {
                            $nonPackageItemsFound[] = [
                                'item_id' => $actualItem->id,
                                'item_name' => $actualItem->name,
                                'item_type' => $actualItem->type,
                                'item_price' => $actualItem->price
                            ];
                            Log::info("ℹ️ === SAVE & EXIT: NON-PACKAGE ITEM FOUND ===", [
                                'item_id' => $actualItem->id,
                                'item_name' => $actualItem->name,
                                'item_type' => $actualItem->type
                            ]);
                        }
                    } else {
                        Log::warning("⚠️ === SAVE & EXIT: ITEM NOT FOUND IN DATABASE ===", [
                            'item_id' => $itemId,
                            'item_data' => $item
                        ]);
                    }
                }
            } else {
                Log::info("ℹ️ === SAVE & EXIT: NO ITEMS TO PROCESS ===", [
                    'items_array' => $items,
                    'items_count' => count($items)
                ]);
            }

            Log::info("📦 === SAVE & EXIT: PACKAGE ITEMS CONSUMPTION CHECK ===", [
                'has_package_items_being_consumed' => $hasPackageItemsBeingConsumed,
                'package_items_found' => $packageItemsFound,
                'non_package_items_found' => $nonPackageItemsFound,
                'package_items_count' => count($packageItemsFound),
                'non_package_items_count' => count($nonPackageItemsFound),
                'items_being_updated' => $items,
                'package_suspense_balance' => $packageSuspenseAccount->balance,
                'item_status' => $itemStatus,
                'decision_logic' => [
                    'will_package_suspense_move' => $hasPackageItemsBeingConsumed && $packageSuspenseAccount->balance > 0,
                    'package_suspense_balance' => $packageSuspenseAccount->balance,
                    'has_package_items' => $hasPackageItemsBeingConsumed
                ]
            ]);

            // Process Package Suspense Account ONLY if package items are being consumed
            if ($packageSuspenseAccount->balance > 0 && $hasPackageItemsBeingConsumed) {
                Log::info("📦 === SAVE & EXIT: PROCESSING PACKAGE SUSPENSE ACCOUNT ===", [
                    'balance' => $packageSuspenseAccount->balance,
                    'account_name' => $packageSuspenseAccount->name,
                    'account_id' => $packageSuspenseAccount->id,
                    'has_package_items_being_consumed' => $hasPackageItemsBeingConsumed,
                    'package_items_triggering_movement' => $packageItemsFound,
                    'decision_reason' => 'Package suspense has money, moving ALL package money on Save & Exit'
                ]);

                $businessAccount = $this->getOrCreateBusinessAccount($business);
                
                // Get package tracking information for description
                // For package adjustments, look for the most recent package tracking record
                $packageTracking = \App\Models\PackageTracking::where('client_id', $client->id)
                    ->where('business_id', $business->id)
                    ->where('status', 'active')
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                // For package adjustments, use the actual item being consumed
                $packageDescription = "Package Revenue";
                $trackingNumber = null;
                
                if ($packageTracking) {
                    $trackingNumber = $packageTracking->tracking_number ?? "PKG-{$packageTracking->id}";
                    
                    // Get the actual item being consumed from the invoice
                    $consumedItem = null;
                    foreach ($invoice->items as $item) {
                        $itemModel = \App\Models\Item::find($item['id'] ?? $item['item_id'] ?? null);
                        if ($itemModel && $itemModel->type !== 'package') {
                            $consumedItem = $itemModel;
                            $quantity = $item['quantity'] ?? 1;
                            // Show package name instead of individual item name for credit entries
                            $packageName = $packageTracking->packageItem->name ?? 'Package';
                            $packageQuantity = 1; // Package is always quantity 1, not the individual item quantity
                            $packageDescription = "{$packageName}  (Ref: {$trackingNumber}) from invoice {$invoice->invoice_number}";
                            break;
                        }
                    }
                    
                    // If no consumed item found, use package name
                    if (!$consumedItem) {
                        $packageName = $packageTracking->packageItem->name ?? 'Package';
                        $packageDescription = "{$packageName} (Ref: {$trackingNumber}) from invoice {$invoice->invoice_number}";
                    }
                }
                
                $transfer = $this->transferMoney(
                    $packageSuspenseAccount,
                    $businessAccount,
                    $packageSuspenseAccount->balance,
                    'suspense_to_final',
                    $invoice,
                    null,
                    $packageDescription,
                    $trackingNumber, // Pass the tracking number
                    'debit' // Explicitly set type to 'debit'
                );

                $transfer->markMoneyMovedToFinalAccount();
                
                // Mark the suspense account as moved
                $packageSuspenseAccount->markAsMoved("Money transferred to Business Account via Save & Exit");

                $transferRecords[] = [
                    'item_name' => 'Package Suspense Account',
                    'amount' => $packageSuspenseAccount->balance,
                    'source_suspense' => $packageSuspenseAccount->name,
                    'destination' => $businessAccount->name,
                    'transfer_id' => $transfer->id
                ];

                Log::info("✅ SAVE & EXIT: Package Suspense processed", [
                    'amount_transferred' => $packageSuspenseAccount->balance,
                    'transfer_id' => $transfer->id
                ]);
            } else {
                Log::info("⏭️ SAVE & EXIT: SKIPPING PACKAGE SUSPENSE - No money in package suspense", [
                    'package_suspense_balance' => $packageSuspenseAccount->balance,
                    'has_package_items_being_consumed' => $hasPackageItemsBeingConsumed,
                    'package_items_found' => $packageItemsFound,
                    'non_package_items_found' => $nonPackageItemsFound,
                    'decision_reason' => 'Package suspense balance is zero',
                    'skip_conditions' => [
                        'balance_is_zero' => $packageSuspenseAccount->balance <= 0
                    ]
                ]);
            }

            // Process shared package items - debit business and credit contractor for their share
            // This runs SEPARATELY from suspense processing because package money is already in business account
            // when package items are consumed (money moved when package was purchased)
            if ($hasPackageItemsBeingConsumed && !empty($items)) {
                Log::info("🔍 === SAVE & EXIT: CHECKING PACKAGE ITEMS FOR CONTRACTOR SHARES ===", [
                    'package_items_found' => $packageItemsFound,
                    'invoice_items' => $invoice->items,
                    'package_suspense_balance' => $packageSuspenseAccount->balance,
                    'note' => 'Processing contractor shares regardless of suspense balance - money already in business account'
                ]);

                $businessAccount = $this->getOrCreateBusinessAccount($business);
                $totalContractorShare = 0;
                $contractorShares = [];

                // Get package tracking for description
                $packageTracking = \App\Models\PackageTracking::where('client_id', $client->id)
                    ->where('business_id', $business->id)
                    ->where('status', 'active')
                    ->orderBy('created_at', 'desc')
                    ->first();
                $trackingNumber = $packageTracking ? ($packageTracking->tracking_number ?? "PKG-{$packageTracking->id}") : null;

                // Process each package item being consumed
                foreach ($items as $itemData) {
                    $itemId = $itemData['item_id'] ?? $itemData['id'] ?? null;
                    if (!$itemId) continue;

                    $actualItem = \App\Models\Item::find($itemId);
                    if (!$actualItem) continue;

                    // Check if this is a package adjustment item (item being consumed from package)
                    $isPackageAdjustmentItem = false;
                    if ($invoice->package_adjustment > 0) {
                        $validPackages = \App\Models\PackageTracking::where('client_id', $invoice->client_id)
                            ->where('business_id', $invoice->business_id)
                            ->where('status', 'active')
                            ->where('remaining_quantity', '>', 0)
                            ->get();
                        
                        foreach ($validPackages as $packageTracking) {
                            $packageItems = $packageTracking->packageItem->packageItems;
                            foreach ($packageItems as $packageItem) {
                                if ($packageItem->included_item_id == $actualItem->id) {
                                    $isPackageAdjustmentItem = true;
                                    break 2;
                                }
                            }
                        }
                    }

                    // Only process if this is a package adjustment item (being consumed) and it's a shared item
                    if ($isPackageAdjustmentItem && $actualItem->contractor_account_id && $actualItem->hospital_share < 100) {
                        $contractor = \App\Models\ContractorProfile::find($actualItem->contractor_account_id);
                        if ($contractor) {
                            // Get the item amount from invoice (what was actually consumed)
                            // Try to get total_amount first, otherwise calculate from price * quantity
                            $quantity = $itemData['quantity'] ?? 1;
                            if (isset($itemData['total_amount']) && $itemData['total_amount'] > 0) {
                                $itemAmount = $itemData['total_amount'];
                            } else {
                                $itemPrice = $itemData['price'] ?? $actualItem->price ?? 0;
                                $itemAmount = $itemPrice * $quantity;
                            }

                            // Calculate contractor share
                            $contractorShare = ($itemAmount * (100 - $actualItem->hospital_share)) / 100;
                            $businessShare = $itemAmount - $contractorShare;

                            $totalContractorShare += $contractorShare;

                            $contractorShares[] = [
                                'item_id' => $actualItem->id,
                                'item_name' => $actualItem->name,
                                'item_amount' => $itemAmount,
                                'contractor_share' => $contractorShare,
                                'business_share' => $businessShare,
                                'hospital_share_percentage' => $actualItem->hospital_share,
                                'contractor_share_percentage' => 100 - $actualItem->hospital_share,
                                'contractor_id' => $contractor->id,
                                'contractor_name' => $contractor->name
                            ];

                            Log::info("💰 === SAVE & EXIT: PACKAGE ITEM CONTRACTOR SHARE CALCULATION ===", [
                                'item_id' => $actualItem->id,
                                'item_name' => $actualItem->name,
                                'item_amount' => $itemAmount,
                                'quantity' => $quantity,
                                'contractor_share' => $contractorShare,
                                'business_share' => $businessShare,
                                'hospital_share_percentage' => $actualItem->hospital_share,
                                'contractor_share_percentage' => 100 - $actualItem->hospital_share,
                                'contractor_id' => $contractor->id,
                                'contractor_name' => $contractor->name
                            ]);

                            // Get contractor account
                            $contractorAccount = $this->getOrCreateContractorAccount($contractor);

                            // Get balances BEFORE transfer (for history records)
                            $businessAccountBalanceBefore = $businessAccount->fresh()->balance;
                            $contractorAccountBalanceBefore = $contractorAccount->fresh()->balance;

                            // Transfer contractor share from business account to contractor account
                            // Money is already in business account from when package was purchased
                            $contractorTransfer = $this->transferMoney(
                                $businessAccount,
                                $contractorAccount,
                                $contractorShare,
                                'suspense_to_final',
                                $invoice,
                                $actualItem,
                                "{$actualItem->name} ({$quantity})",
                                $trackingNumber,
                                'debit'
                            );

                            $contractorTransfer->markMoneyMovedToFinalAccount();

                            // Get balances AFTER transfer
                            $businessAccountBalanceAfter = $businessAccount->fresh()->balance;
                            $contractorAccountBalanceAfter = $contractorAccount->fresh()->balance;

                            // Create BusinessBalanceHistory record for the debit from business account
                            // Note: We manually create this because transferMoney only creates history for TO accounts when they're business accounts
                            // The balance is already updated by transferMoney, so we use the correct previous balance
                            \App\Models\BusinessBalanceHistory::create([
                                'business_id' => $business->id,
                                'money_account_id' => $businessAccount->id,
                                'previous_balance' => $businessAccountBalanceBefore,
                                'amount' => $contractorShare,
                                'new_balance' => $businessAccountBalanceAfter,
                                'type' => 'debit',
                                'description' => "{$actualItem->name} ({$quantity})" . ($trackingNumber ? " (Ref: {$trackingNumber})" : ""),
                                'reference_type' => 'invoice',
                                'reference_id' => $invoice->id,
                                'metadata' => [
                                    'invoice_number' => $invoice->invoice_number,
                                    'item_id' => $actualItem->id,
                                    'item_name' => $actualItem->name,
                                    'contractor_share' => $contractorShare,
                                    'contractor_id' => $contractor->id,
                                    'transfer_id' => $contractorTransfer->id,
                                    'description' => "{$actualItem->name}"
                                ],
                                'user_id' => auth()->id()
                            ]);

                            // Create ContractorBalanceHistory record for the credit to contractor account
                            // Note: transferMoney doesn't create contractor balance history, so we create it manually
                            \App\Models\ContractorBalanceHistory::create([
                                'contractor_profile_id' => $contractor->id,
                                'money_account_id' => $contractorAccount->id,
                                'previous_balance' => $contractorAccountBalanceBefore,
                                'amount' => $contractorShare,
                                'new_balance' => $contractorAccountBalanceAfter,
                                'type' => 'credit',
                                'description' => "{$actualItem->name} ({$quantity})" . ($trackingNumber ? " (Ref: {$trackingNumber})" : ""),
                                'reference_type' => 'invoice',
                                'reference_id' => $invoice->id,
                                'metadata' => [
                                    'invoice_number' => $invoice->invoice_number,
                                    'item_id' => $actualItem->id,
                                    'item_name' => $actualItem->name,
                                    'contractor_share' => $contractorShare,
                                    'transfer_id' => $contractorTransfer->id,
                                    'description' => "{$actualItem->name}"
                                ],
                                'user_id' => auth()->id()
                            ]);

                            Log::info("✅ === SAVE & EXIT: PACKAGE ITEM CONTRACTOR SHARE TRANSFERRED ===", [
                                'item_id' => $actualItem->id,
                                'item_name' => $actualItem->name,
                                'contractor_share' => $contractorShare,
                                'transfer_id' => $contractorTransfer->id,
                                'from_account' => $businessAccount->name,
                                'to_account' => $contractorAccount->name,
                                'business_account_balance_before' => $businessAccountBalanceBefore,
                                'business_account_balance_after' => $businessAccountBalanceAfter,
                                'contractor_account_balance_before' => $contractorAccountBalanceBefore,
                                'contractor_account_balance_after' => $contractorAccountBalanceAfter,
                                'business_balance_history_created' => true,
                                'contractor_balance_history_created' => true
                            ]);

                            // Record the transfer
                            $transferRecords[] = [
                                'item_name' => $actualItem->name,
                                'amount' => $contractorShare,
                                'source_suspense' => $businessAccount->name,
                                'destination' => $contractorAccount->name,
                                'transfer_id' => $contractorTransfer->id,
                                'type' => 'contractor_share'
                            ];
                        }
                    }
                }

                if ($totalContractorShare > 0) {
                    Log::info("✅ === SAVE & EXIT: PACKAGE ITEMS CONTRACTOR SHARES PROCESSED ===", [
                        'total_contractor_share' => $totalContractorShare,
                        'contractor_shares' => $contractorShares,
                        'business_account_balance_after' => $businessAccount->fresh()->balance
                    ]);
                } else {
                    Log::info("ℹ️ === SAVE & EXIT: NO CONTRACTOR SHARES FOR PACKAGE ITEMS ===", [
                        'package_items_checked' => count($packageItemsFound),
                        'reason' => 'No shared items found in package items being consumed'
                    ]);
                }
            }

            // Process General Suspense Account - Create individual records for each item
            // BUT ONLY if there are no package items being consumed (package items should not be in general suspense)
            if ($generalSuspenseAccount->balance > 0 && !$hasPackageItemsBeingConsumed) {
                Log::info("📦 === SAVE & EXIT: PROCESSING GENERAL SUSPENSE ACCOUNT ===", [
                    'balance' => $generalSuspenseAccount->balance,
                    'account_name' => $generalSuspenseAccount->name,
                    'account_id' => $generalSuspenseAccount->id,
                    'decision_reason' => 'General suspense has balance, creating individual item records',
                    'destination_account_type' => 'business_account'
                ]);

                $businessAccount = $this->getOrCreateBusinessAccount($business);
                
                // Get individual money transfers from general suspense to create separate records
                // Filter by credit records (money coming into suspense) that haven't been moved yet
                // Filter by invoice_id to ensure we only process transfers for this invoice
                $generalTransfers = \App\Models\MoneyTransfer::where('to_account_id', $generalSuspenseAccount->id)
                    ->where('type', 'credit')
                    ->where('money_moved_to_final_account', false)
                    ->where('invoice_id', $invoice->id)
                    ->where('transfer_type', 'suspense_movement')
                    ->get();
                
                // Filter transfers to only include items that were actually updated
                $updatedItemIds = [];
                foreach ($items as $itemData) {
                    $itemId = $itemData['item_id'] ?? $itemData['id'] ?? null;
                    if ($itemId) {
                        $updatedItemIds[] = $itemId;
                    }
                }
                
                Log::info("🔍 === SAVE & EXIT: FILTERING TRANSFERS BY UPDATED ITEMS ===", [
                    'general_suspense_account_id' => $generalSuspenseAccount->id,
                    'invoice_id' => $invoice->id,
                    'updated_item_ids' => $updatedItemIds,
                    'total_transfers_before_filter' => $generalTransfers->count(),
                    'items_being_processed' => $items,
                    'transfer_types_included' => ['suspense_movement', 'credit_suspense_movement']
                ]);
                
                // Only process transfers for items that were actually updated
                // For credit clients, transfers might not have item_id set, so include all transfers for this invoice
                $generalTransfers = $generalTransfers->filter(function($transfer) use ($updatedItemIds, $invoice) {
                    // If transfer has item_id, it must be in updated items
                    if ($transfer->item_id) {
                        return in_array($transfer->item_id, $updatedItemIds);
                    }
                    // If no item_id but invoice matches, include it (credit client transfers)
                    return $transfer->invoice_id == $invoice->id;
                });
                
                Log::info("✅ === SAVE & EXIT: FILTERED TRANSFERS RESULT ===", [
                    'transfers_after_filter' => $generalTransfers->count(),
                    'filtered_transfer_ids' => $generalTransfers->pluck('id')->toArray(),
                    'general_suspense_balance' => $generalSuspenseAccount->balance
                ]);

                // If no transfers found but suspense account has balance, move entire balance
                // This handles cases where transfers might not have item_id set or don't match filters
                if ($generalTransfers->isEmpty() && $generalSuspenseAccount->balance > 0) {
                    Log::info("⚠️ === SAVE & EXIT: NO TRANSFERS FOUND, MOVING ENTIRE SUSPENSE BALANCE ===", [
                        'general_suspense_balance' => $generalSuspenseAccount->balance,
                        'invoice_id' => $invoice->id,
                        'reason' => 'No matching transfers found, moving entire balance as fallback'
                    ]);
                    
                    // Move entire balance from general suspense to business account
                    $finalTransfer = $this->transferMoney(
                        $generalSuspenseAccount,
                        $businessAccount,
                        $generalSuspenseAccount->balance,
                        'suspense_to_final',
                        $invoice,
                        null,
                        "General Suspense - Final Transfer (Invoice: {$invoice->invoice_number})",
                        null,
                        'debit'
                    );
                    
                    $finalTransfer->markMoneyMovedToFinalAccount();
                    $generalSuspenseAccount->markAsMoved("Money transferred to Business Account via Save & Exit");
                    
                    $transferRecords[] = [
                        'item_name' => 'General Suspense Account',
                        'amount' => $generalSuspenseAccount->balance,
                        'source_suspense' => $generalSuspenseAccount->name,
                        'destination' => $businessAccount->name,
                        'transfer_id' => $finalTransfer->id
                    ];
                    
                    Log::info("✅ SAVE & EXIT: General Suspense processed (fallback - entire balance)", [
                        'amount_transferred' => $generalSuspenseAccount->balance,
                        'transfer_id' => $finalTransfer->id
                    ]);
                } else {
                    // Process individual transfers (normal flow for regular clients and credit clients with item_id set)
                    $itemDataById = collect($items)->mapWithKeys(function ($itemData) {
                        $key = $itemData['item_id'] ?? $itemData['id'] ?? null;
                        return $key ? [$key => $itemData] : [];
                    });

                    foreach ($generalTransfers as $transfer) {
                    // Get the item information for proper description
                    $item = null;
                    if ($transfer->item_id) {
                        $item = \App\Models\Item::find($transfer->item_id);
                    }

                    $description = "General Item - Final Transfer";
                    $displayQuantity = null;

                    if ($item) {
                        $itemDataForDisplay = $itemDataById[$item->id] ?? null;
                        $rawQuantity = $itemDataForDisplay['quantity'] ?? null;
                        $totalAmountForDisplay = $itemDataForDisplay['total_amount'] ?? $transfer->amount;
                        $displayQuantity = $this->resolveQuantityForDisplay(
                            $rawQuantity,
                            $itemDataForDisplay ?? [],
                            $totalAmountForDisplay,
                            $item
                        );
                        $description = "{$item->name} ({$displayQuantity})";
                    } elseif (!empty($transfer->description)) {
                        if (preg_match('/\((x?\d+(?:\.\d+)?)\)/i', $transfer->description, $matches)) {
                            $displayQuantity = ltrim($matches[1], 'xX');
                            $description = "{$transfer->description}";
                        }
                    }

                    // Check if item has hospital share and contractor
                    if ($item && $item->contractor_account_id && $item->hospital_share < 100) {
                        Log::info("=== PROCESSING HOSPITAL SHARE FOR SAVE & EXIT ===", [
                            'item_id' => $item->id,
                            'item_name' => $item->name,
                            'total_amount' => $transfer->amount,
                            'hospital_share' => $item->hospital_share,
                            'contractor_account_id' => $item->contractor_account_id,
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'suspense_account_id' => $generalSuspenseAccount->id,
                            'suspense_account_name' => $generalSuspenseAccount->name,
                            'suspense_account_balance_before' => $generalSuspenseAccount->balance,
                            'business_account_id' => $businessAccount->id,
                            'business_account_name' => $businessAccount->name,
                            'business_account_balance_before' => $businessAccount->balance
                        ]);

                        // Calculate shares FIRST
                        $contractorShare = ($transfer->amount * (100 - $item->hospital_share)) / 100;
                        $businessShare = $transfer->amount - $contractorShare;

                        Log::info("💰 Hospital share calculation - SAVE & EXIT", [
                            'total_amount' => $transfer->amount,
                            'hospital_share_percentage' => $item->hospital_share,
                            'contractor_share_percentage' => 100 - $item->hospital_share,
                            'contractor_share_amount' => $contractorShare,
                            'business_share_amount' => $businessShare,
                            'verification' => ($businessShare + $contractorShare) == $transfer->amount ? '✅ CORRECT' : '❌ MISMATCH'
                        ]);

                        // Transfer ONLY business share from suspense to business account
                        Log::info("🔄 Transferring business share - SAVE & EXIT", [
                            'from_account' => $generalSuspenseAccount->name,
                            'from_account_id' => $generalSuspenseAccount->id,
                            'from_account_balance_before' => $generalSuspenseAccount->balance,
                            'to_account' => $businessAccount->name,
                            'to_account_id' => $businessAccount->id,
                            'to_account_balance_before' => $businessAccount->balance,
                            'amount' => $businessShare
                        ]);
                        
                        $finalTransfer = $this->transferMoney(
                            $generalSuspenseAccount,
                            $businessAccount,
                            $businessShare,
                            'suspense_to_final',
                            $invoice,
                            $item,
                            $description,
                            null,
                            'debit'
                        );
                        
                        Log::info("✅ Business share transfer completed - SAVE & EXIT", [
                            'transfer_id' => $finalTransfer->id,
                            'from_account_balance_after' => $generalSuspenseAccount->fresh()->balance,
                            'to_account_balance_after' => $businessAccount->fresh()->balance,
                            'amount_transferred' => $businessShare
                        ]);

                        // Note: BusinessBalanceHistory record is already created by transferMoney() method
                        // No need to create it again here

                        // Transfer ONLY contractor share from suspense to contractor account
                        $contractor = \App\Models\ContractorProfile::find($item->contractor_account_id);
                        if ($contractor && $contractorShare > 0) {
                            $contractorAccount = $this->getOrCreateContractorAccount($contractor);
                            
                            Log::info("🔄 Transferring contractor share - SAVE & EXIT", [
                                'from_account' => $generalSuspenseAccount->name,
                                'from_account_id' => $generalSuspenseAccount->id,
                                'from_account_balance_before' => $generalSuspenseAccount->fresh()->balance,
                                'to_account' => $contractorAccount->name,
                                'to_account_id' => $contractorAccount->id,
                                'to_account_balance_before' => $contractorAccount->balance,
                                'contractor_id' => $contractor->id,
                                'contractor_name' => $contractor->name,
                                'amount' => $contractorShare
                            ]);
                            
                            $contractorTransfer = $this->transferMoney(
                                $generalSuspenseAccount,
                                $contractorAccount,
                                $contractorShare,
                                'contractor_share',
                                $invoice,
                                $item,
                                "{$item->name}",
                                null,
                                'debit'
                            );
                            
                            Log::info("✅ Contractor share transfer completed - SAVE & EXIT", [
                                'transfer_id' => $contractorTransfer->id,
                                'from_account_balance_after' => $generalSuspenseAccount->fresh()->balance,
                                'to_account_balance_after' => $contractorAccount->fresh()->balance,
                                'amount_transferred' => $contractorShare
                            ]);

                            // Record contractor balance statement
                            \App\Models\ContractorBalanceHistory::recordChange(
                                $contractor->id,
                                $contractorAccount->id,
                                $contractorShare,
                                'credit',
                                "{$item->name} (x1)",
                                'invoice',
                                $invoice->id,
                                [
                                    'invoice_number' => $invoice->invoice_number,
                                    'item_name' => $item->name,
                                    'description' => "{$item->name}"
                                ]
                            );

                            // Update contractor account balance
                            $contractor->increment('account_balance', $contractorShare);

                            // Sync with money account if it exists
                            if ($contractor->moneyAccount) {
                                $contractor->moneyAccount->credit($contractorShare);
                            }

                            Log::info("✅ Contractor share processed - SAVE & EXIT", [
                                'contractor_id' => $contractor->id,
                                'contractor_name' => $contractor->name,
                                'contractor_share_amount' => $contractorShare,
                                'contractor_account_balance' => $contractor->fresh()->account_balance,
                                'contractor_transfer_id' => $contractorTransfer->id
                            ]);
                        } else {
                            Log::warning("⚠️ Contractor share skipped - SAVE & EXIT", [
                                'contractor_account_id' => $item->contractor_account_id,
                                'contractor_share' => $contractorShare,
                                'reason' => $contractor ? 'Contractor share is 0' : 'Contractor not found'
                            ]);
                        }

                        $transferRecords[] = [
                            'item_name' => $item->name,
                            'amount' => $transfer->amount,
                            'business_share' => $businessShare,
                            'contractor_share' => $contractorShare,
                            'source_suspense' => $generalSuspenseAccount->name,
                            'destination' => $businessAccount->name,
                            'transfer_id' => $finalTransfer->id
                        ];

                        Log::info("🎉 SAVE & EXIT: Hospital share item processing COMPLETED", [
                            'item_name' => $item->name,
                            'item_id' => $item->id,
                            'total_amount' => $transfer->amount,
                            'business_share' => $businessShare,
                            'contractor_share' => $contractorShare,
                            'business_transfer_id' => $finalTransfer->id,
                            'contractor_transfer_id' => isset($contractorTransfer) ? $contractorTransfer->id : 'N/A',
                            'final_suspense_balance' => $generalSuspenseAccount->fresh()->balance,
                            'final_business_balance' => $businessAccount->fresh()->balance,
                            'verification' => ($businessShare + $contractorShare) == $transfer->amount ? '✅ AMOUNTS MATCH' : '❌ AMOUNT MISMATCH'
                        ]);
                    } else {
                        // No hospital share - transfer full amount to business account
                        $finalTransfer = $this->transferMoney(
                            $generalSuspenseAccount,
                            $businessAccount,
                            $transfer->amount,
                            'suspense_to_final',
                            $invoice,
                            $item,
                            $description,
                            null,
                            'debit'
                        );

                        $transferRecords[] = [
                            'item_name' => $item ? $item->name : 'General Item',
                            'amount' => $transfer->amount,
                            'source_suspense' => $generalSuspenseAccount->name,
                            'destination' => $businessAccount->name,
                            'transfer_id' => $finalTransfer->id
                        ];

                        Log::info("✅ SAVE & EXIT: Regular item processed", [
                            'item_name' => $item ? $item->name : 'Unknown',
                            'amount_transferred' => $transfer->amount,
                            'transfer_id' => $finalTransfer->id
                        ]);
                    }

                    $finalTransfer->markMoneyMovedToFinalAccount();
                    $transfer->markMoneyMovedToFinalAccount();
                    }
                    
                    // Mark the suspense account as moved (only if we processed transfers, not fallback)
                    if ($generalSuspenseAccount->balance == 0) {
                        $generalSuspenseAccount->markAsMoved("Money transferred to Business Account via Save & Exit");
                    }

                    Log::info("✅ SAVE & EXIT: General Suspense processed", [
                        'total_amount_transferred' => $generalSuspenseAccount->balance,
                        'individual_transfers' => count($generalTransfers)
                    ]);
                }
            } else {
                Log::info("⏭️ SAVE & EXIT: SKIPPING GENERAL SUSPENSE - Package items being consumed", [
                    'general_suspense_balance' => $generalSuspenseAccount->balance,
                    'has_package_items_being_consumed' => $hasPackageItemsBeingConsumed,
                    'package_items_found' => $packageItemsFound,
                    'decision_reason' => 'Package items should not be processed in general suspense'
                ]);
            }

            // Process Kashtre Suspense Account
            Log::info("🔍 === SAVE & EXIT: CHECKING KASHTRE SUSPENSE ACCOUNT ===", [
                'kashtre_suspense_account_id' => $kashtreSuspenseAccount->id,
                'kashtre_suspense_account_name' => $kashtreSuspenseAccount->name,
                'kashtre_suspense_balance' => $kashtreSuspenseAccount->balance,
                'will_process' => $kashtreSuspenseAccount->balance > 0
            ]);
            
            if ($kashtreSuspenseAccount->balance > 0) {
                // Store the balance before transfer
                $kashtreSuspenseBalance = $kashtreSuspenseAccount->balance;
                
                Log::info("📦 === SAVE & EXIT: PROCESSING KASHTRE SUSPENSE ACCOUNT ===", [
                    'balance' => $kashtreSuspenseBalance,
                    'account_name' => $kashtreSuspenseAccount->name,
                    'account_id' => $kashtreSuspenseAccount->id,
                    'decision_reason' => 'Kashtre suspense has balance, moving to Kashtre account',
                    'destination_account_type' => 'kashtre_account'
                ]);

                $kashtreAccount = $this->getOrCreateKashtreAccount($business);
                
                // Get package tracking number for description
                // For package adjustments, look for the most recent package tracking record
                $packageTracking = \App\Models\PackageTracking::where('client_id', $client->id)
                    ->where('business_id', $business->id)
                    ->where('status', 'active')
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                $trackingNumber = $packageTracking ? $packageTracking->tracking_number : 'N/A';
                $description = "Service Fee Transfer from Invoice {$invoice->invoice_number}";
                
                $transfer = $this->transferMoney(
                    $kashtreSuspenseAccount,
                    $kashtreAccount,
                    $kashtreSuspenseBalance,
                    'suspense_to_final',
                    $invoice,
                    null,
                    $description,
                    null,
                    'debit'
                );

                $transfer->markMoneyMovedToFinalAccount();
                
                Log::info("🔍 === KASHTRE TRANSFER RECORD UPDATE ===", [
                    'transfer_id' => $transfer->id,
                    'money_moved_to_final_account' => $transfer->fresh()->money_moved_to_final_account,
                    'moved_to_final_at' => $transfer->fresh()->moved_to_final_at
                ]);
                
                // Mark the suspense account as moved
                $kashtreSuspenseAccount->markAsMoved("Money transferred to Kashtre Account via Save & Exit");
                
                Log::info("🔍 === KASHTRE SUSPENSE STATUS UPDATE ===", [
                    'account_id' => $kashtreSuspenseAccount->id,
                    'account_name' => $kashtreSuspenseAccount->name,
                    'status_before' => $kashtreSuspenseAccount->status,
                    'status_after' => $kashtreSuspenseAccount->fresh()->status,
                    'balance_after_transfer' => $kashtreSuspenseAccount->fresh()->balance
                ]);

                $transferRecords[] = [
                    'item_name' => 'Kashtre Suspense Account',
                    'amount' => $kashtreSuspenseBalance,
                    'source_suspense' => $kashtreSuspenseAccount->name,
                    'destination' => $kashtreAccount->name,
                    'transfer_id' => $transfer->id
                ];

                Log::info("✅ SAVE & EXIT: Kashtre Suspense processed", [
                    'amount_transferred' => $kashtreSuspenseBalance,
                    'transfer_id' => $transfer->id
                ]);
            } else {
                Log::info("⏭️ SAVE & EXIT: SKIPPING KASHTRE SUSPENSE - No money in account", [
                    'kashtre_suspense_balance' => $kashtreSuspenseAccount->balance,
                    'account_name' => $kashtreSuspenseAccount->name,
                    'account_id' => $kashtreSuspenseAccount->id
                ]);
            }

            // Create package sales and statement entries for package items
            // Only create package sales when there's an actual package adjustment (consumption), not when purchasing a package
            if ($hasPackageItemsBeingConsumed && !empty($items) && $invoice->package_adjustment > 0) {
                Log::info("📦 === SAVE & EXIT: CREATING PACKAGE SALES AND STATEMENTS ===", [
                    'package_items_being_consumed' => $packageItemsFound,
                    'items_count' => count($items),
                    'invoice_package_adjustment' => $invoice->package_adjustment
                ]);
                
                $this->createPackageSalesAndStatements($invoice, $items);
            } else {
                Log::info("⏭️ SAVE & EXIT: SKIPPING PACKAGE SALES CREATION", [
                    'has_package_items_being_consumed' => $hasPackageItemsBeingConsumed,
                    'items_empty' => empty($items),
                    'items_count' => count($items),
                    'package_items_found' => $packageItemsFound,
                    'non_package_items_found' => $nonPackageItemsFound,
                    'package_adjustment' => $invoice->package_adjustment,
                    'reason' => $invoice->package_adjustment > 0 ? 'Package adjustment detected' : 'No package adjustment - likely package purchase'
                ]);
            }

            // All suspense accounts have been processed
            Log::info("✅ === SAVE & EXIT: ALL SUSPENSE ACCOUNTS PROCESSED ===", [
                'package_suspense_processed' => $packageSuspenseAccount->balance > 0 && $hasPackageItemsBeingConsumed,
                'general_suspense_processed' => $generalSuspenseAccount->balance > 0,
                'kashtre_suspense_processed' => $kashtreSuspenseAccount->balance > 0,
                'total_transfers' => count($transferRecords),
                'package_items_analysis' => [
                    'package_items_found' => $packageItemsFound,
                    'non_package_items_found' => $nonPackageItemsFound,
                    'has_package_items_being_consumed' => $hasPackageItemsBeingConsumed
                ],
                'suspense_accounts_summary' => [
                    'package_suspense_balance_before' => $packageSuspenseAccount->balance,
                    'general_suspense_balance_before' => $generalSuspenseAccount->balance,
                    'kashtre_suspense_balance_before' => $kashtreSuspenseAccount->balance,
                    'total_suspense_balance_before' => $packageSuspenseAccount->balance + $generalSuspenseAccount->balance + $kashtreSuspenseAccount->balance
                ]
            ]);

            Log::info("🏁 === SAVE & EXIT: SUSPENSE TO FINAL MONEY MOVEMENT COMPLETED ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $client->id,
                'client_name' => $client->name,
                'business_id' => $business->id,
                'business_name' => $business->name,
                'transfer_records_count' => count($transferRecords),
                'package_suspense_balance_after' => $packageSuspenseAccount->fresh()->balance,
                'general_suspense_balance_after' => $generalSuspenseAccount->fresh()->balance,
                'kashtre_suspense_balance_after' => $kashtreSuspenseAccount->fresh()->balance,
                'total_suspense_balance_after' => $packageSuspenseAccount->fresh()->balance + $generalSuspenseAccount->fresh()->balance + $kashtreSuspenseAccount->fresh()->balance,
                'transfer_records' => $transferRecords,
                'timestamp' => now()->toDateTimeString()
            ]);

        } catch (Exception $e) {
            Log::error("❌ === SAVE & EXIT: SUSPENSE TO FINAL MONEY MOVEMENT FAILED ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number ?? 'Unknown',
                'client_id' => $invoice->client_id ?? 'Unknown',
                'business_id' => $invoice->business_id ?? 'Unknown',
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'items_being_processed' => $items ?? 'Not provided',
                'item_status' => $itemStatus ?? 'Not provided',
                'transfer_records_count' => count($transferRecords ?? []),
                'timestamp' => now()->toDateTimeString()
            ]);
            throw $e;
        }
        
        // Update package tracking records to reflect usage (always called for package adjustments)
        $this->updatePackageTrackingUsage($invoice, $client, $business);
    }
    
    /**
     * Update package tracking records to reflect usage when package items are consumed
     */
    private function updatePackageTrackingUsage($invoice, $client, $business)
    {
        try {
            Log::info("=== UPDATING PACKAGE TRACKING USAGE ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $client->id,
                'business_id' => $business->id
            ]);
            
            // Get the package tracking service
            $packageTrackingService = app(\App\Services\PackageTrackingService::class);
            
            // Get items from the invoice that are being consumed
            $items = $invoice->items ?? [];
            
            if (empty($items)) {
                Log::info("No items found in invoice for package tracking update");
                return;
            }
            
            // Filter out package items (we only want the actual items being consumed)
            $consumedItems = [];
            foreach ($items as $item) {
                $itemId = $item['id'] ?? $item['item_id'] ?? null;
                if (!$itemId) continue;
                
                $itemModel = \App\Models\Item::find($itemId);
                if ($itemModel && $itemModel->type !== 'package') {
                    $consumedItems[] = $item;
                }
            }
            
            if (empty($consumedItems)) {
                Log::info("No non-package items found for package tracking update");
                return;
            }
            
            Log::info("Found items to update package tracking for", [
                'consumed_items_count' => count($consumedItems),
                'items' => $consumedItems
            ]);
            
            // Use the package tracking service to update usage
            $result = $packageTrackingService->usePackageItems($invoice, $consumedItems);
            
            Log::info("Package tracking usage updated successfully", [
                'result' => $result,
                'invoice_id' => $invoice->id
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to update package tracking usage", [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);
            // Don't throw the exception - this is not critical for money movement
        }
    }

    private function resolveQuantityForDisplay($rawQuantity, array $itemData = [], $totalAmount = null, ?Item $item = null): string
    {
        $resolved = null;

        if (is_numeric($rawQuantity) && (float) $rawQuantity > 0) {
            $resolved = (float) $rawQuantity;
        }

        if ($resolved === null) {
            $unitPrice = null;

            if (isset($itemData['price']) && is_numeric($itemData['price'])) {
                $unitPrice = (float) $itemData['price'];
            } elseif ($item && is_numeric($item->default_price)) {
                $unitPrice = (float) $item->default_price;
            }

            if ($unitPrice && $unitPrice > 0 && $totalAmount !== null && is_numeric($totalAmount)) {
                $resolved = (float) $totalAmount / $unitPrice;
            }
        }

        if ($resolved === null || $resolved <= 0) {
            $resolved = 1.0;
        }

        if (floor($resolved) == $resolved) {
            return (string) (int) $resolved;
        }

        return rtrim(rtrim(number_format($resolved, 2, '.', ''), '0'), '.');
    }

    /**
     * Normalize payment method string to match PaymentMethodAccount enum values
     * Handles variations like 'mobile_money', 'mobile-money', 'Mobile Money', etc.
     */
    private function normalizePaymentMethod($paymentMethod): string
    {
        if (empty($paymentMethod)) {
            return 'cash'; // Default to cash
        }

        // Convert to lowercase and replace spaces/hyphens with underscores
        $normalized = strtolower(trim((string)$paymentMethod));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        // Map common variations to standard enum values
        $mapping = [
            'mobile_money' => 'mobile_money',
            'mobilemoney' => 'mobile_money',
            'mobile' => 'mobile_money',
            'mtn' => 'mobile_money',
            'airtel' => 'mobile_money',
            'yo' => 'mobile_money',
            'cash' => 'cash',
            'bank_transfer' => 'bank_transfer',
            'banktransfer' => 'bank_transfer',
            'bank' => 'bank_transfer',
            'transfer' => 'bank_transfer',
            'insurance' => 'insurance',
            'credit_arrangement' => 'credit_arrangement',
            'credit' => 'credit_arrangement',
            'v_card' => 'v_card',
            'virtual_card' => 'v_card',
            'p_card' => 'p_card',
            'physical_card' => 'p_card',
        ];

        return $mapping[$normalized] ?? $normalized;
    }
}
