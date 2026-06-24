<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryOrder;
use App\Models\Item;
use App\Models\Store;
use App\Models\SubGroup;
use App\Services\Inventory\InventoryOrderApprovalService;
use App\Services\Inventory\InventoryOrderFulfillmentService;
use App\Services\Inventory\InventoryOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class InventoryOrderController extends Controller
{
    public function __construct(
        private readonly InventoryOrderService $service,
        private readonly InventoryOrderApprovalService $approvalService,
        private readonly InventoryOrderFulfillmentService $fulfillmentService,
    ) {
        $this->middleware(function ($request, $next) {
            return $this->inventoryMiddleware($request, $next);
        });
    }

    public function index()
    {
        return view('inventory.orders.index');
    }

    public function create()
    {
        $businessId = (int) Auth::user()->business_id;
        $moduleConfig = InventoryModuleConfig::query()
            ->forBusiness($businessId)
            ->active()
            ->first();

        return view('inventory.orders.create', [
            'stores' => Store::optionsForSelect($businessId),
            'items' => Item::query()
                ->where('business_id', $businessId)
                ->where('type', 'good')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'importance_category', 'group_id', 'subgroup_id']),
            'importanceOptions' => \App\Models\Item::importanceOptions(),
            'groupOptions' => Group::query()
                ->where('business_id', $businessId)
                ->orderBy('name')
                ->pluck('name', 'id'),
            'subgroupOptions' => SubGroup::query()
                ->where('business_id', $businessId)
                ->orderBy('name')
                ->pluck('name', 'id'),
            'moduleConfig' => $moduleConfig,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'importance_filter' => 'nullable|in:essential,non_essential',
            'group_id' => 'nullable|exists:groups,id',
            'subgroup_id' => 'nullable|exists:sub_groups,id',
            'budget_mode' => 'nullable|in:days,amount',
            'budget_value' => 'nullable|numeric|min:0|required_with:budget_mode',
            'period_of_order_days' => 'nullable|numeric|min:0',
            'safety_stock_days' => 'nullable|numeric|min:0',
            'buffer_stock_days' => 'nullable|numeric|min:0',
            'notification_to_order_days' => 'nullable|numeric|min:0',
            'peak_period_percent' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:2000',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'integer|exists:items,id',
        ]);

        $this->validateOrderBudget($request, $validated);

        $businessId = (int) Auth::user()->business_id;

        Store::query()
            ->where('business_id', $businessId)
            ->where('id', $validated['store_id'])
            ->firstOrFail();

        if (! empty($validated['group_id'])) {
            Group::query()
                ->where('business_id', $businessId)
                ->whereKey($validated['group_id'])
                ->firstOrFail();
        }

        if (! empty($validated['subgroup_id'])) {
            SubGroup::query()
                ->where('business_id', $businessId)
                ->whereKey($validated['subgroup_id'])
                ->firstOrFail();
        }

        $itemIds = $validated['item_ids'] ?? null;

        if ($itemIds !== null && $itemIds !== []) {
            $validItemCount = Item::query()
                ->where('business_id', $businessId)
                ->where('type', 'good')
                ->whereIn('id', $itemIds)
                ->count();

            if ($validItemCount !== count(array_unique($itemIds))) {
                return back()
                    ->withInput()
                    ->withErrors(['item_ids' => 'One or more selected items are invalid for your organisation.']);
            }
        }

        $order = $this->service->createDraft(
            $businessId,
            (int) $validated['store_id'],
            Auth::user(),
            $validated['importance_filter'] ?? null,
            $validated['budget_mode'] ?? null,
            isset($validated['budget_value']) ? (float) $validated['budget_value'] : null,
            isset($validated['period_of_order_days']) ? (int) $validated['period_of_order_days'] : null,
            $validated['notes'] ?? null,
            isset($validated['group_id']) ? (int) $validated['group_id'] : null,
            isset($validated['subgroup_id']) ? (int) $validated['subgroup_id'] : null,
            isset($validated['peak_period_percent']) ? (float) $validated['peak_period_percent'] : null,
            isset($validated['safety_stock_days']) ? (int) $validated['safety_stock_days'] : null,
            isset($validated['buffer_stock_days']) ? (int) $validated['buffer_stock_days'] : null,
            isset($validated['notification_to_order_days']) ? (int) $validated['notification_to_order_days'] : null,
            $itemIds,
        );

        $redirect = redirect()->route('inventory.orders.show', $order);

        if ($order->lines()->count() === 0) {
            return $redirect->with('warning', $this->service->explainEmptyOrder($order));
        }

        return $redirect
            ->with('success', 'Order generated. Review and edit quantities before submitting.');
    }

    public function show(InventoryOrder $order)
    {
        $this->authorizeOrder($order);

        $order->load([
            'lines.item.itemUnit',
            'lines.item.orderUnit',
            'store',
            'createdBy',
            'submittedBy',
            'group',
            'subgroup',
            'approvals.approver',
            'goodsReceivedNotes',
        ]);

        $emptyOrderReason = $order->lines->isEmpty()
            ? app(InventoryOrderService::class)->explainEmptyOrder($order)
            : null;

        $canApprove = $this->approvalService->userCanApprove($order, Auth::user());
        $receiptOptions = $order->canReceiveGoods()
            ? $this->fulfillmentService->receiptOptionsBySupplier($order)
            : [];

        return view('inventory.orders.show', compact(
            'order',
            'emptyOrderReason',
            'canApprove',
            'receiptOptions',
        ));
    }

    public function submit(InventoryOrder $order)
    {
        $this->authorizeOrder($order);

        if (! $order->isDraft()) {
            return back()->withErrors(['status' => 'Only draft orders can be submitted.']);
        }

        try {
            $this->approvalService->submit($order, Auth::user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('inventory.orders.show', $order)
            ->with('success', 'Order submitted for approval.');
    }

    public function approve(Request $request, InventoryOrder $order)
    {
        $this->authorizeOrder($order);

        $request->validate(['comment' => 'nullable|string|max:1000']);

        try {
            $this->approvalService->approve($order, Auth::user(), $request->input('comment'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $order->refresh();

        $message = $order->isApproved()
            ? 'Order approved. You can now receive goods against this order.'
            : 'Approval recorded. Awaiting next approver.';

        return redirect()
            ->route('inventory.orders.show', $order)
            ->with('success', $message);
    }

    public function reject(Request $request, InventoryOrder $order)
    {
        $this->authorizeOrder($order);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $this->approvalService->reject($order, Auth::user(), $validated['reason']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('inventory.orders.show', $order)
            ->with('success', 'Order rejected.');
    }

    public function receive(InventoryOrder $order, Request $request)
    {
        $this->authorizeOrder($order);

        if (! $order->canReceiveGoods()) {
            return back()->withErrors(['status' => 'Only approved orders can be received against.']);
        }

        $receiptOptions = $this->fulfillmentService->receiptOptionsBySupplier($order);

        if ($receiptOptions === []) {
            return back()->with('warning', 'All items on this order have been fully received.');
        }

        $supplierId = $request->query('supplier_id');

        if ($supplierId === null && count($receiptOptions) > 1) {
            return view('inventory.orders.receive-select-supplier', compact('order', 'receiptOptions'));
        }

        if ($supplierId === null && count($receiptOptions) === 1) {
            $supplierId = $receiptOptions[0]['supplier_id'];
        }

        $supplierQuery = ($supplierId !== null && (int) $supplierId !== 0)
            ? (int) $supplierId
            : null;

        return redirect()->route('inventory.receive.create', array_filter([
            'inventory_order_id' => $order->id,
            'supplier_id' => $supplierQuery,
        ]));
    }

    public function regenerate(InventoryOrder $order)
    {
        $this->authorizeOrder($order);

        if (! $order->isDraft()) {
            return back()->withErrors(['status' => 'Only draft orders can be regenerated.']);
        }

        $this->service->populateLines($order);

        $order = $order->fresh(['lines.item.itemUnit', 'lines.item.orderUnit', 'store', 'group', 'subgroup']);

        $redirect = redirect()->route('inventory.orders.show', $order);

        if ($order->lines()->count() === 0) {
            return $redirect->with('warning', $this->service->explainEmptyOrder($order));
        }

        return $redirect->with('success', 'Order items refreshed from current stock and consumption.');
    }

    private function authorizeOrder(InventoryOrder $order): void
    {
        if ((int) $order->business_id !== (int) Auth::user()->business_id) {
            abort(403);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function validateOrderBudget(Request $request, array $validated): void
    {
        $mode = $validated['budget_mode'] ?? null;
        $value = isset($validated['budget_value']) ? (float) $validated['budget_value'] : null;

        if (! $mode || $value === null) {
            return;
        }

        Validator::make(
            ['budget_value' => $value],
            [
                'budget_value' => match ($mode) {
                    InventoryOrder::BUDGET_MODE_DAYS => [
                        'integer',
                        'min:1',
                        'max:366',
                    ],
                    InventoryOrder::BUDGET_MODE_AMOUNT => [
                        'numeric',
                        'min:1',
                    ],
                    default => ['numeric', 'min:0'],
                },
            ],
            [
                'budget_value.max' => 'Stock-days budget cannot exceed 366 days. Switch to Amount (UGX) if you meant a money cap.',
                'budget_value.integer' => 'Stock-days budget must be a whole number of days.',
            ]
        )->validate();
    }

    private function inventoryMiddleware($request, $next)
    {
        $user = auth()->user();

        if ($user->business_id === 1) {
            abort(403, 'Inventory is only available to business users.');
        }

        $enabled = \App\Models\InventoryModuleConfig::query()
            ->where('business_id', $user->business_id)
            ->where('is_active', true)
            ->exists();

        if (! $enabled) {
            abort(403, 'The inventory module is not enabled for your organisation.');
        }

        return $next($request);
    }
}
