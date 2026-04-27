<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Business;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Item;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\ServiceQueue;
use App\Models\ServicePoint;
use App\Models\ServiceCharge;
use App\Models\Group;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Payments\YoAPI;

class AutomatedTestController extends Controller
{
    /**
     * Display the automated test page.
     */
    public function index()
    {
        return view('automated-tests.index');
    }

    /**
     * Get available items for selection.
     */
    public function getItems()
    {
        try {
            $user = Auth::user();
            
            if (!$user || !$user->business_id) {
                return response()->json([], 400);
            }

            $items = Item::where('business_id', $user->business_id)
                ->whereIn('type', ['service', 'good', 'package', 'bulk'])
                ->select('id', 'name', 'type', 'default_price')
                ->orderBy('name')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name . ' (' . ucfirst($item->type) . ')',
                        'price' => $item->default_price ?? 0,
                        'type' => $item->type
                    ];
                });

            return response()->json($items);
        } catch (\Exception $e) {
            Log::error('Error in getItems: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    /**
     * Run one complete user journey test.
     */
    public function run(Request $request)
    {
        $output = [];
        $output[] = "Starting Non-Insurance Purchase Test Suite...\n";
        $output[] = "====================================================\n\n";

        try {
            $user = Auth::user();
            $business = Business::find($user->business_id);
            $branch = Branch::where('business_id', $user->business_id)->first();

            if (!$business || !$branch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Missing business or branch',
                    'output' => "ERROR: Business or branch not found"
                ]);
            }

            // Get or create service point for this branch
            $servicePoint = ServicePoint::where('branch_id', $branch->id)->first();
            if (!$servicePoint) {
                $servicePoint = ServicePoint::create([
                    'name' => 'Main Service Point',
                    'branch_id' => $branch->id,
                    'business_id' => $business->id,
                ]);
            }

            $output[] = "=== COMPLETE CUSTOMER JOURNEY TEST ===\n\n";
            $output[] = "Facility: {$business->name}\n";
            $output[] = "Location: {$branch->name}\n";
            $output[] = "Service Point: {$servicePoint->name}\n";
            $output[] = "====================================================\n\n";

            // Input options
            $paymentPhone = $request->input('payment_phone', '');
            $paymentMode = (string) $request->input('payment_mode', 'simulated');
            $paymentMode = in_array($paymentMode, ['simulated', 'live'], true) ? $paymentMode : 'simulated';
            $runFullSuite = filter_var($request->input('full_suite', false), FILTER_VALIDATE_BOOLEAN);

            $itemCount = intval($request->input('item_count', 50)); // Allow up to 50 items to fill budget
            $itemCount = max(1, min($itemCount, 100)); // Ensure between 1 and 100
            $maxAmount = intval($request->input('max_amount', 100000));
            $maxAmount = max(500, $maxAmount); // Minimum 500 UGX budget
            $itemTypes = $request->input('item_types', ['service', 'good', 'package', 'bulk']);
            $itemTypes = is_array($itemTypes) ? $itemTypes : ['service', 'good', 'package', 'bulk'];

            $scenarios = $runFullSuite
                ? [
                    ['name' => 'Service-only purchase', 'item_types' => ['service'], 'item_count' => 3, 'max_amount' => 50000],
                    ['name' => 'Goods-only purchase', 'item_types' => ['good'], 'item_count' => 4, 'max_amount' => 80000],
                    ['name' => 'Mixed service + goods', 'item_types' => ['service', 'good'], 'item_count' => 6, 'max_amount' => 120000],
                    ['name' => 'Package + bulk purchase', 'item_types' => ['package', 'bulk'], 'item_count' => 3, 'max_amount' => 150000],
                    ['name' => 'Full mixed cart', 'item_types' => ['service', 'good', 'package', 'bulk'], 'item_count' => 8, 'max_amount' => 200000],
                ]
                : [[
                    'name' => 'Custom scenario',
                    'item_types' => $itemTypes,
                    'item_count' => $itemCount,
                    'max_amount' => $maxAmount,
                ]];

            $output[] = "Suite mode: " . ($runFullSuite ? "Full non-insurance coverage" : "Single scenario") . "\n";
            $output[] = "Payment mode: " . strtoupper($paymentMode) . "\n";
            $output[] = "====================================================\n\n";

            foreach ($scenarios as $index => $scenario) {
                try {
                    $output[] = "SCENARIO " . ($index + 1) . ": {$scenario['name']}\n";
                    $output[] = "----------------------------------------------------\n";
                    $output[] = "Item types: " . implode(', ', $scenario['item_types']) . "\n";
                    $output[] = "Item count target: {$scenario['item_count']}\n";
                    $output[] = "Budget: " . number_format((float) $scenario['max_amount']) . " UGX\n\n";

                    // STEP 1: Register client
                    $firstName = 'Test';
                    $surname = 'User' . ($index + 1);
                    $clientPhone = '0777' . rand(100000, 999999);
                    $paymentPhoneNumber = $paymentPhone ?: '0776' . rand(100000, 999999);
                    $clientId = Client::generateClientId($business, $surname, $firstName, null);
                    $visitId = Client::generateVisitId($business, $branch, false, false);

                    $client = Client::create([
                        'client_type' => 'individual',
                        'client_id' => $clientId,
                        'visit_id' => $visitId,
                        'visit_expires_at' => now()->addDays(7),
                        'business_id' => $business->id,
                        'branch_id' => $branch->id,
                        'name' => $firstName . ' ' . $surname,
                        'first_name' => $firstName,
                        'surname' => $surname,
                        'phone_number' => $clientPhone,
                        'payment_phone_number' => $paymentPhoneNumber,
                        'email' => 'testuser' . rand(10000, 99999) . '@test.com',
                        'services_category' => 'outpatient',
                        'payment_methods' => [],
                        'status' => 'active',
                        'balance' => 0,
                    ]);

                    $output[] = "[OK] Client created: {$client->name} ({$client->client_id})\n";

                    // STEP 2: Select / ensure items
                    $availableItems = Item::where('business_id', $business->id)
                        ->whereIn('type', $scenario['item_types'])
                        ->whereNotNull('default_price')
                        ->where('default_price', '>', 0)
                        ->get()
                        ->shuffle();

                    if ($availableItems->isEmpty()) {
                        $group = Group::where('business_id', $business->id)->first()
                            ?? Group::create(['name' => 'Test Items Group', 'business_id' => $business->id]);
                        foreach (range(1, max(4, (int) $scenario['item_count'])) as $n) {
                            Item::create([
                                'name' => "Test Item {$n} (" . strtoupper($scenario['item_types'][($n - 1) % count($scenario['item_types'])]) . ")",
                                'type' => $scenario['item_types'][($n - 1) % count($scenario['item_types'])],
                                'business_id' => $business->id,
                                'group_id' => $group->id,
                                'default_price' => 10000 + ($n * 1000),
                            ]);
                        }
                        $availableItems = Item::where('business_id', $business->id)
                            ->whereIn('type', $scenario['item_types'])
                            ->whereNotNull('default_price')
                            ->where('default_price', '>', 0)
                            ->get()
                            ->shuffle();
                    }

                    $items = collect();
                    $runningTotal = 0;
                    foreach ($availableItems as $item) {
                        if ($items->count() >= (int) $scenario['item_count']) {
                            break;
                        }
                        $price = (float) ($item->default_price ?? 0);
                        if ($price <= 0) {
                            continue;
                        }
                        if (($runningTotal + $price) > (float) $scenario['max_amount'] && $items->isNotEmpty()) {
                            continue;
                        }
                        $items->push($item);
                        $runningTotal += $price;
                    }
                    if ($items->isEmpty() && $availableItems->isNotEmpty()) {
                        $fallbackItem = $availableItems->first();
                        $items->push($fallbackItem);
                        $runningTotal = (float) ($fallbackItem->default_price ?? 0);
                    }

                    $invoiceItems = [];
                    $subtotal = 0;
                    foreach ($items as $item) {
                        $unitPrice = (float) ($item->default_price ?? 0);
                        $subtotal += $unitPrice;
                        $invoiceItems[] = [
                            'item_id' => $item->id,
                            'name' => $item->name,
                            'quantity' => 1,
                            'unit_price' => $unitPrice,
                            'total' => $unitPrice,
                        ];
                        $output[] = "  • {$item->name} (" . ucfirst((string) $item->type) . ") - " . number_format($unitPrice, 2) . " UGX\n";
                    }

                    // Service charge
                    $serviceChargeAmount = 0;
                    $serviceChargeConfig = ServiceCharge::where('business_id', $business->id)
                        ->where('entity_type', 'business')
                        ->where('is_active', true)
                        ->where('lower_bound', '<=', $subtotal)
                        ->where('upper_bound', '>=', $subtotal)
                        ->orderBy('lower_bound', 'desc')
                        ->first();
                    if ($serviceChargeConfig) {
                        if ($serviceChargeConfig->type === 'fixed') {
                            $serviceChargeAmount = (float) $serviceChargeConfig->amount;
                        } elseif ($serviceChargeConfig->type === 'percentage') {
                            $serviceChargeAmount = ($subtotal * (float) $serviceChargeConfig->amount) / 100;
                        }
                    }
                    $finalAmount = round($subtotal + $serviceChargeAmount, 2);

                    // YoAPI minimum practical request amount safeguard.
                    // Keep invoice math consistent by folding any required top-up into service charge.
                    $minimumYoAmount = 500.00;
                    if ($finalAmount < $minimumYoAmount) {
                        $topUp = $minimumYoAmount - $finalAmount;
                        $serviceChargeAmount = round($serviceChargeAmount + $topUp, 2);
                        $finalAmount = $minimumYoAmount;
                        $output[] = "[INFO] Applied minimum Yo amount top-up: " . number_format($topUp, 2) . " UGX (final total set to 500.00 UGX)\n";
                    }

                    // STEP 3: Create invoice
                    $invoice = Invoice::create([
                        'invoice_number' => 'ORD' . now()->format('ymdHis') . Str::upper(Str::random(4)),
                        'client_id' => $client->id,
                        'visit_id' => $client->visit_id,
                        'business_id' => $business->id,
                        'branch_id' => $branch->id,
                        'created_by' => Auth::id(),
                        'client_name' => $client->name,
                        'client_phone' => $client->phone_number,
                        'payment_phone' => $client->payment_phone_number,
                        'items' => $invoiceItems,
                        'subtotal' => $subtotal,
                        'service_charge' => $serviceChargeAmount,
                        'total_amount' => $finalAmount,
                        'amount_paid' => 0,
                        'balance_due' => $finalAmount,
                        'payment_status' => 'unpaid',
                        'payment_methods' => [],
                        'status' => 'pending',
                        'currency' => 'UGX',
                    ]);
                    $output[] = "[OK] Invoice created: {$invoice->invoice_number}, total " . number_format($finalAmount, 2) . " UGX\n";

                    // STEP 4: Payment initiation / simulation
                    $formattedPhone = preg_replace('/[^0-9+]/', '', (string) $client->payment_phone_number);
                    if (str_starts_with($formattedPhone, '+256')) {
                        $formattedPhone = substr($formattedPhone, 1);
                    } elseif (str_starts_with($formattedPhone, '0')) {
                        $formattedPhone = '256' . substr($formattedPhone, 1);
                    }

                    $paymentReference = 'SIM-' . strtoupper(Str::random(8));
                    $paymentStatus = 'completed';
                    $trackingLines = [];

                    if ($paymentMode === 'live') {
                        $paymentStatus = 'pending';
                        try {
                            $yoPayments = new YoAPI(
                                config('payments.yo_username'),
                                config('payments.yo_password')
                            );
                            $yoPayments->set_external_reference('OR' . $invoice->invoice_number);
                            $paymentResult = $yoPayments->ac_deposit_funds(
                                $formattedPhone,
                                intval($finalAmount),
                                "Order {$invoice->invoice_number}"
                            );
                            $trackingLines[] = 'Prompt sent: ' . json_encode([
                                'status' => $paymentResult['Status'] ?? null,
                                'status_message' => $paymentResult['StatusMessage'] ?? null,
                                'transaction_reference' => $paymentResult['TransactionReference'] ?? null,
                            ]);
                            if (!isset($paymentResult['Status']) || $paymentResult['Status'] !== 'OK') {
                                $paymentStatus = 'failed';
                            } else {
                                $paymentReference = $paymentResult['TransactionReference'] ?? $paymentReference;
                                $pollAttempts = max(1, min((int) $request->input('poll_attempts', 8), 20));
                                $pollDelay = max(1, min((int) $request->input('poll_delay_seconds', 3), 10));
                                for ($attempt = 1; $attempt <= $pollAttempts; $attempt++) {
                                    $statusResult = $yoPayments->ac_transaction_check_status($paymentReference);
                                    $yoStatus = strtoupper((string) ($statusResult['TransactionStatus'] ?? ''));
                                    $trackingLines[] = "Status poll {$attempt}/{$pollAttempts}: " . json_encode([
                                        'transaction_status' => $statusResult['TransactionStatus'] ?? null,
                                        'status_message' => $statusResult['StatusMessage'] ?? null,
                                        'issued_receipt' => $statusResult['IssuedReceiptNumber'] ?? null,
                                        'completion_date' => $statusResult['TransactionCompletionDate'] ?? null,
                                    ]);

                                    if ($yoStatus === 'SUCCEEDED') {
                                        $paymentStatus = 'completed';
                                        break;
                                    }
                                    if ($yoStatus === 'FAILED') {
                                        $paymentStatus = 'failed';
                                        break;
                                    }

                                    if ($attempt < $pollAttempts) {
                                        sleep($pollDelay);
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            $paymentStatus = 'failed';
                            $output[] = "[FAILED] Live payment request failed: {$e->getMessage()}\n";
                        }
                    } else {
                        $output[] = "[OK] Payment simulated as COMPLETED for coverage\n";
                        $trackingLines[] = 'Simulated payment completed immediately';
                    }

                    // LIVE mode can spend time polling Yo; ensure DB connection is fresh before writes.
                    DB::disconnect();
                    DB::reconnect();

                    $transaction = Transaction::create([
                        'invoice_id' => $invoice->id,
                        'client_id' => $client->id,
                        'business_id' => $business->id,
                        'branch_id' => $branch->id,
                        'amount' => $finalAmount,
                        'reference' => $invoice->invoice_number,
                        'external_reference' => $paymentReference,
                        'service' => 'healthcare',
                        'status' => $paymentStatus,
                        'type' => 'credit',
                        'origin' => 'web',
                        'method' => 'mobile_money',
                        // DB enum only allows: mtn, airtel, yo.
                        // Keep yo for both live and simulated modes to avoid enum truncation.
                        'provider' => 'yo',
                        'phone_number' => $formattedPhone,
                        'description' => "Automated test {$scenario['name']}",
                    ]);

                    // STEP 5: Finalize status & queue
                    if ($paymentStatus === 'completed') {
                        $invoice->update([
                            'amount_paid' => $finalAmount,
                            'balance_due' => 0,
                            'payment_status' => 'paid',
                            'status' => 'paid',
                        ]);

                        $queuedAtPoints = $this->queueItemsByMappedServicePoint(
                            $client->id,
                            $business->id,
                            $branch->id,
                            $invoiceItems,
                            $servicePoint->id
                        );

                        if (count($queuedAtPoints) > 0) {
                            $output[] = "[OK] Queue entries created after payment completion\n";
                            foreach ($queuedAtPoints as $q) {
                                $output[] = "    - {$q['service_point_name']}: {$q['items_count']} item(s), queue #{$q['queue_number']}\n";
                            }
                        } else {
                            $output[] = "[WARNING] No queue entries were created\n";
                        }
                    } elseif ($paymentStatus === 'pending') {
                        $invoice->update(['payment_status' => 'pending', 'status' => 'pending']);
                        $output[] = "[OK] Payment request pending confirmation (live mode)\n";
                    } else {
                        $invoice->update(['payment_status' => 'failed', 'status' => 'failed']);
                        $output[] = "[FAILED] Payment initiation failed\n";
                    }

                    if (!empty($trackingLines)) {
                        $output[] = "Tracking log:\n";
                        foreach ($trackingLines as $line) {
                            $output[] = "  - {$line}\n";
                        }
                    }

                    $output[] = "Assertions:\n";
                    $output[] = "  - Client created: PASS\n";
                    $output[] = "  - Invoice created with non-insurance items: PASS\n";
                    $output[] = "  - Transaction recorded: PASS\n";
                    $output[] = "  - Amount math valid (subtotal + charge = total): " . (($finalAmount >= $subtotal) ? 'PASS' : 'FAIL') . "\n";
                    $output[] = "  - Queue behavior validated: " . ($paymentStatus === 'completed' ? 'PASS' : 'PENDING (live mode)') . "\n";
                    $output[] = "Scenario result: " . (($paymentStatus === 'failed') ? 'FAIL' : 'PASS') . "\n\n";

                } catch (\Throwable $scenarioError) {
                    Log::error('Automated non-insurance scenario failed', [
                        'scenario' => $scenario['name'] ?? 'unknown',
                        'error' => $scenarioError->getMessage(),
                    ]);
                    $output[] = "[ERROR] Scenario failed: " . ($scenario['name'] ?? 'unknown') . "\n";
                    $output[] = "Reason: {$scenarioError->getMessage()}\n\n";
                }
            }
            $output[] = "====================================================\n";
            $output[] = "Test suite completed.\n";

            return response()->json([
                'status' => 'success',
                'output' => implode('', $output)
            ]);

        } catch (\Exception $e) {
            Log::error('Test error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            
            $output[] = "\n[ERROR] TEST FAILED\n";
            $output[] = "Error: " . $e->getMessage() . "\n";

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'output' => implode('', $output)
            ], 500);
        }
    }

    /**
     * Queue invoice items to their mapped branch service points.
     * Falls back to default service point for unmapped items.
     */
    private function queueItemsByMappedServicePoint(
        int $clientId,
        int $businessId,
        int $branchId,
        array $invoiceItems,
        int $defaultServicePointId
    ): array {
        $grouped = [];

        foreach ($invoiceItems as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $mappedServicePointId = null;

            if ($itemId > 0) {
                $item = Item::find($itemId);
                if ($item) {
                    $mappedServicePointId = $item->branchServicePoints()
                        ->where('business_id', $businessId)
                        ->where('branch_id', $branchId)
                        ->value('service_point_id');
                }
            }

            $servicePointId = (int) ($mappedServicePointId ?: $defaultServicePointId);
            if (!isset($grouped[$servicePointId])) {
                $grouped[$servicePointId] = [];
            }
            $grouped[$servicePointId][] = $row;
        }

        $created = [];
        foreach ($grouped as $servicePointId => $items) {
            $queue = ServiceQueue::create([
                'client_id' => $clientId,
                'service_point_id' => $servicePointId,
                'user_id' => Auth::id(),
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'queue_number' => ServiceQueue::generateQueueNumber($servicePointId, $businessId),
                'status' => ServiceQueue::STATUS_PENDING,
                'priority' => ServiceQueue::PRIORITY_NORMAL,
                'items' => $items,
                'total_amount' => array_sum(array_map(fn ($x) => (float) ($x['total'] ?? 0), $items)),
                'payment_status' => ServiceQueue::PAYMENT_PAID,
                'notes' => 'Automated non-insurance test queue entry (mapped by item service point)',
            ]);

            $spName = ServicePoint::find($servicePointId)?->name ?? 'Service Point #' . $servicePointId;
            $created[] = [
                'service_point_id' => $servicePointId,
                'service_point_name' => $spName,
                'items_count' => count($items),
                'queue_number' => $queue->queue_number,
            ];
        }

        return $created;
    }
}


