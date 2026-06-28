<?php

namespace App\Http\Controllers;

use App\Models\InventoryOrder;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventorySupplierQuotation;
use App\Services\Inventory\InventoryProcurementPdfService;
use App\Services\Inventory\InventoryPurchaseOrderFulfillmentService;
use App\Services\Inventory\InventoryPurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryPurchaseOrderController extends Controller
{
    public function __construct(
        private readonly InventoryPurchaseOrderService $service,
        private readonly InventoryProcurementPdfService $pdfService,
        private readonly InventoryPurchaseOrderFulfillmentService $fulfillmentService,
    ) {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function show(InventoryPurchaseOrder $purchaseOrder)
    {
        $this->authorizePo($purchaseOrder);

        $purchaseOrder->load([
            'lines.item.itemUnit',
            'supplier',
            'store',
            'inventoryOrder',
            'supplierQuotation',
            'issuedBy',
            'goodsReceivedNotes',
        ]);

        return view('inventory.purchase-orders.show', [
            'po' => $purchaseOrder,
        ]);
    }

    public function pdf(InventoryPurchaseOrder $purchaseOrder)
    {
        $this->authorizePo($purchaseOrder);

        return $this->pdfService->lpoPdf($purchaseOrder)->download($purchaseOrder->po_number.'.pdf');
    }

    public function issue(InventoryPurchaseOrder $purchaseOrder)
    {
        $this->authorizePo($purchaseOrder);

        try {
            $this->service->issue($purchaseOrder, Auth::user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('inventory.purchase-orders.show', $purchaseOrder)
            ->with('success', 'LPO issued. Finance and approvers have been emailed a PDF copy.');
    }

    public function receive(InventoryPurchaseOrder $purchaseOrder)
    {
        $this->authorizePo($purchaseOrder);

        if (! $purchaseOrder->canReceiveGoods()) {
            return back()->withErrors(['status' => 'Only issued LPOs can be received against.']);
        }

        $prefillLines = $this->fulfillmentService->prefillGrnLines($purchaseOrder);

        if ($prefillLines === []) {
            return back()->with('warning', 'All items on this LPO have been fully received.');
        }

        return redirect()->route('inventory.receive.create', [
            'inventory_order_id' => $purchaseOrder->inventory_order_id,
            'inventory_purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $purchaseOrder->supplier_id,
        ]);
    }

    public function createFromQuotation(InventorySupplierQuotation $quotation)
    {
        $this->authorizeQuotation($quotation);

        try {
            $po = $this->service->createFromQuotation($quotation, Auth::user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('inventory.purchase-orders.show', $po)
            ->with('success', 'Draft LPO created. Review and issue to notify finance and approvers.');
    }

    private function authorizePo(InventoryPurchaseOrder $po): void
    {
        if ((int) $po->business_id !== (int) Auth::user()->business_id) {
            abort(403);
        }
    }

    private function authorizeQuotation(InventorySupplierQuotation $quotation): void
    {
        if ((int) $quotation->business_id !== (int) Auth::user()->business_id) {
            abort(403);
        }
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
