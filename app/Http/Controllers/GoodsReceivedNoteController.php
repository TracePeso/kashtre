<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\Business;
use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteLine;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryOrder;
use App\Models\InventoryPurchaseOrder;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\GoodsReceivedNoteService;
use App\Services\GrnBulkImportService;
use App\Support\InventoryBusinessContext;
use App\Support\SupplierCategorySelection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GoodsReceivedNoteController extends Controller
{
    use RequiresInventoryModule;

    public function __construct(
        private GoodsReceivedNoteService $service,
        private GrnBulkImportService $bulkImport,
    ) {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function create(Request $request)
    {
        $businessId = \App\Support\InventoryBusinessContext::effectiveBusinessId();

        $inventoryOrder = null;
        $purchaseOrder = null;
        $prefillLines = [];
        $prefillStoreId = old('store_id');
        $prefillSupplierId = old('supplier_id', $request->query('supplier_id'));

        if (is_array(old('lines')) && old('lines') !== []) {
            $prefillLines = collect(old('lines'))->map(fn (array $line): array => [
                'item_id' => $line['item_id'] ?? '',
                'inventory_order_line_id' => $line['inventory_order_line_id'] ?? '',
                'suom' => $line['suom'] ?? '',
                'duom' => $line['duom'] ?? '',
                'quantity' => $line['quantity'] ?? 1,
                'batch_number' => $line['batch_number'] ?? '',
                'expiry_date' => $line['expiry_date'] ?? '',
                'purchase_price' => $line['purchase_price'] ?? 0,
                'conversion' => $line['sale_units_per_purchase_unit'] ?? $line['conversion'] ?? 1,
            ])->values()->all();

            if (old('inventory_order_id')) {
                $inventoryOrder = InventoryOrder::query()
                    ->where('business_id', $businessId)
                    ->with(['store'])
                    ->find(old('inventory_order_id'));
            }
        } elseif ($poId = $request->query('inventory_purchase_order_id') ?? old('inventory_purchase_order_id')) {
            $purchaseOrder = InventoryPurchaseOrder::query()
                ->where('business_id', $businessId)
                ->with(['store', 'supplier', 'inventoryOrder', 'lines.item.itemUnit', 'lines.item.orderUnit'])
                ->findOrFail($poId);

            if (! $purchaseOrder->canReceiveGoods()) {
                abort(403, 'This LPO is not issued for receiving.');
            }

            $fulfillment = app(\App\Services\Inventory\InventoryPurchaseOrderFulfillmentService::class);
            $prefillLines = $fulfillment->prefillGrnLines($purchaseOrder);
            $inventoryOrder = $purchaseOrder->inventoryOrder;

            if ($prefillLines === []) {
                return redirect()
                    ->route('inventory.purchase-orders.show', $purchaseOrder)
                    ->with('warning', 'No remaining quantities to receive on this LPO.');
            }

            $prefillStoreId = $purchaseOrder->store_id;
            $prefillSupplierId = $purchaseOrder->supplier_id;
        } elseif ($orderId = $request->query('inventory_order_id') ?? old('inventory_order_id')) {
            $inventoryOrder = InventoryOrder::query()
                ->where('business_id', $businessId)
                ->with(['store', 'lines.item.itemUnit', 'lines.item.orderUnit', 'lines.supplier'])
                ->findOrFail($orderId);

            if (! $inventoryOrder->canReceiveGoods()) {
                abort(403, 'This RFQ has no issued LPO available for receiving.');
            }

            $fulfillment = app(\App\Services\Inventory\InventoryOrderFulfillmentService::class);
            $prefillLines = $fulfillment->prefillGrnLines(
                $inventoryOrder,
                ($prefillSupplierId !== null && $prefillSupplierId !== '' && (int) $prefillSupplierId !== 0)
                    ? (int) $prefillSupplierId
                    : null
            );

            if ($prefillLines === []) {
                return redirect()
                    ->route('inventory.orders.show', $inventoryOrder)
                    ->with('warning', 'No remaining quantities to receive for this supplier.');
            }

            $prefillStoreId = $inventoryOrder->store_id;
        }

        return view('inventory.receive.create', array_merge($this->grnFormOptions($businessId), [
            'inventoryOrder' => $inventoryOrder,
            'purchaseOrder' => $purchaseOrder,
            'prefillLines' => $prefillLines,
            'prefillStoreId' => $prefillStoreId,
            'prefillSupplierId' => $prefillSupplierId,
        ]));
    }

    public function bulkUpload()
    {
        return view('inventory.receive.bulk-upload', $this->grnFormOptions((int) \App\Support\InventoryBusinessContext::effectiveBusinessId()));
    }

    public function store(Request $request)
    {
        $validated = $this->validateGrn($request);

        $user = Auth::user();
        $businessId = $user->business_id;
        $action = $request->input('action', 'submit');

        if (! empty($validated['inventory_purchase_order_id'])) {
            $linkedPo = InventoryPurchaseOrder::query()
                ->where('business_id', $businessId)
                ->findOrFail($validated['inventory_purchase_order_id']);

            if (! $linkedPo->canReceiveGoods()) {
                throw ValidationException::withMessages([
                    'inventory_purchase_order_id' => 'The linked LPO is not issued for receiving.',
                ]);
            }
        } elseif (! empty($validated['inventory_order_id'])) {
            $linkedOrder = InventoryOrder::query()
                ->where('business_id', $businessId)
                ->findOrFail($validated['inventory_order_id']);

            if (! $linkedOrder->canReceiveGoods()) {
                throw ValidationException::withMessages([
                    'inventory_order_id' => 'The linked order is not approved for receiving.',
                ]);
            }
        }

        $grn = DB::transaction(function () use ($validated, $request, $user, $businessId, $action) {
            [$deliveryPath, $deliveryOriginal] = $this->storeUploadedFile($request, 'delivery_note', 'inventory/delivery-notes');

            $leadTime = $this->service->calculateLeadTimeDays(
                $validated['date_of_order'],
                $validated['date_of_delivery']
            );

            $grn = GoodsReceivedNote::create([
                'grn_number' => GoodsReceivedNote::generateNumber($businessId),
                'business_id' => $businessId,
                'supplier_id' => $validated['supplier_id'],
                'store_id' => $validated['store_id'],
                'inventory_order_id' => $validated['inventory_order_id'] ?? null,
                'inventory_purchase_order_id' => $validated['inventory_purchase_order_id'] ?? null,
                'date_of_order' => $validated['date_of_order'],
                'date_of_delivery' => $validated['date_of_delivery'],
                'lead_time_days' => $leadTime,
                'delivery_note_path' => $deliveryPath,
                'delivery_note_original_name' => $deliveryOriginal,
                'technical_supervisor_user_id' => $validated['technical_supervisor_user_id'] ?? null,
                'status' => GoodsReceivedNote::STATUS_DRAFT,
                'entry_by_user_id' => $user->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->syncLines($grn, $validated['lines']);

            return $grn;
        });

        if ($action === 'submit') {
            $this->service->submit($grn, $user);

            return redirect()->route('inventory.receive.show', $grn)
                ->with('success', 'Goods receive note submitted for approval.');
        }

        return redirect()->route('inventory.receive.show', $grn)
            ->with('success', 'Goods receive note saved as draft.');
    }

    public function show(GoodsReceivedNote $goodsReceivedNote)
    {
        $this->authorizeBusiness($goodsReceivedNote);

        $goodsReceivedNote->load([
            'lines.item',
            'supplier',
            'store',
            'entryBy',
            'submittedBy',
            'inspectedBy',
            'technicalSupervisor',
            'approvals.approver',
            'inventoryOrder',
            'purchaseOrder.lines',
        ]);

        if ($goodsReceivedNote->isApproved()) {
            $this->service->applyStockIfNeeded($goodsReceivedNote);
            $goodsReceivedNote->refresh();
        }

        $canApprove = $this->service->userCanApprove($goodsReceivedNote, Auth::user());

        return view('inventory.receive.show', compact('goodsReceivedNote', 'canApprove'));
    }

    public function inspect(Request $request, GoodsReceivedNote $goodsReceivedNote)
    {
        $this->authorizeBusiness($goodsReceivedNote);

        $validated = $request->validate([
            'inspection_status' => 'required|in:passed,failed,pending',
            'inspection_notes' => 'nullable|string|max:2000',
            'line_conditions' => 'nullable|array',
            'line_conditions.*' => 'nullable|in:good,damaged,expired,short',
        ]);

        try {
            $this->service->recordInspection(
                $goodsReceivedNote,
                Auth::user(),
                $validated['inspection_status'],
                $validated['inspection_notes'] ?? null,
                $validated['line_conditions'] ?? [],
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('inventory.receive.show', $goodsReceivedNote)
            ->with('success', 'QC inspection recorded.');
    }

    public function submit(GoodsReceivedNote $goodsReceivedNote)
    {
        $this->authorizeBusiness($goodsReceivedNote);

        $this->service->submit($goodsReceivedNote, Auth::user());

        return redirect()->route('inventory.receive.show', $goodsReceivedNote)
            ->with('success', 'Goods receive note submitted for approval.');
    }

    public function approve(Request $request, GoodsReceivedNote $goodsReceivedNote)
    {
        $this->authorizeBusiness($goodsReceivedNote);

        $request->validate(['comment' => 'nullable|string|max:1000']);

        $this->service->approve($goodsReceivedNote, Auth::user(), $request->input('comment'));

        $goodsReceivedNote->refresh();

        $message = $goodsReceivedNote->isApproved()
            ? 'All approvers have signed off. Stock at '.($goodsReceivedNote->store->name ?? 'the store').' has been updated.'
            : 'Your approval was recorded. Waiting for the next approver — stock is not updated yet.';

        return redirect()->route('inventory.receive.show', $goodsReceivedNote)
            ->with('success', $message);
    }

    public function reject(Request $request, GoodsReceivedNote $goodsReceivedNote)
    {
        $this->authorizeBusiness($goodsReceivedNote);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $this->service->reject($goodsReceivedNote, Auth::user(), $validated['reason']);

        return redirect()->route('inventory.receive.show', $goodsReceivedNote)
            ->with('success', 'Goods receive note rejected.');
    }

    public function downloadBulkTemplate(Request $request)
    {
        $businessId = (int) \App\Support\InventoryBusinessContext::effectiveBusinessId();
        $supplierId = $request->filled('supplier_id') ? (int) $request->query('supplier_id') : null;
        $itemIds = $this->resolveTemplateItemIds($request, $businessId);

        if ($supplierId) {
            Supplier::query()
                ->where('business_id', $businessId)
                ->whereKey($supplierId)
                ->firstOrFail();
        }

        $rows = $this->bulkImport->templateRows($businessId, $supplierId, $itemIds);
        $filename = 'grn_lines_template_'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function downloadItemsReference(Request $request)
    {
        $businessId = (int) \App\Support\InventoryBusinessContext::effectiveBusinessId();
        $supplierId = $request->filled('supplier_id') ? (int) $request->query('supplier_id') : null;

        $items = $this->bulkImport->itemsForBusiness($businessId, $supplierId);
        $filename = 'grn_items_reference_'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($items): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'item_code',
                'item_name',
                'sale_unit',
                'sale_price',
                'purchase_price',
                'purchase_price_per_order_unit',
            ]);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $item->code,
                    $item->name,
                    $item->itemUnit?->name ?? '',
                    $item->default_price ?? 0,
                    $item->purchasePricePerSuom(),
                    $item->purchasePricePerOuom(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function bulkImport(Request $request)
    {
        $businessId = (int) \App\Support\InventoryBusinessContext::effectiveBusinessId();

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
            'supplier_id' => 'nullable|exists:suppliers,id',
        ]);

        $supplierId = isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : null;

        if ($supplierId) {
            Supplier::query()
                ->where('business_id', $businessId)
                ->whereKey($supplierId)
                ->firstOrFail();
        }

        $result = $this->bulkImport->parseUpload(
            $request->file('file'),
            $businessId,
            $supplierId
        );

        if ($result['lines'] === [] && $result['errors'] !== []) {
            return response()->json([
                'ok' => false,
                'lines' => [],
                'errors' => $result['errors'],
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'lines' => $result['lines'],
            'errors' => $result['errors'],
            'imported_count' => count($result['lines']),
        ]);
    }

    public function catalogueLines(Request $request)
    {
        $businessId = (int) \App\Support\InventoryBusinessContext::effectiveBusinessId();

        $validated = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
        ]);

        $supplierId = isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : null;

        if ($supplierId) {
            Supplier::query()
                ->where('business_id', $businessId)
                ->whereKey($supplierId)
                ->firstOrFail();
        }

        return response()->json([
            'lines' => $this->bulkImport->catalogueLines($businessId, $supplierId),
        ]);
    }

    private function validateGrn(Request $request): array
    {
        $businessId = \App\Support\InventoryBusinessContext::effectiveBusinessId();
        $itemUnitNames = ItemUnit::query()
            ->where('business_id', $businessId)
            ->pluck('name')
            ->all();

        $business = Business::query()->findOrFail($businessId);
        $supervisorRule = $business->isGrnTechnicalSupervisorRequired()
            ? 'required|exists:users,id'
            : 'nullable|exists:users,id';

        $validated = $request->validate([
            'inventory_order_id' => 'nullable|exists:inventory_orders,id',
            'inventory_purchase_order_id' => 'nullable|exists:inventory_purchase_orders,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'store_id' => 'required|exists:stores,id',
            'date_of_order' => 'required|date',
            'date_of_delivery' => 'required|date|after_or_equal:date_of_order',
            'delivery_note' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'technical_supervisor_user_id' => $supervisorRule,
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|exists:items,id',
            'lines.*.inventory_order_line_id' => 'nullable|exists:inventory_order_lines,id',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.batch_number' => 'nullable|string|max:100',
            'lines.*.expiry_date' => 'nullable|date',
            'lines.*.duom' => ['required', 'string', 'max:50', Rule::in($itemUnitNames)],
            'lines.*.suom' => ['required', 'string', 'max:50', Rule::in($itemUnitNames)],
            'lines.*.purchase_price' => 'required|numeric|min:0',
            'lines.*.sale_units_per_purchase_unit' => 'required|numeric|min:0.0001',
            'lines.*.ordered_quantity' => 'nullable|numeric|min:0',
            'lines.*.condition_status' => 'nullable|in:good,damaged,expired,short',
        ]);

        if (! empty($validated['technical_supervisor_user_id'])) {
            $supervisorValid = User::query()
                ->where('business_id', $businessId)
                ->where('status', 'active')
                ->whereKey((int) $validated['technical_supervisor_user_id'])
                ->exists();

            if (! $supervisorValid) {
                throw ValidationException::withMessages([
                    'technical_supervisor_user_id' => 'The technical supervisor must be active staff of your organisation.',
                ]);
            }
        } else {
            $validated['technical_supervisor_user_id'] = null;
        }

        return $validated;
    }

    private function syncLines(GoodsReceivedNote $grn, array $lines): void
    {
        $grn->lines()->delete();

        foreach ($lines as $line) {
            $item = Item::with('itemUnit')->findOrFail($line['item_id']);

            if ((int) $item->business_id !== (int) $grn->business_id) {
                throw ValidationException::withMessages([
                    'lines' => 'All items must belong to your organisation.',
                ]);
            }

            $itemSuom = $item->itemUnit?->name;

            if (! $itemSuom) {
                throw ValidationException::withMessages([
                    'lines' => "Item \"{$item->name}\" has no sale unit configured.",
                ]);
            }

            if (($line['suom'] ?? '') !== $itemSuom) {
                throw ValidationException::withMessages([
                    'lines' => "Sale unit for \"{$item->name}\" must match the item master ({$itemSuom}).",
                ]);
            }

            $quantity = (float) $line['quantity'];
            $conversion = (float) $line['sale_units_per_purchase_unit'];
            $saleUnits = GoodsReceivedNoteLine::calculateSaleUnitsPurchased($quantity, $conversion);
            $ordered = isset($line['ordered_quantity']) ? (float) $line['ordered_quantity'] : null;
            $variance = $ordered !== null ? round($quantity - $ordered, 4) : null;

            GoodsReceivedNoteLine::create([
                'goods_received_note_id' => $grn->id,
                'item_id' => $item->id,
                'inventory_order_line_id' => $line['inventory_order_line_id'] ?? null,
                'item_name' => $item->name,
                'quantity' => $quantity,
                'ordered_quantity' => $ordered,
                'variance_quantity' => $variance,
                'condition_status' => $line['condition_status'] ?? 'good',
                'batch_number' => $line['batch_number'] ?? null,
                'expiry_date' => $line['expiry_date'] ?? null,
                'duom' => $line['duom'],
                'purchase_price' => $line['purchase_price'],
                'suom' => $itemSuom,
                'sale_units_per_purchase_unit' => $conversion,
                'sale_units_purchased' => $saleUnits,
            ]);
        }
    }

    private function grnFormOptions(int $businessId): array
    {
        $suppliers = Supplier::query()
            ->where('business_id', $businessId)
            ->with(['items:id', 'industry:id,name', 'subCategory:id,name'])
            ->orderBy('name')
            ->get();

        $moduleConfig = InventoryModuleConfig::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->with(['approvers.user'])
            ->first();

        $grnApprovers = $moduleConfig
            ? $moduleConfig->regularApprovers()->with('user')->get()
            : collect();

        $businessUsers = User::query()
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $lastGrnPurchasePricesByItem = $this->service->lastApprovedPurchasePricesPerOuom($businessId);

        $items = Item::where('business_id', $businessId)
            ->where('type', 'good')
            ->with(['itemUnit', 'orderUnit'])
            ->orderBy('name')
            ->get();

        return [
            'suppliers' => $suppliers,
            'supplierCatalog' => SupplierCategorySelection::catalogFromSuppliers($suppliers),
            'supplierIndustries' => SupplierCategorySelection::industryOptionsForBusiness($businessId),
            'supplierSubCategoriesByIndustry' => SupplierCategorySelection::subCategoryOptionsByIndustryForBusiness($businessId),
            'supplierItemIds' => $suppliers->mapWithKeys(fn (Supplier $supplier) => [
                $supplier->id => $supplier->items->pluck('id')->values()->all(),
            ]),
            'stores' => Store::query()
                ->forBusiness($businessId)
                ->with('parent')
                ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('name')
                ->get(),
            'itemUnits' => ItemUnit::query()
                ->where('business_id', $businessId)
                ->orderBy('name')
                ->get(),
            'items' => $items,
            'grnFormItems' => $this->service->itemsForGrnForm($items, $lastGrnPurchasePricesByItem),
            'lastGrnPurchasePricesByItem' => $lastGrnPurchasePricesByItem,
            'grnApprovers' => $grnApprovers,
            'businessUsers' => $businessUsers,
            'grnTechnicalSupervisorRequired' => (bool) (Business::query()
                ->whereKey($businessId)
                ->value('grn_technical_supervisor_required') ?? false),
            'technicalSupervisor' => null,
        ];
    }

    private function authorizeBusiness(GoodsReceivedNote $grn): void
    {
        if ((int) $grn->business_id !== (int) \App\Support\InventoryBusinessContext::effectiveBusinessId()) {
            abort(403);
        }
    }

    /**
     * @return array<int>|null
     */
    private function resolveTemplateItemIds(Request $request, int $businessId): ?array
    {
        if (! $request->filled('item_ids')) {
            return null;
        }

        $raw = $request->query('item_ids');

        if (is_array($raw)) {
            $itemIds = array_values(array_unique(array_filter(array_map('intval', $raw))));
        } else {
            $itemIds = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $raw)))));
        }

        if ($itemIds === []) {
            throw ValidationException::withMessages([
                'item_ids' => 'Select at least one item for the template.',
            ]);
        }

        $validIds = Item::query()
            ->where('business_id', $businessId)
            ->where('type', 'good')
            ->whereIn('id', $itemIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($validIds) !== count($itemIds)) {
            throw ValidationException::withMessages([
                'item_ids' => 'One or more selected items are not valid for your organisation.',
            ]);
        }

        return $validIds;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function storeUploadedFile(Request $request, string $field, string $directory): array
    {
        if (! $request->hasFile($field)) {
            return [null, null];
        }

        $file = $request->file($field);

        return [
            $file->store($directory, 'public'),
            $file->getClientOriginalName(),
        ];
    }
}
