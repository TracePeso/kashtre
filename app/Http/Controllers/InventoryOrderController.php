<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\ItemImportanceCategory;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryOrder;
use App\Models\Item;
use App\Models\Store;
use App\Models\SubGroup;
use App\Models\Supplier;
use App\Services\Inventory\InventoryOrderApprovalService;
use App\Services\Inventory\InventoryOrderFulfillmentService;
use App\Services\Inventory\InventoryOrderService;
use App\Services\Inventory\InventoryProcurementPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class InventoryOrderController extends Controller
{
    public function __construct(
        private readonly InventoryOrderService $service,
        private readonly InventoryOrderApprovalService $approvalService,
        private readonly InventoryOrderFulfillmentService $fulfillmentService,
        private readonly InventoryProcurementPdfService $pdfService,
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
        ItemImportanceCategory::ensureDefaultsForBusiness($businessId);
        $moduleConfig = InventoryModuleConfig::query()
            ->forBusiness($businessId)
            ->active()
            ->first();

        return view('inventory.orders.create', [
            'stores' => Store::optionsForSelect($businessId),
            'suppliers' => Supplier::query()
                ->where('business_id', $businessId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'items' => Item::query()
                ->where('business_id', $businessId)
                ->where('type', 'good')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'importance_category', 'group_id', 'subgroup_id']),
            'importanceOptions' => Item::importanceOptions($businessId),
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
        $businessId = (int) Auth::user()->business_id;
        ItemImportanceCategory::ensureDefaultsForBusiness($businessId);

        $importanceSlugs = array_keys(Item::importanceOptions($businessId));

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'ordering_approach' => 'nullable|in:period,budget',
            'importance_filter' => array_merge(
                ['nullable', 'string', 'max:64'],
                $request->filled('importance_filter') ? [Rule::in($importanceSlugs)] : []
            ),
            'group_id' => 'nullable|exists:groups,id',
            'subgroup_id' => 'nullable|exists:sub_groups,id',
            'budget_mode' => 'nullable|in:days,amount',
            'budget_value' => 'nullable|numeric|min:0',
            'period_of_order_days' => 'nullable|numeric|min:0',
            'safety_stock_days' => 'nullable|numeric|min:0',
            'buffer_stock_days' => 'nullable|numeric|min:0',
            'notification_to_order_days' => 'nullable|numeric|min:0',
            'peak_period_percent' => 'nullable|numeric|min:0|max:100',
            'peak_consumption_increase_percent' => 'nullable|numeric|min:0|max:1000',
            'notes' => 'nullable|string|max:2000',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'integer|exists:items,id',
        ]);

        $orderingApproach = $validated['ordering_approach']
            ?? (($validated['budget_mode'] ?? null) ? 'budget' : 'period');

        if ($orderingApproach === 'budget') {
            Validator::make(
                $request->only(['budget_mode', 'budget_value']),
                [
                    'budget_mode' => 'required|in:days,amount',
                    'budget_value' => 'required|numeric|min:1',
                ],
                [
                    'budget_value.required' => 'Enter a budget cap or stock-days target when ordering by budget.',
                    'budget_value.min' => 'Budget must be at least 1.',
                ]
            )->validate();

            $validated['budget_mode'] = $request->input('budget_mode');
            $validated['budget_value'] = (float) $request->input('budget_value');
        } else {
            $validated['budget_mode'] = null;
            $validated['budget_value'] = null;
        }

        $this->validateOrderBudget($request, $validated);

        Store::query()
            ->where('business_id', $businessId)
            ->where('id', $validated['store_id'])
            ->firstOrFail();

        Supplier::query()
            ->where('business_id', $businessId)
            ->whereKey($validated['supplier_id'])
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
            isset($validated['peak_consumption_increase_percent']) ? (float) $validated['peak_consumption_increase_percent'] : null,
            isset($validated['safety_stock_days']) ? (int) $validated['safety_stock_days'] : null,
            isset($validated['buffer_stock_days']) ? (int) $validated['buffer_stock_days'] : null,
            isset($validated['notification_to_order_days']) ? (int) $validated['notification_to_order_days'] : null,
            $itemIds,
            (int) $validated['supplier_id'],
        );

        $redirect = redirect()->route('inventory.orders.show', $order);

        if ($order->lines()->count() === 0) {
            return $redirect->with('warning', $this->service->explainEmptyOrder($order));
        }

        return $redirect
            ->with('success', 'Order generated. Review and edit quantities before submitting.');
    }

    public function pdf(InventoryOrder $order)
    {
        $this->authorizeOrder($order);

        return $this->pdfService->rfqPdf($order)->download($order->order_number.'.pdf');
    }

    public function show(InventoryOrder $order)
    {
        $this->authorizeOrder($order);

        $order->load([
            'lines.item.itemUnit',
            'lines.item.orderUnit',
            'lines.supplier',
            'store',
            'supplier',
            'createdBy',
            'submittedBy',
            'group',
            'subgroup',
            'approvals.approver',
            'goodsReceivedNotes',
            'supplierQuotations.lines.item',
            'supplierQuotations.supplier',
            'supplierQuotations.purchaseOrder',
            'purchaseOrders.supplier',
            'purchaseOrders.lines',
        ]);

        $emptyOrderReason = $order->lines->isEmpty()
            ? app(InventoryOrderService::class)->explainEmptyOrder($order)
            : null;

        $canApprove = $this->approvalService->userCanApprove($order, Auth::user());
        $receiptOptions = [];

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
            ->with('success', 'RFQ submitted for approval.');
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

        if ($order->isRfqApproved()) {
            $message = 'RFQ approved. Record supplier quotations, then generate LPOs.';
        } elseif ($order->isPendingApproval()) {
            $message = 'Approval recorded. Awaiting next approver.';
        } else {
            $message = 'Approval recorded.';
        }

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
            ->with('success', 'RFQ rejected.');
    }

    public function receive(InventoryOrder $order, Request $request)
    {
        $this->authorizeOrder($order);

        $issuedPos = $order->purchaseOrders()
            ->whereIn('status', [
                \App\Models\InventoryPurchaseOrder::STATUS_ISSUED,
                \App\Models\InventoryPurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            ])
            ->with('supplier')
            ->get();

        if ($issuedPos->isEmpty()) {
            return back()->withErrors(['status' => 'Issue an LPO from an accepted supplier quotation before receiving goods.']);
        }

        if ($issuedPos->count() === 1) {
            return redirect()->route('inventory.purchase-orders.receive', $issuedPos->first());
        }

        return view('inventory.orders.receive-select-lpo', compact('order', 'issuedPos'));
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
