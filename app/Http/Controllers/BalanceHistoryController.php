<?php

namespace App\Http\Controllers;

use App\Models\BalanceHistory;
use App\Models\Client;
use App\Support\YoDepositGate;
use App\Support\YoExternalReference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BalanceHistoryController extends Controller
{
    /**
     * Display balance statement for all clients or a specific client
     */
    public function index(Request $request)
    {
        // Show all balance histories for the current business
        $businessId = Auth::user()->business_id;
        $balanceHistories = BalanceHistory::where('business_id', $businessId)
            ->with(['client', 'user', 'invoice', 'business', 'branch'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('balance-statement.index', compact('balanceHistories'));
    }

    /**
     * Show balance statement for a specific client
     */
    public function show($clientId)
    {
        $client = Client::findOrFail($clientId);
        
        // Refresh client to ensure we have the latest balance
        $client->refresh();
        
        // Credits − debits, excluding insurance informational rows (same rule as balance-statement view).
        $credits = BalanceHistory::where('client_id', $clientId)
            ->where('transaction_type', 'credit')
            ->affectingClientBalance()
            ->sum('change_amount');

        $debits = abs(BalanceHistory::where('client_id', $clientId)
            ->where('transaction_type', 'debit')
            ->affectingClientBalance()
            ->sum('change_amount'));
        
        $calculatedBalance = $credits - $debits;
        
        // Update client balance to match calculated balance
        if (abs(($client->balance ?? 0) - $calculatedBalance) >= 0.01) {
            \Log::info("Updating client balance to match balance history calculation", [
                'client_id' => $clientId,
                'current_balance' => $client->balance ?? 0,
                'calculated_balance' => $calculatedBalance,
                'credits' => $credits,
                'debits' => $debits,
            ]);
            $client->update(['balance' => $calculatedBalance]);
            $client->refresh();
        }
        
        $balanceHistories = BalanceHistory::where('client_id', $clientId)
            ->with(['user', 'invoice', 'business', 'branch', 'client.insuranceCompany'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('balance-statement.show', compact('balanceHistories', 'client'));
    }

    /**
     * Show pay back page for a credit client
     */
    public function showPayBack($clientId)
    {
        $user = Auth::user();
        
        // Check permission
        if (!in_array('Process Pay Back', $user->permissions ?? [])) {
            return redirect()->route('balance-statement.show', $clientId)
                ->with('error', 'You do not have permission to process pay back.');
        }
        
        $client = Client::findOrFail($clientId);
        
        // Verify client is credit eligible and has negative balance
        if (!$client->is_credit_eligible || ($client->balance ?? 0) >= 0) {
            return redirect()->route('balance-statement.show', $clientId)
                ->with('error', 'This client does not have outstanding amounts to pay back.');
        }
        
        // Get PP entries (service charges first, then oldest to newest)
        // Only include entries where services have been delivered (money moved from suspense to final accounts)
        // Exclude insurance informational debits and zero-change rows
        $ppEntries = BalanceHistory::where('client_id', $clientId)
            ->where('transaction_type', 'debit')
            ->affectingClientBalance()
            ->whereNotNull('invoice_id')
            ->where('change_amount', '!=', 0)
            ->with(['invoice'])
            ->get()
            ->filter(function ($entry) use ($client) {
                if (!$entry->invoice || $entry->invoice->balance_due <= 0) {
                    return false;
                }
                
                // Only include if money has moved from suspense to final accounts (services delivered)
                // Check if there are MoneyTransfer records with money_moved_to_final_account = true
                $hasDeliveredServices = \App\Models\MoneyTransfer::where('invoice_id', $entry->invoice_id)
                    ->where('transfer_type', 'suspense_to_final')
                    ->where('money_moved_to_final_account', true)
                    ->exists();
                
                return $hasDeliveredServices;
            })
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'description' => $entry->description,
                    'amount' => abs($entry->change_amount),
                    'date' => $entry->created_at->format('Y-m-d H:i'),
                    'invoice_id' => $entry->invoice_id,
                    'invoice_number' => $entry->invoice->invoice_number ?? null,
                    'is_service_charge' => stripos($entry->description, 'service fee') !== false || 
                                          stripos($entry->description, 'service charge') !== false,
                    'created_at' => $entry->created_at->timestamp,
                    'item_id' => $entry->invoice->items[0]['id'] ?? $entry->invoice->items[0]['item_id'] ?? null,
                ];
            })
            ->sortBy(function ($entry) {
                $priority = $entry['is_service_charge'] ? 0 : 1;
                return [$priority, $entry['created_at']];
            })
            ->values();

        $totalOutstanding = $ppEntries->sum('amount');

        // Includes config defaults when no MaturationPeriod row exists
        $business = $client->business;
        $availablePaymentMethods = \App\Models\MaturationPeriod::activePaymentMethodsForBusiness($business->id);
        
        // Define the order for payment methods
        $paymentMethodOrder = [
            'mobile_money' => 1,
            'v_card' => 2,
            'p_card' => 3,
            'bank_transfer' => 4,
            'cash' => 5,
        ];
        
        // Sort payment methods according to the defined order
        usort($availablePaymentMethods, function ($a, $b) use ($paymentMethodOrder) {
            $orderA = $paymentMethodOrder[$a] ?? 999;
            $orderB = $paymentMethodOrder[$b] ?? 999;
            return $orderA <=> $orderB;
        });
        
        // Payment method display names
        $paymentMethodNames = [
            'insurance' => '🛡️ Insurance',
            'credit_arrangement_institutions' => '💳 Credit Arrangement Institutions',
            'mobile_money' => '📱 Mobile Money',
            'v_card' => '💳 V Card (Virtual Card)',
            'p_card' => '💳 P Card (Physical Card)',
            'bank_transfer' => '🏦 Bank Transfer',
            'cash' => '💵 Cash',
        ];

        return view('balance-statement.pay-back', compact('client', 'ppEntries', 'totalOutstanding', 'availablePaymentMethods', 'paymentMethodNames'));
    }

    /**
     * Get balance statement as JSON for AJAX requests
     */
    public function getBalanceHistory(Request $request, $clientId)
    {
        $client = Client::findOrFail($clientId);
        
        $balanceHistories = BalanceHistory::where('client_id', $clientId)
            ->with(['user', 'invoice', 'business', 'branch', 'client.insuranceCompany'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($history) {
                $description = $history->description;
                if ($history->payment_method === 'insurance' && ($label = $history->insurancePayerDisplayName())) {
                    $description = str_replace('[Insurance]', '[' . $label . ']', $description);
                }

                return [
                    'id' => $history->id,
                    'date' => $history->created_at->format('Y-m-d H:i:s'),
                    'transaction_type' => $history->transaction_type,
                    'description' => $description,
                    'previous_balance' => number_format($history->previous_balance, 2),
                    'change_amount' => $history->getFormattedChangeAmount(),
                    'new_balance' => number_format($history->new_balance, 2),
                    'reference_number' => $history->reference_number,
                    'user_name' => $history->user ? $history->user->name : 'System',
                    'payment_method' => $history->payment_method,
                    'notes' => $history->notes,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $balanceHistories,
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'current_balance' => number_format($client->balance ?? 0, 2),
            ]
        ]);
    }

    /**
     * Get PP (Pending Payment) entries for a client
     * Only includes entries where services have been delivered (money moved from suspense to final accounts)
     * Ordered: Service charges first, then other items oldest to newest
     */
    public function getPPEntries($clientId)
    {
        $user = Auth::user();
        
        // Check permission
        if (!in_array('Process Pay Back', $user->permissions ?? [])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to process pay back.'
            ], 403);
        }
        
        $client = Client::findOrFail($clientId);
        
        // Get all debit entries from invoices with outstanding balance
        // BUT only include entries where money has moved from suspense to final accounts (services delivered)
        $ppEntries = BalanceHistory::where('client_id', $clientId)
            ->where('transaction_type', 'debit')
            ->affectingClientBalance()
            ->whereNotNull('invoice_id')
            ->where('change_amount', '!=', 0)
            ->with(['invoice'])
            ->get()
            ->filter(function ($entry) {
                if (!$entry->invoice || $entry->invoice->balance_due <= 0) {
                    return false;
                }
                
                // Only include if money has moved from suspense to final accounts (services delivered)
                // Check if there are MoneyTransfer records with money_moved_to_final_account = true
                $hasDeliveredServices = \App\Models\MoneyTransfer::where('invoice_id', $entry->invoice_id)
                    ->where('transfer_type', 'suspense_to_final')
                    ->where('money_moved_to_final_account', true)
                    ->exists();
                
                return $hasDeliveredServices;
            })
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'description' => $entry->description,
                    'amount' => abs($entry->change_amount), // Make positive
                    'date' => $entry->created_at->format('Y-m-d H:i'),
                    'invoice_id' => $entry->invoice_id,
                    'invoice_number' => $entry->invoice->invoice_number ?? null,
                    'is_service_charge' => stripos($entry->description, 'service fee') !== false || 
                                          stripos($entry->description, 'service charge') !== false,
                    'created_at' => $entry->created_at->timestamp,
                ];
            })
            ->sortBy(function ($entry) {
                // Service charges first (priority 0), then others (priority 1)
                $priority = $entry['is_service_charge'] ? 0 : 1;
                // Then by date (oldest first)
                return [$priority, $entry['created_at']];
            })
            ->values();

        return response()->json([
            'success' => true,
            'entries' => $ppEntries,
            'total_outstanding' => $ppEntries->sum('amount'),
        ]);
    }

    /**
     * Show payment summary before processing payment
     */
    public function showPaymentSummary(Request $request, $clientId)
    {
        $user = Auth::user();
        
        // Check permission
        if (!in_array('Process Pay Back', $user->permissions ?? [])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to process pay back.'
            ], 403);
        }
        
        $client = Client::findOrFail($clientId);
        $business = $client->business;
        
        $request->validate([
            'entry_ids' => 'required|array',
            'entry_ids.*' => 'exists:balance_histories,id',
            'payment_method' => 'required|string',
            'total_amount' => 'required|numeric|min:0.01',
        ]);
        
        $entryIds = $request->entry_ids;
        $paymentMethod = $request->payment_method;
        $totalAmount = $request->total_amount;
        
        // Get the selected entries
        $entries = BalanceHistory::whereIn('id', $entryIds)
            ->where('client_id', $clientId)
            ->where('transaction_type', 'debit')
            ->with(['invoice'])
            ->get();
        
        // Build items array for invoice preview
        $paymentItems = [];
        foreach ($entries as $entry) {
            $entryAmount = abs($entry->change_amount);
            $paymentItems[] = [
                'name' => $entry->description,
                'description' => $entry->description,
                'quantity' => 1,
                'price' => $entryAmount,
                'total_amount' => $entryAmount,
            ];
        }
        
        // Generate invoice number for preview
        $paymentInvoiceNumber = \App\Models\Invoice::generateInvoiceNumber($business->id, 'proforma');
        
        return response()->json([
            'success' => true,
            'summary' => [
                'invoice_number' => $paymentInvoiceNumber,
                'client_name' => $client->name,
                'client_phone' => $client->phone_number,
                'payment_phone' => $client->payment_phone_number ?? $client->phone_number,
                'items' => $paymentItems,
                'subtotal' => $totalAmount,
                'service_charge' => 0,
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod,
            ]
        ]);
    }

    /**
     * Process mobile money payment for pay-back
     * Creates pending transaction and initiates payment via YoAPI
     */
    private function processMobileMoneyPayBack($request, $client, $business, $entryIds, $totalAmount, $paymentPhone)
    {
        \DB::beginTransaction();
        try {
            // Get the selected entries
            $entries = BalanceHistory::whereIn('id', $entryIds)
                ->where('client_id', $client->id)
                ->where('transaction_type', 'debit')
                ->with(['invoice'])
                ->get();

            if ($entries->isEmpty()) {
                \DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No valid entries found to pay.'
                ], 400);
            }

            // Build items array for payment invoice
            $paymentItems = [];
            $itemDescriptions = [];
            foreach ($entries as $entry) {
                $entryAmount = abs($entry->change_amount);
                $itemDescriptions[] = $entry->description;
                $paymentItems[] = [
                    'id' => null,
                    'item_id' => null,
                    'name' => $entry->description,
                    'description' => $entry->description,
                    'quantity' => 1,
                    'price' => $entryAmount,
                    'total_amount' => $entryAmount,
                ];
            }

            // Create payment description
            $description = "Payment for PP items: " . implode(", ", array_slice($itemDescriptions, 0, 3));
            if (count($itemDescriptions) > 3) {
                $description .= " and " . (count($itemDescriptions) - 3) . " more";
            }

            // Generate invoice number
            $paymentInvoiceNumber = \App\Models\Invoice::generateInvoiceNumber($business->id, 'proforma');

            // Create payment invoice (pending status - will be confirmed when payment completes)
            $paymentInvoice = \App\Models\Invoice::create([
                'invoice_number' => $paymentInvoiceNumber,
                'client_id' => $client->id,
                'business_id' => $business->id,
                'branch_id' => $client->branch_id,
                'created_by' => Auth::id(),
                'client_name' => $client->name,
                'client_phone' => $client->phone_number,
                'payment_phone' => $paymentPhone,
                'visit_id' => $client->visit_id,
                'items' => $paymentItems,
                'subtotal' => $totalAmount,
                'package_adjustment' => 0,
                'account_balance_adjustment' => 0,
                'service_charge' => 0,
                'total_amount' => $totalAmount,
                'amount_paid' => 0,
                'balance_due' => $totalAmount,
                'payment_methods' => ['mobile_money'],
                'payment_status' => 'pending',
                'status' => 'draft', // Will be confirmed when payment completes
                'notes' => 'Mobile money payment for pending payment items - Payment pending',
            ]);

            \Log::info("Payment invoice created for mobile money PP payment", [
                'payment_invoice_id' => $paymentInvoice->id,
                'payment_invoice_number' => $paymentInvoiceNumber,
                'total_amount' => $totalAmount,
                'status' => 'draft',
            ]);

            // Local or Yo live deposits off: skip Yo ac_deposit_funds (use simulate-pay-back / cron as needed)
            $skipYoPayBack = app()->environment('local') || config('app.env') === 'local' || !YoDepositGate::useLiveYoDeposits();
            
            if ($skipYoPayBack) {
                // Create transaction record without calling YoAPI
                // The simulation command will handle completing the payment
                $externalReference = YoExternalReference::make('PPL');
                
                \Log::info('Creating local transaction record for pay-back (YoAPI skipped)', [
                    'phone' => $paymentPhone,
                    'amount' => $totalAmount,
                    'description' => $description,
                    'external_reference' => $externalReference,
                    'invoice_id' => $paymentInvoice->id,
                    'note' => 'Run "php artisan payments:simulate-pay-back" to complete this payment',
                ]);

                // Create transaction record for tracking (pending status)
                $transaction = \App\Models\Transaction::create([
                    'business_id' => $business->id,
                    'branch_id' => $client->branch_id ?? null,
                    'client_id' => $client->id,
                    'invoice_id' => $paymentInvoice->id,
                    'amount' => $totalAmount,
                    'reference' => $paymentInvoiceNumber,
                    'external_reference' => $externalReference,
                    'description' => $description,
                    'status' => 'pending',
                    'payment_status' => 'PP', // Pending Payment
                    'type' => 'debit',
                    'origin' => 'web',
                    'phone_number' => $paymentPhone,
                    'provider' => 'yo',
                    'service' => 'pp_payment',
                    'date' => now(),
                    'currency' => 'UGX',
                    'names' => $client->name,
                    'method' => 'mobile_money',
                    'transaction_for' => 'main',
                ]);

                // Store entry_ids in invoice notes
                $paymentInvoice->update([
                    'notes' => 'Mobile money payment for pending payment items - Payment pending. Entry IDs: ' . implode(',', $entryIds) . ' | LOCAL: Run "php artisan payments:simulate-pay-back" to complete',
                ]);

                \DB::commit();

                \Log::info("Local transaction record created for pay-back (simulation required)", [
                    'transaction_id' => $transaction->id,
                    'invoice_id' => $paymentInvoice->id,
                    'external_reference' => $externalReference,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment record created. Run "php artisan payments:simulate-pay-back" to complete the payment.',
                    'transaction_id' => $externalReference,
                    'invoice_id' => $paymentInvoice->id,
                    'invoice_number' => $paymentInvoiceNumber,
                    'status' => 'pending',
                    'local_mode' => true,
                ]);
            }

            // Production/Server environment: Call YoAPI
            // Format phone number for YoAPI
            $phone = $paymentPhone;
            if (str_starts_with($phone, '+')) {
                $phone = substr($phone, 1);
            } elseif (str_starts_with($phone, '0')) {
                $phone = '256' . substr($phone, 1);
            }

            // Initialize YoAPI
            $yoPayments = new \App\Payments\YoAPI(config('payments.yo_username'), config('payments.yo_password'));
            $yoPayments->set_instant_notification_url(config('payments.webhook_url', 'https://webhook.site/396126eb-cc9b-4c57-a7a9-58f43d2b7935'));
            $externalReference = YoExternalReference::make('PP');
            $yoPayments->set_external_reference($externalReference);

            \Log::info('Initiating mobile money payment for pay-back', [
                'phone' => $phone,
                'amount' => $totalAmount,
                'description' => $description,
                'external_reference' => $externalReference,
                'invoice_id' => $paymentInvoice->id,
            ]);

            // Process payment through YoAPI
            $result = $yoPayments->ac_deposit_funds($phone, $totalAmount, $description);

            \Log::info('YoAPI response for pay-back payment', ['result' => $result]);

            // Check if payment request was initiated successfully
            if (isset($result['Status']) && $result['Status'] === 'OK' && isset($result['TransactionReference'])) {
                // Create transaction record for tracking
                $transaction = \App\Models\Transaction::create([
                    'business_id' => $business->id,
                    'branch_id' => $client->branch_id ?? null,
                    'client_id' => $client->id,
                    'invoice_id' => $paymentInvoice->id,
                    'amount' => $totalAmount,
                    'reference' => $paymentInvoiceNumber,
                    'external_reference' => $result['TransactionReference'],
                    'description' => $description,
                    'status' => 'pending',
                    'payment_status' => 'PP', // Pending Payment
                    'type' => 'debit',
                    'origin' => 'web',
                    'phone_number' => $paymentPhone,
                    'provider' => 'yo',
                    'service' => 'pp_payment',
                    'date' => now(),
                    'currency' => 'UGX',
                    'names' => $client->name,
                    'method' => 'mobile_money',
                    'transaction_for' => 'main',
                ]);

                // Store entry_ids in transaction metadata (we'll use notes field or create a separate table)
                // For now, we'll link them via the invoice
                $paymentInvoice->update([
                    'notes' => 'Mobile money payment for pending payment items - Payment pending. Entry IDs: ' . implode(',', $entryIds),
                ]);

                \DB::commit();

                \Log::info("Mobile money payment initiated for pay-back", [
                    'transaction_id' => $transaction->id,
                    'invoice_id' => $paymentInvoice->id,
                    'external_reference' => $result['TransactionReference'],
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'A payment prompt has been sent to ' . $paymentPhone . '. Please complete the payment to proceed.',
                    'transaction_id' => $result['TransactionReference'],
                    'invoice_id' => $paymentInvoice->id,
                    'invoice_number' => $paymentInvoiceNumber,
                    'status' => 'pending',
                ]);
            } else {
                \DB::rollBack();
                $errorMessage = isset($result['StatusMessage']) ? "Mobile Money payment failed: {$result['StatusMessage']}" : 
                               (isset($result['ErrorMessage']) ? "Mobile Money payment failed: {$result['ErrorMessage']}" : 
                               'Mobile Money payment failed: Unknown error.');
                
                \Log::error('Mobile money payment failed for pay-back', [
                    'result' => $result,
                    'phone' => $phone,
                    'amount' => $totalAmount,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 400);
            }

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("Error processing mobile money pay-back payment", [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process payment for PP entries
     * Creates an invoice without service charge and marks items as paid in destination accounts
     */
    public function payBack(Request $request, $clientId)
    {
        $user = Auth::user();
        
        // Check permission
        if (!in_array('Process Pay Back', $user->permissions ?? [])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to process pay back.'
                ], 403);
            }
            return redirect()->route('balance-statement.show', $clientId)
                ->with('error', 'You do not have permission to process pay back.');
        }
        
        $client = Client::findOrFail($clientId);
        $business = $client->business;
        
        // Includes config defaults when no MaturationPeriod row exists
        $availablePaymentMethods = \App\Models\MaturationPeriod::activePaymentMethodsForBusiness($business->id);
        
        // Validate payment methods - check if business has any set up
        if (empty($availablePaymentMethods)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No payment methods have been set up for this business. Please contact the administrator.'
                ], 400);
            }
            return redirect()->route('balance-statement.pay-back.show', $clientId)
                ->with('error', 'No payment methods have been set up for this business. Please contact the administrator.');
        }
        
        $request->validate([
            'entry_ids' => 'required|array',
            'entry_ids.*' => 'exists:balance_histories,id',
            'payment_method' => 'required|string|in:' . implode(',', $availablePaymentMethods),
            'total_amount' => 'required|numeric|min:0.01',
            'payment_phone' => 'nullable|string', // For mobile money payments
        ]);
        $entryIds = $request->entry_ids;
        $paymentMethod = $request->payment_method;
        $totalAmount = $request->total_amount;
        $paymentPhone = $request->payment_phone ?? $client->payment_phone_number ?? $client->phone_number;
        
        // Handle mobile money payment differently
        if ($paymentMethod === 'mobile_money') {
            return $this->processMobileMoneyPayBack($request, $client, $business, $entryIds, $totalAmount, $paymentPhone);
        }

        \DB::beginTransaction();
        try {
            // Get the selected entries with invoice and item details
            $entries = BalanceHistory::whereIn('id', $entryIds)
                ->where('client_id', $clientId)
                ->where('transaction_type', 'debit')
                ->with(['invoice'])
                ->get();

            if ($entries->isEmpty()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No valid entries found to pay.'
                    ], 400);
                }
                return redirect()->route('balance-statement.pay-back.show', $clientId)
                    ->with('error', 'No valid entries found to pay.');
            }

            // Build items array for payment invoice (without service charge)
            $paymentItems = [];
            $itemDescriptions = [];
            $invoicesToUpdate = [];
            $paidAmountsByInvoice = [];

            foreach ($entries as $entry) {
                $entryAmount = abs($entry->change_amount);
                $itemDescriptions[] = $entry->description;
                
                // Add to payment items
                $paymentItems[] = [
                    'id' => null, // Payment item, not a product
                    'item_id' => null,
                    'name' => $entry->description,
                    'description' => $entry->description,
                    'quantity' => 1,
                    'price' => $entryAmount,
                    'total_amount' => $entryAmount,
                ];

                // Track invoice payments
                if ($entry->invoice) {
                    $invoiceId = $entry->invoice->id;
                    if (!isset($paidAmountsByInvoice[$invoiceId])) {
                        $paidAmountsByInvoice[$invoiceId] = 0;
                        $invoicesToUpdate[$invoiceId] = $entry->invoice;
                    }
                    $paidAmountsByInvoice[$invoiceId] += $entryAmount;
                }
            }

            // Create payment description
            $description = "Payment for PP items: " . implode(", ", array_slice($itemDescriptions, 0, 3));
            if (count($itemDescriptions) > 3) {
                $description .= " and " . (count($itemDescriptions) - 3) . " more";
            }

            // Generate invoice number for payment invoice
            $paymentInvoiceNumber = \App\Models\Invoice::generateInvoiceNumber($business->id, 'proforma');

            // Create payment invoice (NO SERVICE CHARGE)
            $paymentInvoice = \App\Models\Invoice::create([
                'invoice_number' => $paymentInvoiceNumber,
                'client_id' => $clientId,
                'business_id' => $business->id,
                'branch_id' => $client->branch_id,
                'created_by' => $user->id,
                'client_name' => $client->name,
                'client_phone' => $client->phone_number,
                'payment_phone' => $client->payment_phone_number,
                'visit_id' => $client->visit_id,
                'items' => $paymentItems,
                'subtotal' => $totalAmount,
                'package_adjustment' => 0,
                'account_balance_adjustment' => 0,
                'service_charge' => 0, // NO SERVICE CHARGE for PP payments
                'total_amount' => $totalAmount,
                'amount_paid' => $totalAmount,
                'balance_due' => 0,
                'payment_methods' => [$paymentMethod],
                'payment_status' => 'paid',
                'status' => 'confirmed',
                'notes' => 'Payment for pending payment items - No service charge applied',
            ]);

            \Log::info("Payment invoice created for PP payment", [
                'payment_invoice_id' => $paymentInvoice->id,
                'payment_invoice_number' => $paymentInvoiceNumber,
                'total_amount' => $totalAmount,
                'service_charge' => 0,
            ]);

            // Debit from source account (payment method account)
            $normalizedPaymentMethod = str_replace('_', ' ', $paymentMethod);
            $sourceAccount = \App\Models\PaymentMethodAccount::forBusiness($business->id)
                ->forPaymentMethod($normalizedPaymentMethod)
                ->active()
                ->first();

            if ($sourceAccount) {
                $sourceAccount->debit(
                    $totalAmount,
                    $paymentInvoiceNumber,
                    $description,
                    $client->id,
                    $paymentInvoice->id,
                    ['type' => 'pp_payment', 'entry_ids' => $entryIds]
                );
                \Log::info("Debited payment method account for PP payment", [
                    'account_id' => $sourceAccount->id,
                    'amount' => $totalAmount,
                ]);
            } else {
                \Log::warning("Payment method account not found for PP payment", [
                    'business_id' => $business->id,
                    'payment_method' => $normalizedPaymentMethod,
                ]);
            }

            // Credit client account (reduces debt)
            \App\Models\BalanceHistory::recordCredit(
                $client,
                $totalAmount,
                $description,
                $paymentInvoiceNumber,
                "Payment for pending payment items - Invoice #{$paymentInvoiceNumber}",
                $paymentMethod,
                $paymentInvoice->id,
                'paid' // Payment status is 'paid' since payment is received
            );

            // Update client balance (reduce negative balance)
            $client->increment('balance', $totalAmount);

            // Process each entry: mark items as paid and move money to destination accounts
            $moneyTrackingService = app(\App\Services\MoneyTrackingService::class);
            
            foreach ($entries as $entry) {
                $entryAmount = abs($entry->change_amount);
                $originalInvoice = $entry->invoice;
                
                if (!$originalInvoice) continue;

                // Find the original item from the invoice
                $itemFound = false;
                foreach ($originalInvoice->items ?? [] as $invoiceItem) {
                    $itemId = $invoiceItem['id'] ?? $invoiceItem['item_id'] ?? null;
                    if (!$itemId) continue;
                    
                    $item = \App\Models\Item::find($itemId);
                    if (!$item) continue;

                    // Check if this entry matches this item
                    $itemDescription = $item->name;
                    $quantity = $invoiceItem['quantity'] ?? 1;
                    $itemTotalAmount = $invoiceItem['total_amount'] ?? ($invoiceItem['price'] ?? 0) * $quantity;
                    
                    // Match by description or amount
                    if (stripos($entry->description, $itemDescription) !== false || 
                        abs($itemTotalAmount - $entryAmount) < 0.01) {
                        
                        // Mark item as paid in destination accounts (entity/contractor/kashtre)
                        // Transfer money from payment method account to destination accounts
                        $this->markItemAsPaid(
                            $moneyTrackingService,
                            $item,
                            $entryAmount,
                            $originalInvoice,
                            $sourceAccount,
                            $paymentInvoiceNumber,
                            $description
                        );
                        
                        $itemFound = true;
                        break;
                    }
                }

                // If item not found but it's a service charge, handle it
                if (!$itemFound && (stripos($entry->description, 'service fee') !== false || 
                                    stripos($entry->description, 'service charge') !== false)) {
                    // Service charge goes to Kashtre account
                    $kashtreAccount = $moneyTrackingService->getOrCreateKashtreAccount();
                    if ($sourceAccount && $kashtreAccount) {
                        $moneyTrackingService->transferMoney(
                            $sourceAccount,
                            $kashtreAccount,
                            $entryAmount,
                            'pp_payment',
                            $paymentInvoice,
                            null,
                            "Service charge payment: {$entry->description}"
                        );
                    }
                }
            }

            // Update payment_status and payment_method for the selected BalanceHistory entries
            BalanceHistory::whereIn('id', $entryIds)
                ->where('client_id', $clientId)
                ->update([
                    'payment_status' => 'paid',
                    'payment_method' => $paymentMethod,
                ]);

            \Log::info("Updated BalanceHistory entries payment status", [
                'entry_ids' => $entryIds,
                'payment_status' => 'paid',
                'payment_method' => $paymentMethod,
            ]);

            // Update corresponding BusinessBalanceHistory records
            // These are the records created when money moved from suspense to final accounts
            // They would have payment_status = 'pending_payment' for credit clients
            foreach ($entries as $entry) {
                if (!$entry->invoice) continue;
                
                $entryAmount = abs($entry->change_amount);
                $isServiceCharge = stripos($entry->description, 'service fee') !== false || 
                                   stripos($entry->description, 'service charge') !== false;
                
                if ($isServiceCharge) {
                    // Service charge goes to Kashtre (business_id = 1)
                    // Update Kashtre balance history records
                    $kashtreBalanceHistories = \App\Models\BusinessBalanceHistory::where('business_id', 1)
                        ->where('reference_type', 'invoice')
                        ->where('reference_id', $entry->invoice_id)
                        ->where('type', 'credit')
                        ->where(function($query) use ($entryAmount) {
                            // Match by amount (with small tolerance for floating point)
                            $query->whereBetween('amount', [$entryAmount - 0.01, $entryAmount + 0.01]);
                        })
                        ->where(function($query) {
                            // Only update pending payments
                            $query->where('payment_status', 'pending_payment')
                                  ->orWhereNull('payment_status');
                        })
                        ->get();
                    
                    foreach ($kashtreBalanceHistories as $kbh) {
                        $kbh->update([
                            'payment_status' => 'paid',
                            'payment_method' => $paymentMethod,
                        ]);
                    }
                } else {
                    // Regular items go to business account
                    // Find BusinessBalanceHistory records for this invoice that match the amount
                    $businessBalanceHistories = \App\Models\BusinessBalanceHistory::where('business_id', $business->id)
                        ->where('reference_type', 'invoice')
                        ->where('reference_id', $entry->invoice_id)
                        ->where('type', 'credit')
                        ->where(function($query) use ($entryAmount) {
                            // Match by amount (with small tolerance for floating point)
                            $query->whereBetween('amount', [$entryAmount - 0.01, $entryAmount + 0.01]);
                        })
                        ->where(function($query) {
                            // Only update pending payments
                            $query->where('payment_status', 'pending_payment')
                                  ->orWhereNull('payment_status');
                        })
                        ->get();
                    
                    foreach ($businessBalanceHistories as $bbh) {
                        $bbh->update([
                            'payment_status' => 'paid',
                            'payment_method' => $paymentMethod,
                        ]);
                    }
                }
            }

            \Log::info("Updated BusinessBalanceHistory entries payment status", [
                'invoice_ids' => array_keys($invoicesToUpdate),
                'payment_status' => 'paid',
                'payment_method' => $paymentMethod,
            ]);

            // Update original invoices and accounts receivable
            foreach ($invoicesToUpdate as $invoiceId => $invoice) {
                $paidAmount = $paidAmountsByInvoice[$invoiceId];
                
                // Update invoice
                $invoice->increment('amount_paid', $paidAmount);
                $invoice->decrement('balance_due', $paidAmount);
                
                // If invoice is fully paid, update payment status
                $invoice->refresh();
                if ($invoice->balance_due <= 0) {
                    $invoice->update(['payment_status' => 'paid']);
                } elseif ($invoice->amount_paid > 0) {
                    $invoice->update(['payment_status' => 'partial']);
                }

                // Update accounts receivable
                $accountsReceivable = \App\Models\AccountsReceivable::where('invoice_id', $invoiceId)
                    ->where('client_id', $clientId)
                    ->first();

                if ($accountsReceivable) {
                    // Create transaction record for the payment
                    $transaction = \App\Models\Transaction::create([
                        'business_id' => $business->id,
                        'branch_id' => $client->branch_id ?? null,
                        'client_id' => $clientId,
                        'invoice_id' => $invoiceId,
                        'amount' => $paidAmount,
                        'reference' => $paymentInvoiceNumber . '-' . $invoiceId,
                        'description' => $description,
                        'status' => 'completed',
                        'payment_status' => 'Paid',
                        'type' => 'credit',
                        'origin' => 'web',
                        'phone_number' => $client->phone_number ?? null,
                        'provider' => $paymentMethod === 'mobile_money' ? 'yo' : $paymentMethod,
                        'service' => 'pp_payment',
                        'date' => now(),
                        'currency' => 'UGX',
                        'names' => $client->name,
                        'method' => $paymentMethod,
                        'transaction_for' => 'main',
                    ]);
                    
                    $accountsReceivable->recordPayment($paidAmount, $transaction->id);
                }
            }

            \DB::commit();

            \Log::info("PP Payment processed successfully", [
                'client_id' => $clientId,
                'payment_invoice_id' => $paymentInvoice->id,
                'payment_invoice_number' => $paymentInvoiceNumber,
                'entry_ids' => $entryIds,
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Payment of UGX " . number_format($totalAmount, 2) . " processed successfully.",
                    'invoice_id' => $paymentInvoice->id,
                    'invoice_number' => $paymentInvoiceNumber,
                ]);
            }

            return redirect()->route('balance-statement.show', $clientId)
                ->with('success', "Payment of UGX " . number_format($totalAmount, 2) . " processed successfully. Invoice #{$paymentInvoiceNumber} created.");

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("Error processing PP payment", [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to process payment: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->route('balance-statement.pay-back.show', $clientId)
                ->with('error', 'Failed to process payment: ' . $e->getMessage());
        }
    }

    /**
     * Mark item as paid and transfer money to destination accounts
     */
    private function markItemAsPaid($moneyTrackingService, $item, $amount, $originalInvoice, $sourceAccount, $reference, $description)
    {
        $business = $originalInvoice->business;
        
        // Determine destination account based on item ownership
        if ($item->contractor_account_id) {
            // Contractor item - split between contractor and business
            $contractor = \App\Models\ContractorProfile::find($item->contractor_account_id);
            if ($contractor) {
                $contractorAccount = $moneyTrackingService->getOrCreateContractorAccount($contractor);
                $contractorShare = ($amount * (100 - $item->hospital_share)) / 100;
                $businessShare = $amount - $contractorShare;
                
                // Transfer contractor share
                if ($contractorShare > 0 && $sourceAccount && $contractorAccount) {
                    $moneyTrackingService->transferMoney(
                        $sourceAccount,
                        $contractorAccount,
                        $contractorShare,
                        'pp_payment',
                        $originalInvoice,
                        $item,
                        "PP Payment - Contractor share: {$item->name}"
                    );
                    
                    // Update contractor balance
                    $contractor->increment('account_balance', $contractorShare);
                }
                
                // Transfer business share
                if ($businessShare > 0) {
                    $businessAccount = $moneyTrackingService->getOrCreateBusinessAccount($business);
                    if ($sourceAccount && $businessAccount) {
                        $moneyTrackingService->transferMoney(
                            $sourceAccount,
                            $businessAccount,
                            $businessShare,
                            'pp_payment',
                            $originalInvoice,
                            $item,
                            "PP Payment - Business share: {$item->name}"
                        );
                    }
                }
            }
        } else {
            // Business item - transfer to business account
            $businessAccount = $moneyTrackingService->getOrCreateBusinessAccount($business);
            if ($sourceAccount && $businessAccount) {
                $moneyTrackingService->transferMoney(
                    $sourceAccount,
                    $businessAccount,
                    $amount,
                    'pp_payment',
                    $originalInvoice,
                    $item,
                    "PP Payment: {$item->name}"
                );
            }
        }
        
        // Mark service delivery queue items as completed if they exist
        $serviceDeliveryQueues = \App\Models\ServiceDeliveryQueue::where('invoice_id', $originalInvoice->id)
            ->where('item_id', $item->id)
            ->where('status', '!=', 'completed')
            ->get();
            
        foreach ($serviceDeliveryQueues as $queue) {
            $queue->markAsCompleted(auth()->id());
        }
    }




}
