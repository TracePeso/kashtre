<?php

namespace App\Http\Controllers;

use App\Models\InventoryDailyConsumption;
use App\Models\InventoryStockLevel;
use Illuminate\Support\Facades\Auth;

class InventoryDailyConsumptionController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            return $this->inventoryMiddleware($request, $next);
        });
    }

    public function index()
    {
        $businessId = (int) Auth::user()->business_id;

        $baseQuery = InventoryDailyConsumption::query()->where('business_id', $businessId);

        $summary = [
            'total_entries' => (clone $baseQuery)->count(),
            'distinct_items' => (clone $baseQuery)->distinct('item_id')->count('item_id'),
            'date_from' => (clone $baseQuery)->min('consumption_date'),
            'date_to' => (clone $baseQuery)->max('consumption_date'),
            'last_30_days' => (clone $baseQuery)
                ->where('consumption_date', '>=', now()->subDays(30)->toDateString())
                ->count(),
        ];

        return view('inventory.consumption.index', compact('summary'));
    }

    public function show(InventoryDailyConsumption $consumption)
    {
        if ((int) $consumption->business_id !== (int) Auth::user()->business_id) {
            abort(403);
        }

        $consumption->load(['item.itemUnit', 'store', 'recordedBy']);

        $stockLevel = InventoryStockLevel::query()
            ->where('business_id', $consumption->business_id)
            ->where('store_id', $consumption->store_id)
            ->where('item_id', $consumption->item_id)
            ->first();

        $recentForItem = InventoryDailyConsumption::query()
            ->where('business_id', $consumption->business_id)
            ->where('store_id', $consumption->store_id)
            ->where('item_id', $consumption->item_id)
            ->where('consumption_date', '>=', $consumption->consumption_date->copy()->subDays(13))
            ->where('consumption_date', '<=', $consumption->consumption_date)
            ->orderByDesc('consumption_date')
            ->get();

        $rolling30Total = InventoryDailyConsumption::query()
            ->where('business_id', $consumption->business_id)
            ->where('store_id', $consumption->store_id)
            ->where('item_id', $consumption->item_id)
            ->where('consumption_date', '>=', $consumption->consumption_date->copy()->subDays(29))
            ->where('consumption_date', '<=', $consumption->consumption_date)
            ->sum('quantity_suom');

        return view('inventory.consumption.show', [
            'consumption' => $consumption,
            'stockLevel' => $stockLevel,
            'recentForItem' => $recentForItem,
            'rolling30Avg' => round((float) $rolling30Total / 30, 4),
        ]);
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
