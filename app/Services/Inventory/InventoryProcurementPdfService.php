<?php

namespace App\Services\Inventory;

use App\Models\InventoryOrder;
use App\Models\InventoryPurchaseOrder;
use App\Services\DocumentPdfService;
use Barryvdh\DomPDF\PDF as PdfInstance;

class InventoryProcurementPdfService
{
    public function __construct(
        private readonly DocumentPdfService $documents,
    ) {
    }

    public function rfqPdf(InventoryOrder $order): PdfInstance
    {
        $order->loadMissing([
            'lines.item.itemUnit',
            'store',
            'supplier',
            'business',
            'createdBy',
            'group',
            'subgroup',
        ]);

        return $this->documents->render(
            'inventory.orders.pdf.rfq',
            compact('order'),
            $order->business,
        );
    }

    public function lpoPdf(InventoryPurchaseOrder $po): PdfInstance
    {
        $po->loadMissing([
            'lines.item.itemUnit',
            'supplier',
            'store',
            'business',
            'inventoryOrder',
            'issuedBy',
        ]);

        return $this->documents->render(
            'inventory.purchase-orders.pdf.lpo',
            compact('po'),
            $po->business,
        );
    }

    public function lpoPdfContent(InventoryPurchaseOrder $po): string
    {
        return $this->lpoPdf($po)->output();
    }

    public function storeRfqDocument(InventoryOrder $order): ?string
    {
        if (! $order->isExternal()) {
            return null;
        }

        $order->loadMissing([
            'lines.item.itemUnit',
            'store',
            'supplier',
            'business',
            'createdBy',
            'group',
            'subgroup',
        ]);

        if ($order->lines->isEmpty()) {
            return null;
        }

        $filename = $order->order_number.'.pdf';
        $path = 'inventory/rfq-documents/'.$order->business_id.'/'.$order->id.'/'.$filename;

        $pdf = $this->rfqPdf($order);
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, $pdf->output());

        $order->update([
            'rfq_document_path' => $path,
            'rfq_document_original_name' => $filename,
        ]);

        return $path;
    }
}
