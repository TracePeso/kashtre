<?php

namespace App\Http\Controllers;

use App\Models\InventoryOrder;
use App\Models\InventorySupplierQuotation;
use App\Services\Inventory\InventorySupplierQuotationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventorySupplierQuotationController extends Controller
{
    public function __construct(
        private readonly InventorySupplierQuotationService $service,
    ) {
        $this->middleware($this->inventoryMiddleware(...));
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
            ->route('inventory.orders.show', $order)
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
            ->route('inventory.orders.show', $quotation->inventoryOrder)
            ->with('success', 'Supplier quotation accepted. You can now generate an LPO.');
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
            ->route('inventory.orders.show', $quotation->inventoryOrder)
            ->with('success', 'Supplier quotation rejected.');
    }

    private function authorizeOrder(InventoryOrder $order): void
    {
        if ((int) $order->business_id !== (int) Auth::user()->business_id) {
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
