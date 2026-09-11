<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\InventoryRfqSupplier;
use App\Services\Inventory\IncomingRfqService;
use App\Services\Inventory\InventoryProcurementPdfService;
use App\Services\Inventory\InventorySupplierQuotationService;
use App\Support\InventoryBusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryIncomingRfqController extends Controller
{
    use RequiresInventoryModule;

    public function __construct(
        private readonly IncomingRfqService $incomingRfqService,
        private readonly InventorySupplierQuotationService $quotationService,
        private readonly InventoryProcurementPdfService $pdfService,
    ) {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function index()
    {
        return view('inventory.incoming-rfqs.index');
    }

    public function show(InventoryRfqSupplier $invitation)
    {
        $entityBusinessId = (int) InventoryBusinessContext::effectiveBusinessId();
        $this->incomingRfqService->authorizeInvitation($invitation, $entityBusinessId);

        $invitation->load([
            'supplier',
            'inventoryOrder.business',
            'inventoryOrder.store',
            'inventoryOrder.lines.item',
            'inventoryOrder.supplierQuotations.lines',
            'inventoryOrder.supplierQuotations.purchaseOrder',
        ]);

        $order = $invitation->inventoryOrder;
        $quotation = $order->supplierQuotations
            ->firstWhere('supplier_id', $invitation->supplier_id);
        $statusLabel = $this->incomingRfqService->statusLabel($invitation);
        $canSubmitQuotation = $this->incomingRfqService->canSubmitQuotation($invitation);

        return view('inventory.incoming-rfqs.show', compact(
            'invitation',
            'order',
            'quotation',
            'statusLabel',
            'canSubmitQuotation',
        ));
    }

    public function pdf(InventoryRfqSupplier $invitation)
    {
        $entityBusinessId = (int) InventoryBusinessContext::effectiveBusinessId();
        $this->incomingRfqService->authorizeInvitation($invitation, $entityBusinessId);

        $order = $invitation->inventoryOrder;

        if (! $order || ! $order->canDownloadRfqPdf() || ! $order->canManageSupplierQuotations()) {
            abort(404, 'RFQ document is not available yet.');
        }

        return $this->pdfService->rfqPdf($order)->download($order->rfqPdfFilename());
    }

    public function storeQuotation(Request $request, InventoryRfqSupplier $invitation)
    {
        $entityBusinessId = (int) InventoryBusinessContext::effectiveBusinessId();
        $this->incomingRfqService->authorizeInvitation($invitation, $entityBusinessId);

        if (! $this->incomingRfqService->canSubmitQuotation($invitation)) {
            return back()->withErrors(['status' => 'This RFQ is not open for quotations.']);
        }

        $order = $invitation->inventoryOrder;

        $validated = $request->validate([
            'reference_number' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1',
            'lines.*.inventory_order_line_id' => 'required|exists:inventory_order_lines,id',
            'lines.*.quoted_quantity_suom' => 'required|numeric|min:0',
            'lines.*.unit_price' => 'required|numeric|min:0',
        ]);

        $validLineIds = $order->lines()->pluck('id')->all();

        foreach ($validated['lines'] as $index => $line) {
            if (! in_array((int) $line['inventory_order_line_id'], $validLineIds, true)) {
                return back()->withInput()->withErrors([
                    "lines.{$index}.inventory_order_line_id" => 'One or more lines do not belong to this RFQ.',
                ]);
            }
        }

        try {
            $this->quotationService->createOrUpdateFromRfq(
                $order,
                (int) $invitation->supplier_id,
                Auth::user(),
                $validated['lines'],
                $validated['reference_number'] ?? null,
                $validated['notes'] ?? null,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('inventory.incoming-rfqs.show', $invitation)
            ->with('success', 'Your quotation has been submitted to '.$order->business?->name.'. Track it under Inventory → Supplied quotations.');
    }
}
