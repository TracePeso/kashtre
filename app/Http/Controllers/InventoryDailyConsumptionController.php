<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\Store;
use App\Services\Inventory\InventoryConsumptionQueryService;
use App\Support\InventoryBusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryDailyConsumptionController extends Controller
{
    use RequiresInventoryModule;

    public function __construct()
    {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function index()
    {
        return view('inventory.consumption.index');
    }

    public function showMonth(Request $request, Item $item, string $month, InventoryConsumptionQueryService $queries)
    {
        $businessId = (int) InventoryBusinessContext::effectiveBusinessId();

        if ((int) $item->business_id !== $businessId) {
            abort(403);
        }

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            abort(404);
        }

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
        ]);

        $store = Store::query()
            ->where('business_id', $businessId)
            ->whereKey($validated['store_id'])
            ->firstOrFail();

        $summary = $queries->monthSummary(
            $businessId,
            (int) $store->id,
            (int) $item->id,
            $month
        );

        if ($summary['total_quantity_suom'] <= 0) {
            abort(404, 'No consumption for this item in the selected month.');
        }

        $stockLevel = InventoryStockLevel::query()
            ->where('business_id', $businessId)
            ->where('store_id', $store->id)
            ->where('item_id', $item->id)
            ->first();

        $item->load('itemUnit');

        return view('inventory.consumption.month', [
            'item' => $item,
            'store' => $store,
            'month' => $month,
            'summary' => $summary,
            'stockLevel' => $stockLevel,
        ]);
    }

    public function showDay(Request $request, Item $item, string $date, InventoryConsumptionQueryService $queries)
    {
        $businessId = (int) InventoryBusinessContext::effectiveBusinessId();

        if ((int) $item->business_id !== $businessId) {
            abort(403);
        }

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'month' => 'nullable|date_format:Y-m',
        ]);

        $store = Store::query()
            ->where('business_id', $businessId)
            ->whereKey($validated['store_id'])
            ->firstOrFail();

        $dailyRows = $queries->dailyBreakdown(
            $businessId,
            (int) $store->id,
            (int) $item->id,
            $date,
            $date
        );

        $totalQuantity = (float) ($dailyRows->first()->quantity_suom ?? 0);

        if ($totalQuantity <= 0) {
            abort(404, 'No consumption for this item on the selected day.');
        }

        $month = $validated['month'] ?? substr($date, 0, 7);

        $salesSummary = $queries->salesDaySummary(
            $businessId,
            (int) $store->id,
            (int) $item->id,
            $date
        );

        $item->load('itemUnit');

        return view('inventory.consumption.day', [
            'item' => $item,
            'store' => $store,
            'date' => $date,
            'month' => $month,
            'totalQuantity' => $totalQuantity,
            'salesSummary' => $salesSummary,
        ]);
    }
}
