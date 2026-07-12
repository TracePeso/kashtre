<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\InventoryOrder;
use App\Models\InventorySupplierQuotation;
use App\Models\Supplier;
use App\Services\Inventory\InventorySupplierQuotationService;
use App\Support\InventoryBusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventorySupplierQuotationController extends Controller
{
    use RequiresInventoryModule;

    public function __construct(
        private readonly InventorySupplierQuotationService $service,
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
            'purchaseOrders.supplier',
            'invitedSuppliers',
            'supplier',
        ]);

        $this->service->ensurePrimarySupplierInvited($order);

        $sheet = $this->service->comparisonSheet($order);
        $availableSuppliers = Supplier::query()
            ->where('business_id', $order->business_id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('inventory.orders.quotations-compare', [
            'order' => $order,
            'sheet' => $sheet,
            'availableSuppliers' => $availableSuppliers,
            'rfqSuppliers' => $this->service->suppliersForRfq($order->fresh(['invitedSuppliers', 'supplierQuotations.lines', 'lines', 'supplier'])),
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
            'lines.*.quoted_quantity_suom' => 'required|numeric|min:0',
            'lines.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
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
