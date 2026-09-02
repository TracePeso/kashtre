<?php

namespace App\Services\Inventory;

use App\Models\GoodsReceivedNote;
use App\Models\InventoryOrder;
use App\Models\InventoryOrderLine;

class InventoryOrderFulfillmentService
{
    public function applyGrnReceipt(GoodsReceivedNote $grn): void
    {
        $grn->loadMissing(['lines', 'inventoryOrder.lines']);

        $order = $grn->inventoryOrder;

        if (! $order) {
            return;
        }

        foreach ($grn->lines as $grnLine) {
            $saleUnits = (float) $grnLine->sale_units_purchased;

            if ($saleUnits <= 0) {
                continue;
            }

            $orderLine = $this->resolveOrderLine($order, $grnLine->inventory_order_line_id, (int) $grnLine->item_id);

            if (! $orderLine) {
                continue;
            }

            $orderLine->update([
                'received_quantity_suom' => round((float) $orderLine->received_quantity_suom + $saleUnits, 4),
            ]);
        }

        $this->refreshOrderStatus($order->fresh(['lines']));
    }

    /**
     * @return array<int, array{supplier_id: ?int, supplier_name: string, lines_count: int, remaining_suom: float}>
     */
    public function receiptOptionsBySupplier(InventoryOrder $order): array
    {
        $order->loadMissing(['lines.item', 'lines.supplier']);

        $groups = [];

        foreach ($order->lines as $line) {
            $remaining = $this->remainingQuantitySuom($line);

            if ($remaining <= 0) {
                continue;
            }

            $supplierId = $line->supplier_id;
            $key = $supplierId ?? 0;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'supplier_id' => $supplierId,
                    'supplier_name' => $line->supplier?->name ?? 'No supplier assigned',
                    'lines_count' => 0,
                    'remaining_suom' => 0.0,
                ];
            }

            $groups[$key]['lines_count']++;
            $groups[$key]['remaining_suom'] += $remaining;
        }

        return array_values($groups);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function prefillGrnLines(InventoryOrder $order, ?int $supplierId = null): array
    {
        $order->loadMissing(['lines.item.itemUnit', 'lines.item.orderUnit']);

        $lines = [];

        foreach ($order->lines as $line) {
            if ($supplierId !== null && (int) ($line->supplier_id ?? 0) !== $supplierId) {
                continue;
            }

            $remaining = $this->remainingQuantitySuom($line);

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

            $lines[] = [
                'inventory_order_line_id' => $line->id,
                'item_id' => $line->item_id,
                'quantity' => $duomQty,
                'batch_number' => '',
                'expiry_date' => '',
                'duom' => $item->orderUnit?->name ?? $item->itemUnit?->name ?? '',
                'suom' => $item->itemUnit?->name ?? '',
                'conversion' => $conversion,
                'remaining_suom' => $remaining,
                'ordered_suom' => (float) $line->order_quantity_suom,
            ];
        }

        return $lines;
    }

    public function remainingQuantitySuom(InventoryOrderLine $line): float
    {
        return max(0, round((float) $line->order_quantity_suom - (float) $line->received_quantity_suom, 4));
    }

    public function refreshOrderStatus(InventoryOrder $order): void
    {
        if (! in_array($order->status, [
            InventoryOrder::STATUS_PO_ISSUED,
            InventoryOrder::STATUS_PARTIALLY_RECEIVED,
            InventoryOrder::STATUS_FULFILLED,
        ], true)) {
            return;
        }

        $lines = $order->lines;

        if ($lines->isEmpty()) {
            return;
        }

        $allFulfilled = true;
        $anyReceived = false;

        foreach ($lines as $line) {
            $ordered = (float) $line->order_quantity_suom;
            $received = (float) $line->received_quantity_suom;

            if ($received > 0) {
                $anyReceived = true;
            }

            if ($received + 0.0001 < $ordered) {
                $allFulfilled = false;
            }
        }

        if ($allFulfilled) {
            $order->update(['status' => InventoryOrder::STATUS_FULFILLED]);

            return;
        }

        if ($anyReceived) {
            $order->update(['status' => InventoryOrder::STATUS_PARTIALLY_RECEIVED]);
        }
    }

    private function resolveOrderLine(InventoryOrder $order, ?int $orderLineId, int $itemId): ?InventoryOrderLine
    {
        if ($orderLineId) {
            return $order->lines->firstWhere('id', $orderLineId);
        }

        return $order->lines->firstWhere('item_id', $itemId);
    }
}
