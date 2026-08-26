<?php

namespace App\Http\Controllers;

use App\Models\ServicePoint;
use Illuminate\Http\Request;

class ServicePointController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return view("service_points.index");
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
    public function show(ServicePoint $servicePoint)
    {
        // Check if user has access to this service point
        $user = auth()->user();
        
        if (!$user->service_points || !in_array($servicePoint->id, $user->service_points)) {
            abort(403, 'You do not have access to this service point.');
        }

        // Load the service point with its queues and related data, ordered by queue time
        $servicePoint->load([
            'pendingDeliveryQueues' => function($query) {
                $query->with(['client', 'invoice', 'item'])
                      ->whereNotNull('client_id')
                      ->orderBy('queued_at', 'asc');
            },
            'partiallyDoneDeliveryQueues' => function($query) {
                $query->with(['client', 'invoice', 'item', 'startedByUser'])
                      ->whereNotNull('client_id')
                      ->orderBy('queued_at', 'asc');
            },
            'serviceDeliveryQueues' => function($query) {
                $query->where('status', 'completed')
                      ->whereNotNull('client_id')
                      ->whereDate('completed_at', today())
                      ->with(['client', 'invoice', 'item'])
                      ->orderBy('queued_at', 'asc');
            }
        ]);

        // Group queues by client for better organization
        $clientsWithItems = [];
        
        // Process pending items
        foreach ($servicePoint->pendingDeliveryQueues as $queue) {
            if (!$queue->client_id || !$queue->client) {
                continue;
            }
            $clientId = $queue->client_id;
            if (!isset($clientsWithItems[$clientId])) {
                $clientsWithItems[$clientId] = [
                    'client' => $queue->client,
                    'pending' => [],
                    'partially_done' => [],
                    'completed' => [],
                    'earliest_queue_time' => $queue->queued_at
                ];
            } else {
                // Update earliest queue time if this queue is earlier
                if ($queue->queued_at < $clientsWithItems[$clientId]['earliest_queue_time']) {
                    $clientsWithItems[$clientId]['earliest_queue_time'] = $queue->queued_at;
                }
            }
            $clientsWithItems[$clientId]['pending'][] = $queue;
        }
        
        // Process in-progress items (partially_done)
        foreach ($servicePoint->partiallyDoneDeliveryQueues as $queue) {
            if (!$queue->client_id || !$queue->client) {
                continue;
            }
            if ($user->business_id !== 1 && $queue->assigned_to && $queue->assigned_to !== $user->id) {
                continue;
            }
            $clientId = $queue->client_id;
            if (!isset($clientsWithItems[$clientId])) {
                $clientsWithItems[$clientId] = [
                    'client' => $queue->client,
                    'pending' => [],
                    'partially_done' => [],
                    'completed' => [],
                    'earliest_queue_time' => $queue->queued_at
                ];
            } else {
                // Update earliest queue time if this queue is earlier
                if ($queue->queued_at < $clientsWithItems[$clientId]['earliest_queue_time']) {
                    $clientsWithItems[$clientId]['earliest_queue_time'] = $queue->queued_at;
                }
            }
            $clientsWithItems[$clientId]['partially_done'][] = $queue;
        }
        
        // Process completed items
        foreach ($servicePoint->serviceDeliveryQueues as $queue) {
            if (!$queue->client_id || !$queue->client) {
                continue;
            }
            if (
                $queue->status === 'partially_done' &&
                $user->business_id !== 1 &&
                $queue->assigned_to &&
                $queue->assigned_to !== $user->id
            ) {
                continue;
            }
            $clientId = $queue->client_id;
            if (!isset($clientsWithItems[$clientId])) {
                $clientsWithItems[$clientId] = [
                    'client' => $queue->client,
                    'pending' => [],
                    'partially_done' => [],
                    'completed' => [],
                    'earliest_queue_time' => $queue->queued_at
                ];
            } else {
                // Update earliest queue time if this queue is earlier
                if ($queue->queued_at < $clientsWithItems[$clientId]['earliest_queue_time']) {
                    $clientsWithItems[$clientId]['earliest_queue_time'] = $queue->queued_at;
                }
            }
            $clientsWithItems[$clientId]['completed'][] = $queue;
        }

        // Sort clients by their earliest queue time (first in queue first)
        uasort($clientsWithItems, function($a, $b) {
            return $a['earliest_queue_time'] <=> $b['earliest_queue_time'];
        });

        return view('service-points.show', compact('servicePoint', 'clientsWithItems'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ServicePoint $servicePoint)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ServicePoint $servicePoint)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ServicePoint $servicePoint)
    {
        //
    }

    /**
     * Show client details page
     */
    public function clientDetails(ServicePoint $servicePoint, $clientId)
    {
        // Check if user has access to this service point
        $user = auth()->user();
        
        if (!$user->service_points || !in_array($servicePoint->id, $user->service_points)) {
            abort(403, 'You do not have access to this service point.');
        }

        // Get client data
        $client = \App\Models\Client::findOrFail($clientId);
        // Don't regenerate visit_id if it was cleared/expired - only generate when creating invoice
        // $client->ensureActiveVisitId();
        
        // Get all items for this client at this service point
        // Exclude completed items - they should not show in the queue
        $clientItemsQuery = \App\Models\ServiceDeliveryQueue::where('service_point_id', $servicePoint->id)
            ->where('client_id', $clientId)
            ->where('status', '!=', 'completed') // Exclude completed items from queue
            ->with(['item', 'invoice', 'startedByUser', 'servicePoint'])
            ->when($user->business_id !== 1, function ($query) use ($user) {
                $query->where(function ($inner) use ($user) {
                    $inner->whereNull('assigned_to')
                        ->orWhere('assigned_to', $user->id)
                        ->orWhere('status', 'pending');
                });
            });

        $clientItems = $clientItemsQuery->get();
        
        \Illuminate\Support\Facades\Log::info("=== SERVICE POINT CLIENT DETAILS - ITEMS FILTERED ===", [
            'service_point_id' => $servicePoint->id,
            'client_id' => $clientId,
            'total_items_found' => $clientItems->count(),
            'items_by_status' => $clientItems->groupBy('status')->map->count(),
            'completed_items_excluded' => true
        ]);

        if ($user->business_id !== 1) {
            $clientItems = $clientItems->filter(function ($item) use ($user) {
                if ($item->status === 'partially_done' && $item->assigned_to && $item->assigned_to !== $user->id) {
                    return false;
                }
                return true;
            })->values();
        }

        // Group items by status
        $pendingItems = $clientItems->where('status', 'pending');
        $partiallyDoneItems = $clientItems->where('status', 'partially_done');

        // Get client statement (balance statement)
        $clientStatement = \App\Models\BalanceHistory::where('client_id', $clientId)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        // Get client notes (if you have a notes system)
        $clientNotes = []; // You can implement this based on your notes system

        // Calculate correct total amount from service delivery queue items (only pending and in-progress)
        $correctTotalAmount = $pendingItems->sum(function ($item) {
            return $item->price * $item->quantity;
        }) + $partiallyDoneItems->sum(function ($item) {
            return $item->price * $item->quantity;
        });

        // Get items for the POS functionality with branch-specific pricing
        // If user is from Kashtre (business_id 1), they can access items from all businesses
        if ($user->business_id == 1) {
            $items = \App\Models\Item::orderBy('name')->get();
        } else {
            $items = \App\Models\Item::where('business_id', $user->business_id)
                        ->orderBy('name')
                        ->get();
        }

        // Get current branch
        $currentBranch = $user->currentBranch;

        // Get branch-specific prices for each item
        // For each item, we need to find the appropriate branch price based on the item's business
        $branchPrices = [];
        
        // Get all branch prices for items from all businesses
        $allBranchPrices = \App\Models\BranchItemPrice::with('branch')
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
            \Illuminate\Support\Facades\Log::info("=== SERVICE POINTS ITEM PRICING DEBUG ===", [
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

        return view('pos.item-selection', compact(
            'servicePoint', 
            'client', 
            'pendingItems', 
            'partiallyDoneItems', 
            'correctTotalAmount',
            'items'
        ));
    }

    /**
     * Update statuses and process money movements (Save & Exit)
     */
    public function updateStatusesAndProcessMoneyMovements(Request $request, $servicePointId, $clientId)
    {
        // Handle service point - it might be 0 (null) or an actual ID
        $servicePoint = null;
        if ($servicePointId && $servicePointId != '0') {
            $servicePoint = ServicePoint::find($servicePointId);
        }
        
        \Illuminate\Support\Facades\Log::info("=== SAVE AND EXIT REQUEST STARTED ===", [
            'service_point_id' => $servicePoint ? $servicePoint->id : null,
            'client_id' => $clientId,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->name,
            'user_business_id' => auth()->user()->business_id,
            'request_data' => $request->all(),
            'item_statuses' => $request->input('item_statuses', []),
            'item_statuses_count' => count($request->input('item_statuses', [])),
            'raw_input' => $request->input(),
            'all_input' => $request->all(),
            'timestamp' => now()->toDateTimeString()
        ]);

        // Start database transaction for rollback capability
        \Illuminate\Support\Facades\DB::beginTransaction();
        
        try {
            $request->validate([
                'item_statuses' => 'nullable|array',
                'item_statuses.*' => 'required|in:not_done,partially_done,completed'
            ]);

            $itemStatuses = $request->input('item_statuses', []);

            // Additional validation: Check for status reversals (unidirectional flow)
            if (!empty($itemStatuses)) {
                foreach ($itemStatuses as $itemId => $status) {
                $item = \App\Models\ServiceDeliveryQueue::find($itemId);
                if ($item) {
                    // Prevent going backwards in status progression
                    if ($item->status === 'partially_done' && $status === 'not_done') {
                        \Illuminate\Support\Facades\Log::warning("Status reversal validation failed", [
                            'item_id' => $itemId,
                            'current_status' => $item->status,
                            'attempted_status' => $status
                        ]);
                        return response()->json([
                            'success' => false,
                            'message' => 'Cannot reverse status from "In Progress" to "Not Done"'
                        ], 422);
                    }
                    
                    if ($item->status === 'completed' && in_array($status, ['not_done', 'partially_done'])) {
                        \Illuminate\Support\Facades\Log::warning("Status reversal validation failed", [
                            'item_id' => $itemId,
                            'current_status' => $item->status,
                            'attempted_status' => $status
                        ]);
                        return response()->json([
                            'success' => false,
                            'message' => 'Cannot reverse status from "Completed" to previous status'
                        ], 422);
                    }
                }
            }
            }
            $updatedCount = 0;
            $moneyMovementCount = 0;
            $touchedInvoiceIds = [];

            \Illuminate\Support\Facades\Log::info("Item statuses received", [
                'item_statuses' => $itemStatuses,
                'total_items' => count($itemStatuses)
            ]);

            // Initialize MoneyTrackingService
            $moneyTrackingService = new \App\Services\MoneyTrackingService();

            // Get client for statement updates
            $client = \App\Models\Client::find($clientId);
            if (!$client) {
                \Illuminate\Support\Facades\Log::error("Client not found", [
                    'client_id' => $clientId
                ]);
                \Illuminate\Support\Facades\DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            \Illuminate\Support\Facades\Log::info("Client found", [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'client_suspense_balance_before' => $client->suspense_balance ?? 0
            ]);

            // Note: Client credit records are handled by the MoneyTrackingService automatically

            // Process service charge ONCE per invoice (not per item)
            $invoice = null;
            $serviceChargeProcessed = false;
            
            // Track which bulk items have been processed to prevent duplicate processing
            $processedBulkItems = [];

            // Process each finalized item
            foreach ($itemStatuses as $itemId => $status) {
                \Illuminate\Support\Facades\Log::info("Processing item status update", [
                    'item_id' => $itemId,
                    'new_status' => $status
                ]);

                $itemQuery = \App\Models\ServiceDeliveryQueue::where('id', $itemId)
                    ->where('client_id', $clientId);

                if ($servicePoint) {
                    $itemQuery->where('service_point_id', $servicePoint->id);
                }

                $item = $itemQuery->with(['item', 'invoice', 'creditNote'])->first();

                if (! $item) {
                    \Illuminate\Support\Facades\Log::warning("Service delivery queue item not found for status update", [
                        'item_id' => $itemId,
                        'client_id' => $clientId,
                        'service_point_id' => $servicePoint?->id,
                        'requested_status' => $status,
                    ]);
                    continue;
                }

                if ($status === 'not_done') {
                    if ($item->status === 'completed') {
                        \Illuminate\Support\Facades\Log::warning("Cannot mark completed item as not done", [
                            'item_id' => $item->id,
                            'current_status' => $item->status,
                            'requested_status' => $status,
                        ]);
                        continue;
                    }

                    if ($item->status !== 'not_done') {
                        $item->markAsNotDone(auth()->id());

                        \Illuminate\Support\Facades\Log::info("Item marked as not done; initiating refund workflow", [
                            'item_id' => $item->id,
                            'invoice_id' => $item->invoice_id,
                            'client_id' => $item->client_id,
                            'business_id' => $item->business_id,
                            'amount' => $item->price * $item->quantity,
                        ]);

                        $creditNoteService = app(\App\Services\CreditNoteService::class);
                        $createdCreditNote = $creditNoteService->initiateRefundWorkflow($item, auth()->user(), [
                            'trigger' => 'service_point_not_done',
                            'service_point_id' => $servicePoint?->id,
                        ]);

                        if ($createdCreditNote) {
                            \Illuminate\Support\Facades\Log::info("Refund workflow initiated successfully", [
                                'credit_note_id' => $createdCreditNote->id,
                                'credit_note_number' => $createdCreditNote->credit_note_number,
                                'current_stage' => $createdCreditNote->current_stage,
                            ]);
                        } else {
                            \Illuminate\Support\Facades\Log::warning("Refund workflow could not be initiated", [
                                'item_id' => $item->id,
                                'client_id' => $item->client_id,
                            ]);
                        }
                    } else {
                        \Illuminate\Support\Facades\Log::info("Item already marked as not done; skipping reprocessing", [
                            'item_id' => $item->id,
                        ]);
                    }

                    $updatedCount++;
                    continue;
                }

                if (in_array($status, ['partially_done', 'completed']) && $item->status !== $status) {
                    // SRD §2.2 — goods cannot enter In Progress / partially_done on the service-point dashboard.
                    $item->loadMissing('item');
                    if (($item->item?->type ?? null) === 'good') {
                        $inventoryOn = \App\Models\InventoryModuleConfig::query()
                            ->where('business_id', $item->business_id)
                            ->where('is_active', true)
                            ->exists();
                        if ($inventoryOn && in_array($status, ['partially_done', 'completed'], true)) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Goods cannot be marked In Progress or Completed here. Dispense/release them from EndStore.',
                            ], 422);
                        }
                    }

                    // Set the invoice if not already set
                    if (!$invoice) {
                        $invoice = $item->invoice;
                    }
                    if ($item->invoice_id) {
                        $touchedInvoiceIds[] = (int) $item->invoice_id;
                    }
                    \Illuminate\Support\Facades\Log::info("Item found for processing", [
                        'item_id' => $item->id,
                        'item_name' => $item->item->name ?? 'Unknown',
                        'current_status' => $item->status,
                        'new_status' => $status,
                        'is_money_moved' => $item->is_money_moved,
                        'invoice_id' => $item->invoice_id
                    ]);

                    // Prevent status reversal from partially_done to pending
                    if ($item->status === 'partially_done' && $status === 'pending') {
                        \Illuminate\Support\Facades\Log::warning("Status reversal not allowed", [
                            'item_id' => $item->id,
                            'current_status' => $item->status,
                            'attempted_status' => $status,
                            'reason' => 'Cannot reverse from partially_done to pending'
                        ]);
                        continue;
                    }

                    // Check if money was already moved for this item
                    // If money was already moved, only update status without moving money again
                    if ($item->is_money_moved) {
                        \Illuminate\Support\Facades\Log::info("Money already moved for this item, only updating status", [
                            'item_id' => $item->id,
                            'current_status' => $item->status,
                            'new_status' => $status,
                            'money_moved_at' => $item->money_moved_at
                        ]);
                        if ($status === 'partially_done' && $item->status !== 'partially_done') {
                            $item->loadMissing('item');
                            if (($item->item?->type ?? null) === 'good') {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'Goods cannot be marked In Progress. Dispense them from EndStore.',
                                ], 422);
                            }
                            $item->markAsPartiallyDone(auth()->id());
                        } elseif ($status === 'completed' && $item->status !== 'completed') {
                            $item->loadMissing('item');
                            if (($item->item?->type ?? null) === 'good') {
                                $inventoryOn = \App\Models\InventoryModuleConfig::query()
                                    ->where('business_id', $item->business_id)
                                    ->where('is_active', true)
                                    ->exists();
                                if ($inventoryOn) {
                                    return response()->json([
                                        'success' => false,
                                        'message' => 'Goods cannot be marked Completed here. Dispense/release them from EndStore.',
                                    ], 422);
                                }
                            }
                            $item->markAsCompleted(auth()->id());
                        } else {
                            $item->status = $status;
                            if (in_array($status, ['partially_done', 'completed'])) {
                                $item->assigned_to = auth()->id();
                            }
                            $item->save();
                        }

                        if (in_array($status, ['partially_done', 'completed'])) {
                            \App\Models\Sale::recordFromQueue(
                                $item->fresh(['client', 'invoice', 'item', 'servicePoint', 'business', 'branch']),
                                $status,
                                auth()->id()
                            );
                        }

                        $updatedCount++;
                        continue;
                    }
                    
                    // Update the status using model helpers to ensure sales are recorded
                    if ($status === 'partially_done') {
                        $item->markAsPartiallyDone(auth()->id());
                    } elseif ($status === 'completed') {
                        $item->markAsCompleted(auth()->id());
                    } else {
                        $item->status = $status;
                        if (in_array($status, ['partially_done', 'completed'])) {
                            $item->assigned_to = auth()->id();
                        }
                        $item->save();
                    }

                    \Illuminate\Support\Facades\Log::info("Item status updated", [
                        'item_id' => $item->id,
                        'new_status' => $status
                    ]);

                    // DISABLED: Service charge is now processed in InvoiceController with suspense system
                    // Process service charge ONCE per invoice
                    // if (!$serviceChargeProcessed && $item->invoice) {
                    //     $invoice = $item->invoice;
                    //     $this->processServiceCharge($invoice, $moneyTrackingService, $client);
                    //     $serviceChargeProcessed = true;
                    // }

                    // Process money movements for finalized items
                    try {
                        \Illuminate\Support\Facades\Log::info("Starting money movement for item", [
                            'item_id' => $item->id,
                            'item_name' => $item->item->name ?? 'Unknown',
                            'item_amount' => $item->price * $item->quantity
                        ]);

                        // Check if this item belongs to a bulk item
                        $bulkItemId = $this->getBulkItemIdForIncludedItem($item->item_id, $item->invoice_id);
                        
                        if ($bulkItemId && !in_array($bulkItemId, $processedBulkItems)) {
                            // This is an included item from a bulk - process the entire bulk amount
                            \Illuminate\Support\Facades\Log::info("Processing bulk item money movement", [
                                'bulk_item_id' => $bulkItemId,
                                'included_item_id' => $item->item_id,
                                'included_item_name' => $item->item->name ?? 'Unknown'
                            ]);
                            
                            $this->processBulkItemMoneyMovement($bulkItemId, $invoice, $client, $moneyTrackingService, $item->status);
                            $processedBulkItems[] = $bulkItemId;
                        } else if ($bulkItemId && in_array($bulkItemId, $processedBulkItems)) {
                            // This bulk item has already been processed - skip money movement
                            \Illuminate\Support\Facades\Log::info("Bulk item already processed, skipping money movement", [
                                'bulk_item_id' => $bulkItemId,
                                'included_item_id' => $item->item_id
                            ]);
                        } else {
                            // This is a regular item - process normally
                        $this->processItemMoneyMovement($item, $client, $moneyTrackingService);
                        }
                        
                        // Mark item as money moved to prevent double processing
                        $item->is_money_moved = true;
                        $item->money_moved_at = now();
                        $item->money_moved_by_user_id = auth()->id();
                        $item->save();
                        
                        \Illuminate\Support\Facades\Log::info("Money movement completed for item", [
                            'item_id' => $item->id,
                            'money_moved_at' => $item->money_moved_at,
                            'money_moved_by' => $item->money_moved_by_user_id
                        ]);
                        
                        $moneyMovementCount++;
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Money movement failed for item", [
                            'item_id' => $item->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        throw $e;
                    }

                    $updatedCount++;
                }
            }

            foreach (array_values(array_unique($touchedInvoiceIds)) as $invoiceId) {
                $this->syncInsuranceVendorDebitForDeliveredItems($invoiceId);
            }

            $message = "Successfully updated {$updatedCount} items and processed {$moneyMovementCount} money movements";

            \Illuminate\Support\Facades\Log::info("=== SAVE AND EXIT PROCESSING COMPLETED ===", [
                'updated_count' => $updatedCount,
                'money_movement_count' => $moneyMovementCount,
                'client_id' => $clientId,
                'client_suspense_balance_after' => $client->fresh()->suspense_balance ?? 0
            ]);

            // Commit all transactions if everything succeeded
            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'updated_count' => $updatedCount,
                'money_movement_count' => $moneyMovementCount
            ]);
            
        } catch (\Exception $e) {
            // Rollback all transactions if any error occurred
            \Illuminate\Support\Facades\DB::rollBack();
            
            \Illuminate\Support\Facades\Log::error("=== SAVE AND EXIT PROCESSING FAILED ===", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'client_id' => $clientId,
                'service_point_id' => $servicePoint->id,
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process money movement for a single completed item
     */
    private function processItemMoneyMovement($item, $client, $moneyTrackingService)
    {
        $invoice = $item->invoice;
        $business = \App\Models\Business::find($invoice->business_id);
        $itemAmount = $item->price * $item->quantity;
        
        \Illuminate\Support\Facades\Log::info("=== PROCESSING ITEM MONEY MOVEMENT ===", [
            'item_id' => $item->item_id,
            'item_name' => $item->item->name ?? 'Unknown',
            'item_amount' => $itemAmount,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'business_id' => $business->id,
            'business_name' => $business->name,
            'client_id' => $client->id,
            'client_name' => $client->name
        ]);
        
        try {
            // NEW LOGIC: Use the processSaveAndExit method instead of creating client statements
            // This will move money from suspense account to final accounts without creating client statements
            
            // Prepare item data for processSaveAndExit
            $itemData = [
                'item_id' => $item->item_id,
                'quantity' => $item->quantity,
                'total_amount' => $itemAmount
            ];
            
            \Illuminate\Support\Facades\Log::info("Calling processSaveAndExit with item data", [
                'item_data' => $itemData,
                'invoice_id' => $invoice->id
            ]);
            
            // Call the new processSaveAndExit method with item status
            $transferRecords = $moneyTrackingService->processSaveAndExit($invoice, [$itemData], $item->status);
            
            \Illuminate\Support\Facades\Log::info("Item money movement processed via processSaveAndExit", [
                'item_id' => $item->item_id,
                'item_amount' => $itemAmount,
                'transfer_records' => $transferRecords
            ]);

            // All money movement and balance statement creation is now handled by processSaveAndExit
            // No additional processing needed here
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Item money movement failed", [
                'item_id' => $item->item_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get the bulk item ID for an included item
     */
    private function getBulkItemIdForIncludedItem($includedItemId, $invoiceId)
    {
        // Get the invoice to check its items
        $invoice = \App\Models\Invoice::find($invoiceId);
        if (!$invoice) {
            return null;
        }

        // Check if this item is included in any bulk item for this invoice
        $bulkItem = \App\Models\BulkItem::where('included_item_id', $includedItemId)
            ->whereHas('bulkItem', function($query) use ($invoice) {
                // Check if the bulk item is in the invoice's items array
                $query->where(function($subQuery) use ($invoice) {
                    foreach ($invoice->items as $itemData) {
                        $itemId = $itemData['id'] ?? $itemData['item_id'] ?? null;
                        if ($itemId) {
                            $subQuery->orWhere('id', $itemId);
                        }
                    }
                });
            })
            ->with('bulkItem')
            ->first();
            
        return $bulkItem ? $bulkItem->bulk_item_id : null;
    }

    /**
     * Process money movement for a bulk item
     */
    private function processBulkItemMoneyMovement($bulkItemId, $invoice, $client, $moneyTrackingService, $itemStatus)
    {
        $bulkItem = \App\Models\Item::find($bulkItemId);
        if (!$bulkItem || $bulkItem->type !== 'bulk') {
            \Illuminate\Support\Facades\Log::error("Bulk item not found or not a bulk type", [
                'bulk_item_id' => $bulkItemId
            ]);
            return;
        }

        // Get the bulk item data from the invoice
        $bulkItemData = null;
        foreach ($invoice->items as $itemData) {
            if (($itemData['id'] ?? $itemData['item_id']) == $bulkItemId) {
                $bulkItemData = $itemData;
                break;
            }
        }

        if (!$bulkItemData) {
            \Illuminate\Support\Facades\Log::error("Bulk item data not found in invoice", [
                'bulk_item_id' => $bulkItemId,
                'invoice_id' => $invoice->id
            ]);
            return;
        }

        $bulkAmount = $bulkItemData['total_amount'] ?? ($bulkItem->default_price * ($bulkItemData['quantity'] ?? 1));

        \Illuminate\Support\Facades\Log::info("=== PROCESSING BULK ITEM MONEY MOVEMENT ===", [
            'bulk_item_id' => $bulkItemId,
            'bulk_item_name' => $bulkItem->name,
            'bulk_amount' => $bulkAmount,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $client->id,
            'client_name' => $client->name
        ]);

        try {
            // Prepare bulk item data for processSaveAndExit
            $itemData = [
                'item_id' => $bulkItemId,
                'quantity' => $bulkItemData['quantity'] ?? 1,
                'total_amount' => $bulkAmount
            ];
            
            \Illuminate\Support\Facades\Log::info("Calling processSaveAndExit for bulk item", [
                'item_data' => $itemData,
                'invoice_id' => $invoice->id
            ]);
            
            // Call the processSaveAndExit method for the bulk item
            $transferRecords = $moneyTrackingService->processSaveAndExit($invoice, [$itemData], $itemStatus);
            
            \Illuminate\Support\Facades\Log::info("Bulk item money movement processed via processSaveAndExit", [
                'bulk_item_id' => $bulkItemId,
                'bulk_amount' => $bulkAmount,
                'transfer_records' => $transferRecords
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Bulk item money movement failed", [
                'bulk_item_id' => $bulkItemId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Process service charge for an invoice (DEPRECATED - now handled by processSaveAndExit)
     */
    private function processServiceCharge($invoice, $moneyTrackingService, $client)
    {
        // Service charge processing is now handled by MoneyTrackingService::processSaveAndExit
        // This method is kept for backward compatibility but does nothing
        \Illuminate\Support\Facades\Log::info("processServiceCharge called but deprecated - service charges now handled by processSaveAndExit", [
            'invoice_id' => $invoice->id,
            'service_charge' => $invoice->service_charge ?? 0
        ]);
    }

    /**
     * Sync insurer debit rows to the delivered amount after queue status updates.
     */
    private function syncInsuranceVendorDebitForDeliveredItems(int $invoiceId): void
    {
        $invoice = \App\Models\Invoice::find($invoiceId);
        if (!$invoice) {
            return;
        }

        $debitRows = \App\Models\ThirdPartyPayerBalanceHistory::query()
            ->where('invoice_id', $invoice->id)
            ->where('transaction_type', 'debit')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($debitRows->isEmpty()) {
            return;
        }

        $deliveredSubtotal = (float) \App\Models\ServiceDeliveryQueue::query()
            ->where('invoice_id', $invoice->id)
            ->whereIn('status', ['partially_done', 'completed'])
            ->sum(\Illuminate\Support\Facades\DB::raw('price * quantity'));

        $invoiceSubtotal = (float) ($invoice->subtotal ?? 0);
        $serviceCharge = (float) ($invoice->service_charge ?? 0);
        $serviceChargePortion = 0.0;

        if ($serviceCharge > 0) {
            if ($invoiceSubtotal > 0) {
                $ratio = min(1, max(0, $deliveredSubtotal / $invoiceSubtotal));
                $serviceChargePortion = $serviceCharge * $ratio;
            } elseif ($deliveredSubtotal > 0) {
                $serviceChargePortion = $serviceCharge;
            }
        }

        $targetDeliveredTotal = round($deliveredSubtotal + $serviceChargePortion, 2);
        $currentDebitTotal = round(abs((float) $debitRows->sum('change_amount')), 2);
        $targetDeliveredTotal = min($targetDeliveredTotal, $currentDebitTotal);

        if (abs($targetDeliveredTotal - $currentDebitTotal) < 0.01) {
            return;
        }

        $allocated = 0.0;
        foreach ($debitRows as $index => $row) {
            $original = abs((float) $row->change_amount);
            if ($currentDebitTotal <= 0) {
                $newDebitAmount = 0.0;
            } elseif ($index === $debitRows->count() - 1) {
                $newDebitAmount = round(max(0, $targetDeliveredTotal - $allocated), 2);
            } else {
                $newDebitAmount = round(($original / $currentDebitTotal) * $targetDeliveredTotal, 2);
                $allocated += $newDebitAmount;
            }

            $row->change_amount = -abs($newDebitAmount);
            $row->payment_status = $newDebitAmount > 0 ? 'pending_payment' : 'paid';
            $row->save();
        }

        foreach ($debitRows->pluck('third_party_payer_id')->unique()->filter() as $payerId) {
            $this->recalculateThirdPartyPayerRunningBalance((int) $payerId);
        }
    }

    /**
     * Rebuild running balances for a third-party payer ledger.
     */
    private function recalculateThirdPartyPayerRunningBalance(int $payerId): void
    {
        $histories = \App\Models\ThirdPartyPayerBalanceHistory::query()
            ->where('third_party_payer_id', $payerId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $running = 0.0;
        foreach ($histories as $history) {
            $previous = $running;
            $amount = abs((float) $history->change_amount);
            $delta = $history->transaction_type === 'debit' ? -$amount : $amount;
            $running = round($previous + $delta, 2);

            $history->previous_balance = $previous;
            $history->new_balance = $running;
            $history->change_amount = $history->transaction_type === 'debit' ? -$amount : $amount;
            $history->save();
        }

        \App\Models\ThirdPartyPayer::where('id', $payerId)->update([
            'current_balance' => $running,
        ]);
    }

}
