<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\InventoryOrder;
use App\Models\InventorySupplierQuotation;
use App\Models\Supplier;
use App\Services\Inventory\InventoryEvaluationCommitteeService;
use App\Services\Inventory\InventoryRfqAwardService;
use App\Services\Inventory\InventorySupplierQuotationService;
use App\Support\InventoryBusinessContext;
use App\Support\SupplierCategorySelection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventorySupplierQuotationController extends Controller
{
    use RequiresInventoryModule;

    public function __construct(
        private readonly InventorySupplierQuotationService $service,
        private readonly InventoryRfqAwardService $awardService,
        private readonly InventoryEvaluationCommitteeService $committeeService,
    ) {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function invite(Request $request, InventoryOrder $order)
    {
        $this->authorizeOrder($order);

        $validated = $request->validate([
            'supplier_ids' => 'required|array|min:1',
            'supplier_ids.*' => 'integer|exists:suppliers,id',
        ]);

        try {
            $this->service->inviteSuppliers($order, $validated['supplier_ids']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        if ($order->isRfqApproved() && $order->isExternal()) {
            app(\App\Services\Inventory\InventoryProcurementNotificationService::class)
                ->sendRfqToAllInvitedSuppliers($order->fresh());
        }

        return redirect()
            ->route('inventory.orders.quotations.compare', $order)
            ->with('success', 'Suppliers invited to this RFQ.'.($order->isRfqApproved() ? ' RFQ PDF emailed where supplier emails are set.' : ''));
    }

    public function compare(InventoryOrder $order)
    {
        $this->authorizeOrder($order);

        if (! $order->isExternal()) {
            abort(404);
        }

        $order->load([
            'lines.item',
            'supplierQuotations.supplier',
            'supplierQuotations.purchaseOrder',
            'supplierQuotations.lines',
            'purchaseOrders.supplier',
            'invitedSuppliers',
            'supplier',
            'rfqLineAwards.supplier',
        ]);

        $this->service->ensurePrimarySupplierInvited($order);

        $hasSupplierQuotations = $order->supplierQuotations->contains(
            fn ($quotation) => $quotation->lines->isNotEmpty()
        );

        $showLpoWorkflow = $order->canManageSupplierQuotations() && $hasSupplierQuotations;

        $showSupplierSelection = $showLpoWorkflow;

        $showEvaluationCommittee = $showLpoWorkflow
            && $this->committeeService->businessHasConfiguredCommittee((int) $order->business_id);

        if ($showEvaluationCommittee) {
            $this->committeeService->ensureCommitteeBeforeLpo($order, Auth::user());
            $order->load('committeeMembers.user');
        }

        $sheet = $this->service->comparisonSheet($order);
        $acceptedWithoutLpoCount = collect($sheet['suppliers'])->filter(
            fn (array $sup): bool => ($sup['is_accepted'] ?? false) && ! ($sup['has_lpo'] ?? false)
        )->count();
        $computationForm = $showSupplierSelection
            ? $this->buildComputationFormData($order, $sheet, $this->awardService->awardFormData($order))
            : null;
        $availableSuppliers = Supplier::query()
            ->where('business_id', $order->business_id)
            ->with(['industry:id,name', 'subCategory:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'linked_business_id', 'supplier_industry_id', 'supplier_sub_category_id']);

        $hasSavedAllocations = $order->rfqLineAwards->isNotEmpty();
        $editAllocation = request()->boolean('edit_allocation');
        $showAllocationForm = $showSupplierSelection && (! $hasSavedAllocations || $editAllocation);

        return view('inventory.orders.quotations-compare', [
            'order' => $order,
            'sheet' => $sheet,
            'availableSuppliers' => $availableSuppliers,
            'supplierCatalog' => SupplierCategorySelection::catalogFromSuppliers($availableSuppliers),
            'supplierIndustries' => SupplierCategorySelection::industryOptionsForBusiness((int) $order->business_id),
            'supplierSubCategoriesByIndustry' => SupplierCategorySelection::subCategoryOptionsByIndustryForBusiness((int) $order->business_id),
            'rfqSuppliers' => $this->service->suppliersForRfq($order->fresh(['invitedSuppliers', 'supplierQuotations.lines', 'lines', 'supplier'])),
            'showLpoWorkflow' => $showLpoWorkflow,
            'showSupplierSelection' => $showSupplierSelection,
            'computationForm' => $computationForm,
            'showEvaluationCommittee' => $showEvaluationCommittee,
            'acceptedWithoutLpoCount' => $acceptedWithoutLpoCount,
            'hasSavedAllocations' => $hasSavedAllocations,
            'editAllocation' => $editAllocation,
            'showAllocationForm' => $showAllocationForm,
        ]);
    }

    public function store(Request $request, InventoryOrder $order)
    {
        $this->authorizeOrder($order);

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'reference_number' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1',
            'lines.*.inventory_order_line_id' => 'required|exists:inventory_order_lines,id',
            'lines.*.quoted_quantity_suom' => 'nullable|numeric|min:0',
            'lines.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            $validLineIds = $order->lines()->pluck('id')->all();

            foreach ($validated['lines'] as $index => $line) {
                if (! in_array((int) $line['inventory_order_line_id'], $validLineIds, true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "lines.{$index}.inventory_order_line_id" => 'One or more lines do not belong to this order.',
                    ]);
                }
            }

            $quotation = $this->service->createOrUpdateFromRfq(
                $order,
                (int) $validated['supplier_id'],
                Auth::user(),
                $validated['lines'],
                $validated['reference_number'] ?? null,
                $validated['notes'] ?? null,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('inventory.orders.quotations.compare', $order)
            ->with('success', 'Supplier quotation recorded for '.$quotation->supplier?->name.'.');
    }

    public function accept(InventorySupplierQuotation $quotation)
    {
        $this->authorizeQuotation($quotation);

        try {
            $this->service->accept($quotation);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('inventory.orders.quotations.compare', $quotation->inventoryOrder)
            ->with('success', 'Supplier quotation accepted. Generate an LPO when ready.');
    }

    public function reject(InventorySupplierQuotation $quotation)
    {
        $this->authorizeQuotation($quotation);

        try {
            $this->service->reject($quotation);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('inventory.orders.quotations.compare', $quotation->inventoryOrder)
            ->with('success', 'Supplier quotation rejected.');
    }

    public function saveAwards(Request $request, InventoryOrder $order)
    {
        $this->authorizeOrder($order);

        $awards = collect($request->input('awards', []))
            ->filter(function (array $row): bool {
                return ! empty($row['supplier_id'])
                    && (float) ($row['awarded_quantity_suom'] ?? 0) > 0;
            })
            ->map(fn (array $row): array => [
                'inventory_order_line_id' => $row['inventory_order_line_id'],
                'supplier_id' => $row['supplier_id'],
                'awarded_quantity_suom' => $row['awarded_quantity_suom'],
                'unit_price' => $row['unit_price'] ?? 0,
            ])
            ->values()
            ->all();

        if ($awards === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'awards' => 'Pick a supplier and enter a quantity for at least one item under “Your allocation”, then save again.',
            ]);
        }

        $request->merge(['awards' => $awards]);

        $validated = $request->validate([
            'awards' => 'nullable|array',
            'awards.*.inventory_order_line_id' => 'required|exists:inventory_order_lines,id',
            'awards.*.supplier_id' => 'required|exists:suppliers,id',
            'awards.*.awarded_quantity_suom' => 'required|numeric|min:0.01',
            'awards.*.unit_price' => 'nullable|numeric|min:0',
            'line_comments' => 'nullable|array',
            'line_comments.*.inventory_order_line_id' => 'required|exists:inventory_order_lines,id',
            'line_comments.*.quotation_analysis_comment' => 'nullable|string|max:2000',
        ]);

        try {
            $this->awardService->saveAwards($order, $validated['awards'] ?? []);
            if (! empty($validated['line_comments'])) {
                $this->service->saveLineComments($order, $validated['line_comments']);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('inventory.orders.quotations.compare', $order)
            ->with('success', 'Allocation and comments saved. Generate LPOs when ready.');
    }

    public function saveLineComments(Request $request, InventoryOrder $order)
    {
        $this->authorizeOrder($order);

        $validated = $request->validate([
            'line_comments' => 'nullable|array',
            'line_comments.*.inventory_order_line_id' => 'required|exists:inventory_order_lines,id',
            'line_comments.*.quotation_analysis_comment' => 'nullable|string|max:2000',
        ]);

        try {
            $this->service->saveLineComments($order, $validated['line_comments'] ?? []);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('inventory.orders.quotations.compare', $order)
            ->with('success', 'Item comments saved.');
    }

    private function buildComputationFormData(InventoryOrder $order, array $sheet, array $awardForm): array
    {
        $commentsByLineId = $order->lines->keyBy('id');
        $sheetLinesById = collect($sheet['lines'])->keyBy('order_line_id');

        $awardForm['lines'] = collect($awardForm['lines'])->map(function (array $line) use ($sheetLinesById, $commentsByLineId) {
            $sheetLine = $sheetLinesById->get($line['order_line_id'], []);
            $orderLine = $commentsByLineId->get($line['order_line_id']);

            return array_merge($line, [
                'quotes' => $sheetLine['quotes'] ?? [],
                'best_supplier_id' => $sheetLine['best_supplier_id'] ?? null,
                'fulfillment_label' => $sheetLine['fulfillment_label'] ?? 'Unallocated',
                'analysis_comment' => $orderLine?->quotation_analysis_comment,
            ]);
        })->values()->all();

        $awardForm['sheet_suppliers'] = collect($sheet['suppliers'])->map(fn (array $sup) => [
            'supplier_id' => $sup['supplier_id'],
            'supplier_name' => $sup['supplier_name'],
            'status_label' => $sup['status_label'],
            'total_amount' => $sup['total_amount'],
        ])->values()->all();

        return $awardForm;
    }

    private function authorizeOrder(InventoryOrder $order): void
    {
        if ((int) $order->business_id !== (int) InventoryBusinessContext::effectiveBusinessId()) {
            abort(403);
        }
    }

    private function authorizeQuotation(InventorySupplierQuotation $quotation): void
    {
        if ((int) $quotation->business_id !== (int) InventoryBusinessContext::effectiveBusinessId()) {
            abort(403);
        }
    }
}
