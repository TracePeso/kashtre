<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Item;
use App\Models\BranchItemPrice;
use App\Models\BalanceHistory;
use App\Support\YoDepositGate;
use App\Support\YoExternalReference;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return view('transactions.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        return view('transactions.show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        return view('transactions.edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Show the item selection page for a client
     */
    public function itemSelection(Client $client)
    {
        // Load vendors with their third-party payer and insurance company details
        $client->load(['vendors.vendor.insuranceCompany']);
        
        // Check if user has access to this client
        $user = auth()->user();
        if ($user->business_id !== 1 && $client->business_id !== $user->business_id) {
            abort(403, 'Unauthorized access to client.');
        }

        // Don't regenerate visit_id if it was cleared/expired - only generate when creating invoice
        // $client->ensureActiveVisitId();

        // Get current branch
        $currentBranch = $user->currentBranch;

        // Fetch items that belong to this hospital/business
        // If user is from Kashtre (business_id 1), they can access items from all businesses
        if ($user->business_id == 1) {
            $items = Item::orderBy('name')->get();
        } else {
            $items = Item::where('business_id', $user->business_id)
                        ->orderBy('name')
                        ->get();
        }

        // Filter out excluded items for credit clients BEFORE calculating branch prices
        if ($client->is_credit_eligible) {
            $business = $client->business;
            // Get business-level exclusions
            $businessExcludedItems = $business->credit_excluded_items ?? [];
            // Get individual client exclusions
            $clientExcludedItems = $client->excluded_items ?? [];
            // Merge business and individual exclusions
            $excludedItems = array_unique(array_merge($businessExcludedItems, $clientExcludedItems));
            
            if (!empty($excludedItems)) {
                $items = $items->reject(function ($item) use ($excludedItems) {
                    return in_array($item->id, $excludedItems);
                });
                
                \Illuminate\Support\Facades\Log::info("=== FILTERED EXCLUDED ITEMS FOR CREDIT CLIENT ===", [
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'business_excluded_items' => $businessExcludedItems,
                    'client_excluded_items' => $clientExcludedItems,
                    'merged_excluded_item_ids' => $excludedItems,
                    'items_after_filter' => $items->count(),
                    'excluded_count' => count($excludedItems)
                ]);
            }
        }
        
        // Filter out excluded items for third-party payers BEFORE calculating branch prices
        // Check if client has an associated third-party payer
        $thirdPartyPayer = \App\Models\ThirdPartyPayer::where('client_id', $client->id)
            ->where('business_id', $user->business_id)
            ->where('status', 'active')
            ->first();
        
        if ($thirdPartyPayer) {
            $business = $client->business;
            // Get business-level exclusions
            $businessExcludedItems = $business->third_party_excluded_items ?? [];
            // Get individual payer exclusions
            $payerExcludedItems = $thirdPartyPayer->excluded_items ?? [];
            // Merge business and individual exclusions
            $excludedItems = array_unique(array_merge($businessExcludedItems, $payerExcludedItems));
            
            if (!empty($excludedItems)) {
                $items = $items->reject(function ($item) use ($excludedItems) {
                    return in_array($item->id, $excludedItems);
                });
                
                \Illuminate\Support\Facades\Log::info("=== FILTERED EXCLUDED ITEMS FOR THIRD-PARTY PAYER CLIENT ===", [
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'third_party_payer_id' => $thirdPartyPayer->id,
                    'business_excluded_items' => $businessExcludedItems,
                    'payer_excluded_items' => $payerExcludedItems,
                    'merged_excluded_item_ids' => $excludedItems,
                    'items_after_filter' => $items->count(),
                    'excluded_count' => count($excludedItems)
                ]);
            }
        }

        // Get branch-specific prices for each item
        // For each item, we need to find the appropriate branch price based on the item's business
        $branchPrices = [];
        
        // Get all branch prices for items from all businesses
        $allBranchPrices = BranchItemPrice::with('branch')
            ->get()
            ->groupBy('item_id');
        
        // For each item, find the appropriate branch price
        foreach ($allBranchPrices as $itemId => $itemBranchPrices) {
            // Find the item to determine its business
            $item = $items->where('id', $itemId)->first();
            if (!$item) continue;
            
            // If user is from Kashtre (business_id 1), they can use any branch price
            // Otherwise, use branch prices from the item's business
            if ($user->business_id == 1) {
                // For Kashtre users, prefer the current branch if it has a price, otherwise use any available price
                $preferredPrice = $itemBranchPrices->where('branch_id', $currentBranch->id)->first();
                if (!$preferredPrice) {
                    $preferredPrice = $itemBranchPrices->first();
                }
            } else {
                // For non-Kashtre users, prefer the current branch if it has a price for this item's business,
                // otherwise use any available price from the item's business
                $businessBranchPrices = $itemBranchPrices->where('branch.business_id', $item->business_id);
                $preferredPrice = $businessBranchPrices->where('branch_id', $currentBranch->id)->first();
                if (!$preferredPrice) {
                    $preferredPrice = $businessBranchPrices->first();
                }
            }
            
            if ($preferredPrice) {
                $branchPrices[$itemId] = $preferredPrice->price;
            }
        }

        // Add branch price or default price to each item
        $items->each(function ($item) use ($branchPrices, $currentBranch, $user) {
            // Ensure we have a valid default price
            $defaultPrice = $item->default_price ?? 0;
            
            // Get branch price if available
            $branchPrice = $branchPrices[$item->id] ?? null;
            
            // Set final price - prefer branch price, fallback to default price
            $item->final_price = $branchPrice ?? $defaultPrice;
            
            // Ensure final_price is never null or empty
            if (empty($item->final_price) || $item->final_price === null) {
                $item->final_price = 0;
            }
            
            // Debug logging for pricing issues
            \Illuminate\Support\Facades\Log::info("=== POS ITEM PRICING DEBUG ===", [
                'item_id' => $item->id,
                'item_name' => $item->name,
                'default_price' => $defaultPrice,
                'branch_price' => $branchPrice,
                'final_price' => $item->final_price,
                'branch_id' => $currentBranch ? $currentBranch->id : 'null',
                'branch_name' => $currentBranch ? $currentBranch->name : 'null',
                'has_branch_price' => isset($branchPrices[$item->id]),
                'business_id' => $user->business_id
            ]);
        });

        // Get ordered items for this client (same logic as service point client details)
        \Illuminate\Support\Facades\Log::info("=== POS ITEM SELECTION - FETCHING ORDERED ITEMS ===", [
            'client_id' => $client->id,
            'client_name' => $client->name,
            'business_id' => $client->business_id,
            'timestamp' => now()->toDateTimeString()
        ]);

        $clientItems = \App\Models\ServiceDeliveryQueue::where('client_id', $client->id)
            ->with(['item', 'invoice', 'startedByUser', 'servicePoint'])
            ->get();

        \Illuminate\Support\Facades\Log::info("=== POS ITEM SELECTION - ORDERED ITEMS FETCHED ===", [
            'client_id' => $client->id,
            'total_items_found' => $clientItems->count(),
            'items_by_status' => $clientItems->groupBy('status')->map->count(),
            'timestamp' => now()->toDateTimeString()
        ]);

        // Group items by status (ignore completed items)
        $pendingItems = $clientItems->where('status', 'pending');
        $partiallyDoneItems = $clientItems->where('status', 'partially_done');
        // Note: We ignore completed items, same as client details page

        // Calculate correct total amount (only pending and in-progress)
        $correctTotalAmount = $pendingItems->sum(function ($item) {
            return $item->price * $item->quantity;
        }) + $partiallyDoneItems->sum(function ($item) {
            return $item->price * $item->quantity;
        });

        \Illuminate\Support\Facades\Log::info("=== POS ITEM SELECTION - CALCULATIONS COMPLETE ===", [
            'client_id' => $client->id,
            'pending_items_count' => $pendingItems->count(),
            'partially_done_items_count' => $partiallyDoneItems->count(),
            'completed_items_ignored' => $clientItems->where('status', 'completed')->count(),
            'correct_total_amount' => $correctTotalAmount,
            'unified_component_data' => [
                'pending_items' => $pendingItems->count(),
                'partially_done_items' => $partiallyDoneItems->count(),
                'completed_items' => 0, // Always 0 - ignored
                'total_amount' => $correctTotalAmount
            ],
            'timestamp' => now()->toDateTimeString()
        ]);

        // Determine service point from the client's pending items
        // Use the service_point_id from the first pending or in-progress item
        $firstItem = $clientItems->whereIn('status', ['pending', 'partially_done'])->first();
        $servicePointId = $firstItem ? $firstItem->service_point_id : null;
        
        // If we still don't have a service point, use a default one or the user's first assigned service point
        if (!$servicePointId && $user->service_points && is_array($user->service_points) && count($user->service_points) > 0) {
            $servicePointId = $user->service_points[0];
        }
        
        // Load the service point model if we have an ID
        $servicePoint = null;
        if ($servicePointId) {
            $servicePoint = \App\Models\ServicePoint::find($servicePointId);
        }
        
        // Get available third-party payers for this business (for credit clients with insurance)
        $thirdPartyPayers = \App\Models\ThirdPartyPayer::where('business_id', $user->business_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('pos.item-selection', compact(
            'client', 
            'items', 
            'pendingItems', 
            'partiallyDoneItems', 
            'correctTotalAmount',
            'servicePoint',
            'thirdPartyPayers'
        ));
    }

    /**
     * Show payment page for deductible or co-pay
     */
    public function showPaymentResponsibilityPayment(Client $client, Request $request)
    {
        $user = auth()->user();
        if ($user->business_id !== 1 && $client->business_id !== $user->business_id) {
            abort(403, 'Unauthorized access to client.');
        }

        $type = $request->query('type', 'deductible'); // 'deductible' or 'copay'
        
        if ($type === 'deductible' && (!$client->has_deductible || !$client->deductible_amount)) {
            return redirect()->route('pos.item-selection', $client)
                ->with('error', 'This client does not have a deductible requirement.');
        }
        
        if ($type === 'copay' && !$client->copay_amount) {
            return redirect()->route('pos.item-selection', $client)
                ->with('error', 'This client does not have a co-pay requirement.');
        }

        // Includes config defaults when no MaturationPeriod row exists
        $availablePaymentMethods = \App\Models\MaturationPeriod::activePaymentMethodsForBusiness((int) $client->business_id);

        // Filter out insurance as a payment method for deductible/co-pay
        $availablePaymentMethods = array_filter($availablePaymentMethods, function($method) {
            return $method !== 'insurance';
        });
        
        // Always include cash and mobile_money for payment responsibility payments
        $defaultMethods = ['cash', 'mobile_money'];
        $availablePaymentMethods = array_unique(array_merge($defaultMethods, $availablePaymentMethods));
        
        // Sort to put cash and mobile_money first
        usort($availablePaymentMethods, function($a, $b) use ($defaultMethods) {
            $aIndex = array_search($a, $defaultMethods);
            $bIndex = array_search($b, $defaultMethods);
            
            if ($aIndex !== false && $bIndex !== false) {
                return $aIndex <=> $bIndex;
            }
            if ($aIndex !== false) return -1;
            if ($bIndex !== false) return 1;
            return strcmp($a, $b);
        });

        // Calculate amounts
        $amount = 0;
        $remaining = 0;
        $used = 0;
        
        if ($type === 'deductible') {
            $amount = $client->deductible_amount;
            
            // Calculate actual deductible used from payments
            try {
                $apiController = new \App\Http\Controllers\API\ClientController();
                $deductibleResponse = $apiController->getDeductibleUsed($client);
                if ($deductibleResponse->getStatusCode() === 200) {
                    $deductibleData = json_decode($deductibleResponse->getContent(), true);
                    if (isset($deductibleData['deductible_used'])) {
                        $used = $deductibleData['deductible_used'];
                        $remaining = $deductibleData['deductible_remaining'];
                    } else {
                        $used = 0;
                        $remaining = $amount;
                    }
                } else {
                    $used = 0;
                    $remaining = $amount;
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Error calculating deductible used in payment page', [
                    'client_id' => $client->id,
                    'error' => $e->getMessage(),
                ]);
                $used = 0;
                $remaining = $amount;
            }
        } else {
            $amount = $client->copay_amount;
            $remaining = $amount;
        }

        return view('payment-responsibility.pay', compact(
            'client',
            'type',
            'amount',
            'remaining',
            'used',
            'availablePaymentMethods'
        ));
    }

    /**
     * Process payment for deductible or co-pay
     */
    public function processPaymentResponsibilityPayment(Client $client, Request $request)
    {
        $user = auth()->user();
        if ($user->business_id !== 1 && $client->business_id !== $user->business_id) {
            abort(403, 'Unauthorized access to client.');
        }

        $validated = $request->validate([
            'type' => 'required|in:deductible,copay',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'payment_phone' => 'nullable|string|max:255',
            'payment_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $type = $validated['type'];
        $paymentAmount = $validated['amount'];

        // Validate amount
        if ($type === 'deductible') {
            if (!$client->has_deductible || !$client->deductible_amount) {
                return redirect()->back()->with('error', 'This client does not have a deductible requirement.');
            }
            // TODO: Check remaining deductible
            if ($paymentAmount > $client->deductible_amount) {
                return redirect()->back()->with('error', 'Payment amount cannot exceed deductible amount.');
            }
        } else {
            if (!$client->copay_amount) {
                return redirect()->back()->with('error', 'This client does not have a co-pay requirement.');
            }
            if ($paymentAmount > $client->copay_amount) {
                return redirect()->back()->with('error', 'Payment amount cannot exceed co-pay amount.');
            }
        }

        // Process payment
        try {
            \Illuminate\Support\Facades\DB::beginTransaction();
            
            $reference = $validated['payment_reference'] ?? YoExternalReference::make($type === 'deductible' ? 'DED' : 'COP');
            
            $transactionReference = null;
            $paymentStatus = 'pending';
            
            // Local or YO_LIVE_DEPOSITS_ENABLED=false: complete mobile money without Yo. Live Yo when enabled on server.
            $simulateMobileMoney = !YoDepositGate::useLiveYoDeposits();
            
            if ($validated['payment_method'] === 'mobile_money' && !empty($validated['payment_phone'])) {
                if ($simulateMobileMoney) {
                    $paymentStatus = 'completed';
                    $transactionReference = 'LOCAL-' . time();
                    \Illuminate\Support\Facades\Log::info('Simulated mobile money: auto-completing payment responsibility (Yo ac_deposit_funds skipped)', [
                        'client_id' => $client->id,
                        'type' => $type,
                        'amount' => $paymentAmount,
                        'reference' => $reference,
                    ]);
                } else {
                    try {
                        $yoApi = new \App\Payments\YoAPI(
                            config('payments.yo_username'),
                            config('payments.yo_password')
                        );
                        
                        $yoApi->set_instant_notification_url(config('payments.webhook_url'));
                        $yoApi->set_external_reference($reference);
                        
                        $phone = $validated['payment_phone'];
                        // Format phone number: remove + if present, ensure 256XXXXXXXXX format
                        if (str_starts_with($phone, '+')) {
                            $phone = substr($phone, 1);
                        } elseif (str_starts_with($phone, '0')) {
                            $phone = '256' . substr($phone, 1);
                        }
                        
                        $description = ucfirst($type) . ' payment for ' . $client->name;
                        if (strlen($description) > 160) {
                            $description = substr($description, 0, 157) . '...';
                        }
                        
                        \Illuminate\Support\Facades\Log::info('Initiating mobile money payment for payment responsibility', [
                            'client_id' => $client->id,
                            'type' => $type,
                            'phone' => $phone,
                            'amount' => $paymentAmount,
                            'reference' => $reference,
                        ]);
                        
                        $yoResult = $yoApi->ac_deposit_funds($phone, $paymentAmount, $description);
                        
                        \Illuminate\Support\Facades\Log::info('YoAPI response for payment responsibility', [
                            'result' => $yoResult,
                        ]);
                        
                        if (isset($yoResult['Status']) && $yoResult['Status'] === 'OK' && isset($yoResult['TransactionReference'])) {
                            $transactionReference = $yoResult['TransactionReference'];
                            $paymentStatus = 'pending'; // Will be confirmed via webhook
                            
                            \Illuminate\Support\Facades\Log::info('Mobile money payment initiated successfully', [
                                'transaction_reference' => $transactionReference,
                                'reference' => $reference,
                            ]);
                        } else {
                            $errorMessage = $yoResult['StatusMessage'] ?? 'Unknown error';
                            \Illuminate\Support\Facades\Log::error('Mobile money payment failed', [
                                'yo_result' => $yoResult,
                                'error_message' => $errorMessage,
                            ]);
                            throw new \Exception('Mobile money payment failed: ' . $errorMessage);
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Mobile money payment error', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        throw $e;
                    }
                }
            } else {
                // For cash/bank transfer, mark as completed immediately
                $paymentStatus = 'completed';
                
                \Illuminate\Support\Facades\Log::info('Cash payment processed immediately', [
                    'payment_method' => $validated['payment_method'],
                    'amount' => $paymentAmount,
                    'reference' => $reference,
                ]);
            }
            
            // 1. Process payment in Kashtre (money tracking service)
            $moneyTrackingService = app(\App\Services\MoneyTrackingService::class);
            
            $moneyTrackingService->processPaymentReceived(
                $client,
                $paymentAmount,
                $reference,
                $validated['payment_method'],
                [
                    'payment_responsibility_type' => $type,
                    'payment_phone' => $validated['payment_phone'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'transaction_reference' => $transactionReference,
                    'visit_id' => $client->visit_id, // Track visit_id for co-pay per-visit tracking
                ]
            );

            // 1.b For deductible payments, immediately move money into client's wallet
            // so that it appears on the balance statement and can be used as account balance.
            if ($type === 'deductible') {
                try {
                    $moneyTrackingService->processDeductibleTopUp(
                        $client,
                        $paymentAmount,
                        $reference,
                        [
                            'payment_method' => $validated['payment_method'],
                            'payment_responsibility_type' => $type,
                            'transaction_reference' => $transactionReference,
                        ]
                    );
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Error processing deductible top-up to client wallet', [
                        'client_id' => $client->id,
                        'amount' => $paymentAmount,
                        'reference' => $reference,
                        'error' => $e->getMessage(),
                    ]);
                    // Do not fail the whole transaction; payment_received and third-party sync already done.
                }
            }
            
            // 2. Credit insurance company's account (ThirdPartyPayer) in Kashtre
            if ($client->insurance_company_id) {
                $insuranceCompany = $client->insuranceCompany;
                if ($insuranceCompany && $insuranceCompany->third_party_business_id) {
                    // Find or create ThirdPartyPayer for this insurance company
                    $thirdPartyPayer = \App\Models\ThirdPartyPayer::where('business_id', $client->business_id)
                        ->where('insurance_company_id', $client->insurance_company_id)
                        ->where('type', 'insurance_company')
                        ->first();
                    
                    if ($thirdPartyPayer) {
                        // Record credit to insurance company's account
                        \App\Models\ThirdPartyPayerBalanceHistory::recordCredit(
                            $thirdPartyPayer,
                            $paymentAmount,
                            ucfirst($type) . ' payment from ' . $client->name,
                            $reference,
                            $validated['notes'] ?? null,
                            $validated['payment_method'],
                            $client->id,
                            null // No invoice for payment responsibility
                        );
                        
                        \Illuminate\Support\Facades\Log::info('Insurance company account credited', [
                            'third_party_payer_id' => $thirdPartyPayer->id,
                            'amount' => $paymentAmount,
                            'type' => $type,
                        ]);
                    }
                }
            }
            
            // 3. Create payment record in third-party system
            if ($client->insurance_company_id) {
                $insuranceCompany = $client->insuranceCompany;
                if ($insuranceCompany && $insuranceCompany->third_party_business_id) {
                    try {
                        $apiService = app(\App\Services\ThirdPartyApiService::class);
                        
                        // Find client in third-party system by policy number
                        $thirdPartyClientId = null;
                        if ($client->policy_number) {
                            // Try to find client by policy number in third-party system
                            $verificationResult = $apiService->verifyPolicyNumber(
                                (int)$insuranceCompany->third_party_business_id,
                                $client->policy_number
                            );
                            
                            if ($verificationResult && isset($verificationResult['data']['principal_member_id'])) {
                                $thirdPartyClientId = $verificationResult['data']['principal_member_id'];
                            } else {
                                \Illuminate\Support\Facades\Log::warning('Could not find client in third-party system', [
                                    'policy_number' => $client->policy_number,
                                    'insurance_company_id' => $insuranceCompany->third_party_business_id,
                                ]);
                            }
                        }
                        
                        // Create payment via API (even if client not found, we still create the payment)
                        $paymentData = [
                            'client_id' => $thirdPartyClientId,
                            'insurance_company_id' => (int)$insuranceCompany->third_party_business_id,
                            'payment_type' => $type === 'deductible' ? 'deductible_payment' : 'copay_payment',
                            'amount' => $paymentAmount,
                            'paid_amount' => $paymentAmount,
                            'payment_method' => $validated['payment_method'],
                            'mobile_money_number' => $validated['payment_phone'] ?? null,
                            'transaction_id' => $transactionReference,
                            'payment_reference' => $reference,
                            'payment_date' => now()->toDateString(),
                            'payment_notes' => ($validated['notes'] ?? '') . ' - ' . ucfirst($type) . ' payment from Kashtre',
                            'status' => $paymentStatus,
                        ];
                        
                        $paymentResponse = $apiService->createPaymentResponsibilityPayment($paymentData);
                        
                        if ($paymentResponse && isset($paymentResponse['success']) && $paymentResponse['success']) {
                            \Illuminate\Support\Facades\Log::info('Payment created in third-party system', [
                                'payment_id' => $paymentResponse['data']['payment']['id'] ?? null,
                                'reference' => $reference,
                                'transaction_id' => $paymentResponse['data']['transaction_id'] ?? null,
                            ]);
                        } else {
                            \Illuminate\Support\Facades\Log::warning('Failed to create payment in third-party system', [
                                'response' => $paymentResponse,
                            ]);
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Error creating payment in third-party system', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        // Don't fail the entire transaction if third-party API fails
                    }
                }
            }
            
            \Illuminate\Support\Facades\DB::commit();

            return redirect()->route('pos.item-selection', $client)
                ->with('success', ucfirst($type) . ' payment of UGX ' . number_format($paymentAmount, 2) . ' processed successfully.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            
            \Illuminate\Support\Facades\Log::error('Error processing payment responsibility payment', [
                'client_id' => $client->id,
                'type' => $type,
                'amount' => $paymentAmount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to process payment: ' . $e->getMessage())
                ->withInput();
        }
    }
}
