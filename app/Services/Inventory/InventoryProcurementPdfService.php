<?php

namespace App\Services\Inventory;

use App\Models\InventoryOrder;
use App\Models\InventoryPurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;

class InventoryProcurementPdfService
{
    public function rfqPdf(InventoryOrder $order)
    {
        $order->loadMissing([
            'lines.item.itemUnit',
            'store',
            'business',
            'createdBy',
            'group',
            'subgroup',
        ]);

        return Pdf::loadView('inventory.orders.pdf.rfq', compact('order'))
            ->setPaper('a4', 'portrait');
    }

    public function lpoPdf(InventoryPurchaseOrder $po)
    {
        $po->loadMissing([
            'lines.item.itemUnit',
            'supplier',
            'store',
            'business',
            'inventoryOrder',
            'issuedBy',
        ]);

        return Pdf::loadView('inventory.purchase-orders.pdf.lpo', compact('po'))
            ->setPaper('a4', 'portrait');
    }

    public function lpoPdfContent(InventoryPurchaseOrder $po): string
    {
        return $this->lpoPdf($po)->output();
    }
}
