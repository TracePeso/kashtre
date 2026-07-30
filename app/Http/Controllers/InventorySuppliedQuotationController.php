<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\InventorySupplierQuotation;
use App\Services\Inventory\IncomingRfqService;
use App\Services\Inventory\SuppliedQuotationService;
use App\Support\InventoryBusinessContext;

class InventorySuppliedQuotationController extends Controller
{
    use RequiresInventoryModule;

    public function __construct(
        private readonly SuppliedQuotationService $suppliedQuotationService,
        private readonly IncomingRfqService $incomingRfqService,
    ) {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function index()
    {
        return view('inventory.supplied-quotations.index');
    }

    public function show(InventorySupplierQuotation $quotation)
    {
        $entityBusinessId = (int) InventoryBusinessContext::effectiveBusinessId();
        $this->suppliedQuotationService->authorizeQuotation($quotation, $entityBusinessId);

        $quotation->load([
            'supplier',
            'inventoryOrder.business',
            'inventoryOrder.store',
            'inventoryOrder.lines.item',
            'lines.inventoryOrderLine.item',
            'purchaseOrder',
        ]);

        $invitation = $this->suppliedQuotationService->invitationForQuotation($quotation);
        $outcomeLabel = $this->suppliedQuotationService->outcomeLabel($quotation);
        $canUpdate = $invitation && $this->incomingRfqService->canSubmitQuotation($invitation);

        return view('inventory.supplied-quotations.show', compact(
            'quotation',
            'invitation',
            'outcomeLabel',
            'canUpdate',
        ));
    }
}
