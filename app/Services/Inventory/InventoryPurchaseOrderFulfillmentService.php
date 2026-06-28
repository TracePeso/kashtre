<?php

namespace App\Services\Inventory;

use App\Models\GoodsReceivedNote;
use App\Models\InventoryOrder;
use App\Models\InventoryOrderLine;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventoryPurchaseOrderLine;

class InventoryPurchaseOrderFulfillmentService
{
    public function applyGrnReceipt(GoodsReceivedNote $grn): void
    {
        $grn->loadMissing(['lines', 'purchaseOrder.lines', 'inventoryOrder.lines']);

        $po = $grn->purchaseOrder;
        $order = $grn->inventoryOrder;

        foreach ($grn->lines as $grnLine) {
            $saleUnits = (float) $grnLine->sale_units_purchased;

            if ($saleUnits <= 0) {
                continue;
            }

            if ($po) {
                $poLine = $this->resolvePoLine($po, $grnLine->inventory_order_line_id, (int) $grnLine->item_id);

                if ($poLine) {
                    $poLine->update([
                        'received_quantity_suom' => round((float) $poLine->received_quantity_suom + $saleUnits, 4),
                    ]);
                }
            }

            if ($order) {
                $orderLine = $this->resolveOrderLine($order, $grnLine->inventory_order_line_id, (int) $grnLine->item_id);

                if ($orderLine) {
                    $orderLine->update([
                        'received_quantity_suom' => round((float) $orderLine->received_quantity_suom + $saleUnits, 4),
                    ]);
                }
            }
        }

        if ($po) {
            $this->refreshPurchaseOrderStatus($po->fresh(['lines']));
        }

        if ($order) {
            app(InventoryOrderFulfillmentService::class)->refreshOrderStatus($order->fresh(['lines']));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function prefillGrnLines(InventoryPurchaseOrder $po): array
    {
        $po->loadMissing(['lines.item.itemUnit', 'lines.item.orderUnit']);

        $lines = [];

        foreach ($po->lines as $line) {
            $remaining = $line->remainingQuantitySuom();

            if ($remaining <= 0) {
                continue;
            }

            $item = $line->item;

            if (! $item) {
                continue;
            }

            $conversion = (float) ($item->suom_per_ouom ?? 0) > 0
                ? (float) $item->suom_per_ouom
                : 1.0;

            $duomQty = max(0.0001, round($remaining / $conversion, 4));
            $unitPriceSuom = (float) ($line->unit_price ?? $item->default_price ?? 0);

            $lines[] = [
                'inventory_order_line_id' => $line->inventory_order_line_id,
                'item_id' => $line->item_id,
                'quantity' => $duomQty,
                'batch_number' => '',
                'expiry_date' => '',
                'duom' => $item->orderUnit?->name ?? $item->itemUnit?->name ?? '',
                'suom' => $item->itemUnit?->name ?? '',
                'purchase_price' => round($unitPriceSuom * $conversion, 2),
                'conversion' => $conversion,
                'remaining_suom' => $remaining,
                'ordered_suom' => (float) $line->quantity_suom,
            ];
        }

        return $lines;
    }

    public function refreshPurchaseOrderStatus(InventoryPurchaseOrder $po): void
    {
        if (! $po->isIssued() && $po->status !== InventoryPurchaseOrder::STATUS_PARTIALLY_RECEIVED) {
            return;
        }

        $lines = $po->lines;

        if ($lines->isEmpty()) {
            return;
        }

        $allFulfilled = true;
        $anyReceived = false;

        foreach ($lines as $line) {
            $ordered = (float) $line->quantity_suom;
            $received = (float) $line->received_quantity_suom;

            if ($received > 0) {
                $anyReceived = true;
            }

            if ($received + 0.0001 < $ordered) {
                $allFulfilled = false;
            }
        }

        if ($allFulfilled) {
            $po->update(['status' => InventoryPurchaseOrder::STATUS_FULFILLED]);

            return;
        }

        if ($anyReceived) {
            $po->update(['status' => InventoryPurchaseOrder::STATUS_PARTIALLY_RECEIVED]);
        }
    }

    private function resolvePoLine(InventoryPurchaseOrder $po, ?int $orderLineId, int $itemId): ?InventoryPurchaseOrderLine
    {
        if ($orderLineId) {
            return $po->lines->firstWhere('inventory_order_line_id', $orderLineId);
        }

        return $po->lines->firstWhere('item_id', $itemId);
    }

    private function resolveOrderLine(InventoryOrder $order, ?int $orderLineId, int $itemId): ?InventoryOrderLine
    {
        if ($orderLineId) {
            return $order->lines->firstWhere('id', $orderLineId);
        }

        return $order->lines->firstWhere('item_id', $itemId);
    }
}
