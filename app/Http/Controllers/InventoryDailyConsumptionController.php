<?php

namespace App\Http\Controllers;

use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\Store;
use App\Services\Inventory\InventoryStockAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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
        return view('inventory.consumption.index');
    }

    public function create()
    {
        $businessId = (int) Auth::user()->business_id;

        $items = Item::query()
            ->where('business_id', $businessId)
            ->where('type', 'good')
            ->with('itemUnit')
            ->orderBy('name')
            ->get();

        $stockByStore = InventoryStockLevel::query()
            ->where('business_id', $businessId)
            ->where('quantity_suom', '>', 0)
            ->with(['item' => fn ($q) => $q->select('id', 'name', 'code', 'uom_id'), 'item.itemUnit:id,name'])
            ->get()
            ->groupBy('store_id')
            ->map(fn ($levels) => $levels->map(fn ($level) => [
                'item_id' => $level->item_id,
                'name' => $level->item->name,
                'code' => $level->item->code,
                'suom' => $level->item->itemUnit?->name,
                'system_qty' => (float) $level->quantity_suom,
            ])->values())
            ->all();

        return view('inventory.consumption.create', [
            'stores' => Store::optionsForSelect($businessId),
            'items' => $items,
            'stockByStore' => $stockByStore,
        ]);
    }

    public function store(Request $request, InventoryStockAnalyticsService $analytics)
    {
        $request->merge([
            'lines' => collect($request->input('lines', []))
                ->filter(fn ($line) => ! empty($line['item_id']))
                ->values()
                ->all(),
        ]);

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'consumption_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|exists:items,id',
            'lines.*.quantity_suom' => 'nullable|numeric|min:0',
        ]);

        $lines = collect($validated['lines'])
            ->filter(fn (array $line): bool => (float) ($line['quantity_suom'] ?? 0) > 0)
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'Enter a consumed quantity for at least one item.',
            ]);
        }

        $itemIds = $lines->pluck('item_id');

        if ($itemIds->count() !== $itemIds->unique()->count()) {
            throw ValidationException::withMessages([
                'lines' => 'Each item can only appear once per submission.',
            ]);
        }

        $businessId = (int) Auth::user()->business_id;
        $storeId = (int) $validated['store_id'];

        Store::query()
            ->where('business_id', $businessId)
            ->where('id', $storeId)
            ->firstOrFail();

        $validItemCount = Item::query()
            ->where('business_id', $businessId)
            ->where('type', 'good')
            ->whereIn('id', $itemIds)
            ->count();

        if ($validItemCount !== $itemIds->count()) {
            throw ValidationException::withMessages([
                'lines' => 'All selected items must be goods belonging to your organisation.',
            ]);
        }

        $recorded = $analytics->recordManyConsumptions(
            $businessId,
            $storeId,
            $validated['consumption_date'],
            $lines->all(),
            Auth::id(),
            $validated['notes'] ?? null
        );

        return redirect()
            ->route('inventory.consumption.index')
            ->with('success', "{$recorded} consumption ".($recorded === 1 ? 'entry' : 'entries').' recorded. Moving averages updated.');
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
