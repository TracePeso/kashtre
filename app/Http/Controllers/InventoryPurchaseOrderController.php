<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\InventoryOrder;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventorySupplierQuotation;
use App\Services\Inventory\InventoryProcurementPdfService;
use App\Services\Inventory\InventoryPurchaseOrderFulfillmentService;
use App\Services\Inventory\InventoryPurchaseOrderService;
use App\Support\InventoryBusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryPurchaseOrderController extends Controller
{
    use RequiresInventoryModule;

    public function __construct(
        private readonly InventoryPurchaseOrderService $service,
        private readonly InventoryProcurementPdfService $pdfService,
        private readonly InventoryPurchaseOrderFulfillmentService $fulfillmentService,
    ) {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function index()
    {
        return view('inventory.purchase-orders.index');
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
            ->with('success', 'Draft LPO created. Review and issue to notify finance, approvers, and the supplier.');
    }

    /**
     * Split accepted quotations into individual LPOs (one per supplier).
     */
    public function generateAccepted(InventoryOrder $order)
    {
        if ((int) $order->business_id !== (int) InventoryBusinessContext::effectiveBusinessId()) {
            abort(403);
        }

        $order->load('supplierQuotations.purchaseOrder');
        $created = 0;
        $errors = [];

        foreach ($order->supplierQuotations as $quotation) {
            if (! $quotation->isAccepted() || $quotation->purchaseOrder) {
                continue;
            }

            try {
                $this->service->createFromQuotation($quotation, Auth::user());
                $created++;
            } catch (\Illuminate\Validation\ValidationException $e) {
                $errors[] = ($quotation->supplier?->name ?? 'Supplier').': '.collect($e->errors())->flatten()->first();
            }
        }

        if ($created < 1) {
            $existing = $order->purchaseOrders()->count();

            if ($existing > 0) {
                return redirect()
                    ->route('inventory.orders.show', $order)
                    ->with('success', "LPOs already exist for this RFQ ({$existing}). Open them below, or from Inventory → Local purchase orders.");
            }

            return redirect()
                ->route('inventory.orders.quotations.compare', $order)
                ->withErrors(['status' => $errors[0] ?? 'Accept at least one quotation before generating LPOs.']);
        }

        return redirect()
            ->route('inventory.orders.show', $order)
            ->with('success', "Generated {$created} LPO(s). Open them below to review and issue.".($errors !== [] ? ' Some skipped: '.implode(' ', $errors) : ''));
    }

    private function authorizePo(InventoryPurchaseOrder $po): void
    {
        if ((int) $po->business_id !== (int) InventoryBusinessContext::effectiveBusinessId()) {
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
