<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\Group;
use App\Models\ItemImportanceCategory;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryOrder;
use App\Models\Item;
use App\Models\Store;
use App\Models\SubGroup;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\InventoryEvaluationCommitteeService;
use App\Services\Inventory\InventoryOrderApprovalService;
use App\Services\Inventory\InventoryOrderFulfillmentService;
use App\Services\Inventory\InventoryOrderService;
use App\Services\Inventory\InventoryProcurementPdfService;
use App\Services\Inventory\InventoryStockTransferService;
use App\Support\InventoryBusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class InventoryOrderController extends Controller
{
    use RequiresInventoryModule;

    public function __construct(
        private readonly InventoryOrderService $service,
        private readonly InventoryOrderApprovalService $approvalService,
        private readonly InventoryOrderFulfillmentService $fulfillmentService,
        private readonly InventoryProcurementPdfService $pdfService,
        private readonly InventoryStockTransferService $transferService,
        private readonly InventoryEvaluationCommitteeService $committeeService,
    ) {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        if (! in_array($status, ['all', 'draft', 'pending_approval', 'approved', 'po_issued', 'fulfilled', 'rejected'], true)) {
            $status = 'all';
        }

        return view('inventory.orders.index', compact('status'));
    }

    public function howItWorks()
    {
        return view('inventory.orders.how-it-works');
    }

    public function create()
    {
        $businessId = (int) InventoryBusinessContext::effectiveBusinessId();
        ItemImportanceCategory::ensureDefaultsForBusiness($businessId);
        $moduleConfig = InventoryModuleConfig::query()
            ->forBusiness($businessId)
            ->active()
            ->with('evaluationCommitteeMembers')
            ->first();

        $businessUsers = User::query()
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $defaultChair = $moduleConfig?->evaluationCommitteeMembers?->firstWhere('role', 'chair');

        return view('inventory.orders.create', [
            'stores' => Store::optionsForSelect($businessId),
            'storesList' => Store::query()
                ->forBusiness($businessId)
                ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('name')
                ->get()
                ->map(fn (Store $store) => [
                    'id' => $store->id,
                    'label' => $store->selectLabel(),
                    'parent_id' => $store->parent_id,
                ])
                ->values(),
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
            'businessUsers' => $businessUsers,
            'defaultCommitteeMemberIds' => $moduleConfig?->evaluationCommitteeMembers?->pluck('user_id')->map(fn ($id) => (int) $id)->all() ?? [],
            'defaultCommitteeChairId' => $defaultChair?->user_id,
            'evaluationCommitteeRequired' => $moduleConfig?->evaluationCommitteeRequired() ?? false,
        ]);
    }

    public function store(Request $request)
    {
        $businessId = (int) InventoryBusinessContext::effectiveBusinessId();
        ItemImportanceCategory::ensureDefaultsForBusiness($businessId);

        $importanceSlugs = array_keys(Item::importanceOptions($businessId));

        $validated = $request->validate([
            'order_type' => 'required|in:external,internal',
            'store_id' => 'required|exists:stores,id',
            'source_store_id' => 'nullable|required_if:order_type,internal|exists:stores,id|different:store_id',
            'supplier_id' => 'nullable|exists:suppliers,id',
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
            'committee_members' => 'nullable|array',
            'committee_members.*' => 'integer|exists:users,id',
            'committee_chair_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $orderingApproach = $validated['ordering_approach']
            ?? (in_array($validated['budget_mode'] ?? null, ['amount', 'days'], true) ? 'budget' : 'period');

        if ($orderingApproach === 'budget') {
            Validator::make(
                $request->only(['budget_value']),
                [
                    'budget_value' => 'required|numeric|min:1',
                ],
                [
                    'budget_value.required' => 'Enter the budget (UGX).',
                ]
            )->validate();

            $validated['budget_mode'] = InventoryOrder::BUDGET_MODE_AMOUNT;
            $validated['budget_value'] = (float) $request->input('budget_value');
            $validated['period_of_order_days'] = null;
        } else {
            $validated['budget_mode'] = null;
            $validated['budget_value'] = null;

            Validator::make(
                $request->only(['period_of_order_days']),
                [
                    'period_of_order_days' => 'required|numeric|min:1',
                ],
                [
                    'period_of_order_days.required' => 'Enter the period of order (days) when ordering by period.',
                    'period_of_order_days.min' => 'Period of order must be at least 1 day.',
                ]
            )->validate();
        }

        $this->validateOrderBudget($request, $validated);

        Store::query()
            ->where('business_id', $businessId)
            ->where('id', $validated['store_id'])
            ->firstOrFail();

        $receivingStore = Store::query()
            ->where('business_id', $businessId)
            ->whereKey($validated['store_id'])
            ->firstOrFail();

        if ($validated['order_type'] === InventoryOrder::TYPE_INTERNAL) {
            $sourceStore = Store::query()
                ->where('business_id', $businessId)
                ->whereKey($validated['source_store_id'])
                ->firstOrFail();

            if (! $sourceStore->canTransferStockTo($receivingStore)) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'source_store_id' => 'Internal orders can only be placed between a store and its parent distribution store (or between two root stores). Child stores cannot order directly from sibling stores.',
                    ]);
            }
        } elseif (! empty($validated['supplier_id'])) {
            Supplier::query()
                ->where('business_id', $businessId)
                ->whereKey($validated['supplier_id'])
                ->firstOrFail();
        }

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
            isset($validated['period_of_order_days']) ? (float) $validated['period_of_order_days'] : null,
            $validated['notes'] ?? null,
            isset($validated['group_id']) ? (int) $validated['group_id'] : null,
            isset($validated['subgroup_id']) ? (int) $validated['subgroup_id'] : null,
            isset($validated['peak_period_percent']) ? (float) $validated['peak_period_percent'] : null,
            isset($validated['peak_consumption_increase_percent']) ? (float) $validated['peak_consumption_increase_percent'] : null,
            isset($validated['safety_stock_days']) ? (float) $validated['safety_stock_days'] : null,
            isset($validated['buffer_stock_days']) ? (float) $validated['buffer_stock_days'] : null,
            isset($validated['notification_to_order_days']) ? (float) $validated['notification_to_order_days'] : null,
            $itemIds,
            null, // External RFQs have no header supplier — selection happens at quotation analysis.
            $validated['order_type'],
            $validated['order_type'] === InventoryOrder::TYPE_INTERNAL ? (int) $validated['source_store_id'] : null,
        );

        if ($order->isExternal()) {
            try {
                $memberInputs = $this->committeeService->memberInputsFromRequest(
                    $validated['committee_members'] ?? [],
                    isset($validated['committee_chair_user_id']) ? (int) $validated['committee_chair_user_id'] : null,
                );

                if ($memberInputs !== []) {
                    $this->committeeService->syncOrderMembers($order, $memberInputs, Auth::user());
                } else {
                    $this->committeeService->applyDefaultsToOrder($order, Auth::user());
                }
            } catch (\Illuminate\Validation\ValidationException $e) {
                $order->delete();

                return back()->withInput()->withErrors($e->errors());
            }
        }

        $redirect = redirect()->route('inventory.orders.show', $order);

        if ($order->lines()->count() === 0) {
            return $redirect->with('warning', $this->service->explainEmptyOrder($order));
        }

        $message = $order->isInternal()
            ? 'Internal order generated. Review quantities before submitting for approval.'
            : ($order->canDownloadRfqPdf()
                ? 'Purchase request generated. Download the PDF from the order page, then submit for approval when ready.'
                : 'Purchase request generated. Review and edit quantities before submitting for approval.');

        return $redirect->with('success', $message);
    }

    public function pdf(InventoryOrder $order)
    {
        $this->authorizeOrder($order);

        if (! $order->canDownloadRfqPdf()) {
            abort(404, 'This order has no RFQ PDF available.');
        }

        return $this->pdfService->rfqPdf($order)->download($order->rfqPdfFilename());
    }

    public function calculations(InventoryOrder $order)
    {
        $this->authorizeOrder($order);

        $order->load(['lines.item.itemUnit', 'store', 'sourceStore', 'supplier']);
        $breakdown = $this->service->calculationBreakdown($order);

        return view('inventory.orders.calculations', compact('order', 'breakdown'));
    }

    public function show(InventoryOrder $order)
    {
        $this->authorizeOrder($order);

        $order->load([
            'lines.item.itemUnit',
            'lines.item.orderUnit',
            'lines.supplier',
            'store',
            'sourceStore',
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
            'stockTransfers',
            'invitedSuppliers',
            'committeeMembers.user',
        ]);

        $businessUsers = User::query()
            ->where('business_id', $order->business_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $committeeChair = $order->committeeMembers->firstWhere('role', 'chair');
        $canManageCommittee = $order->isDraft()
            && $order->isExternal()
            && ! InventoryBusinessContext::isAdminBrowsing();
        $moduleConfig = InventoryModuleConfig::query()
            ->forBusiness((int) $order->business_id)
            ->active()
            ->first();
        $evaluationCommitteeRequired = $moduleConfig?->evaluationCommitteeRequired() ?? false;

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
            'businessUsers',
            'committeeChair',
            'canManageCommittee',
            'evaluationCommitteeRequired',
        ));
    }

    public function saveCommittee(Request $request, InventoryOrder $order)
    {
        $this->authorizeOrder($order);
        InventoryBusinessContext::assertWritable();

        if (! $order->isDraft() || ! $order->isExternal()) {
            return back()->withErrors(['committee' => 'Committee can only be updated on draft external orders.']);
        }

        $validated = $request->validate([
            'committee_members' => 'nullable|array',
            'committee_members.*' => 'integer|exists:users,id',
            'committee_chair_user_id' => 'nullable|integer|exists:users,id',
        ]);

        try {
            $this->committeeService->syncOrderMembers(
                $order,
                $this->committeeService->memberInputsFromRequest(
                    $validated['committee_members'] ?? [],
                    isset($validated['committee_chair_user_id']) ? (int) $validated['committee_chair_user_id'] : null,
                ),
                Auth::user(),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('inventory.orders.show', [$order, 'tab' => 'committee'])
            ->with('success', 'Evaluation committee saved.');
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
            ->with('success', $order->isInternal()
                ? 'Internal order submitted for approval.'
                : 'Purchase request submitted for approval.');
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

        if ($order->isInternal() && $order->isAwaitingInternalFulfillment()) {
            $transfer = $order->activeStockTransfer();
            $message = $transfer
                ? 'Internal order approved. Stock transfer '.$transfer->reference.' is ready — review and submit it.'
                : 'Internal order approved. Create a stock transfer to issue stock.';
        } elseif ($order->isRfqApproved()) {
            $message = 'RFQ approved. Invite suppliers and open Quotation analysis to compare quotes, then generate LPOs.';
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
            ->with('success', $order->isInternal() ? 'Internal order rejected.' : 'RFQ rejected.');
    }

    public function createTransfer(InventoryOrder $order)
    {
        $this->authorizeOrder($order);

        try {
            $transfer = $this->transferService->createFromInternalOrder($order, Auth::user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('inventory.transfers.show', $transfer)
            ->with('success', 'Stock transfer draft created from internal order. The supplying store can review quantities before submission.');
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

        $order = $order->fresh(['lines.item.itemUnit', 'lines.item.orderUnit', 'store', 'group', 'subgroup', 'supplier']);

        if ($order->isExternal()) {
            $this->service->refreshRfqDocument($order);
            $order = $order->fresh();
        }

        $redirect = redirect()->route('inventory.orders.show', $order);

        if ($order->lines()->count() === 0) {
            return $redirect->with('warning', $this->service->explainEmptyOrder($order));
        }

        $message = $order->isExternal() && $order->canDownloadRfqPdf()
            ? 'Purchase request items refreshed. Download the updated PDF from the order page.'
            : 'Order items refreshed from current stock and consumption.';

        return $redirect->with('success', $message);
    }

    private function authorizeOrder(InventoryOrder $order): void
    {
        if ((int) $order->business_id !== (int) InventoryBusinessContext::effectiveBusinessId()) {
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
                'budget_value.max' => 'Budget days cannot exceed 366.',
                'budget_value.integer' => 'Budget days must be a whole number.',
            ]
        )->validate();
    }
}
