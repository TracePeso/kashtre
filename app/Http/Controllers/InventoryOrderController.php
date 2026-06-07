<?php

namespace App\Http\Controllers;

use App\Models\InventoryOrder;
use App\Models\Store;
use App\Services\Inventory\InventoryOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryOrderController extends Controller
{
    public function __construct(
        private readonly InventoryOrderService $service
    ) {
        $this->middleware(function ($request, $next) {
            return $this->inventoryMiddleware($request, $next);
        });
    }

    public function index()
    {
        return view('inventory.orders.index');
    }

    public function create()
    {
        $businessId = (int) Auth::user()->business_id;

        return view('inventory.orders.create', [
            'stores' => Store::optionsForSelect($businessId),
            'importanceOptions' => \App\Models\Item::importanceOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'importance_filter' => 'nullable|in:essential,non_essential',
            'budget_mode' => 'nullable|in:days,amount',
            'budget_value' => 'nullable|numeric|min:0',
            'moving_average_days' => 'required|in:15,30,90,180,360',
            'period_of_order_days' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        $businessId = (int) Auth::user()->business_id;

        Store::query()
            ->where('business_id', $businessId)
            ->where('id', $validated['store_id'])
            ->firstOrFail();

        $order = $this->service->createDraft(
            $businessId,
            (int) $validated['store_id'],
            Auth::user(),
            $validated['importance_filter'] ?? null,
            $validated['budget_mode'] ?? null,
            isset($validated['budget_value']) ? (float) $validated['budget_value'] : null,
            (int) $validated['moving_average_days'],
            isset($validated['period_of_order_days']) ? (float) $validated['period_of_order_days'] : null,
            $validated['notes'] ?? null
        );

        $redirect = redirect()->route('inventory.orders.show', $order);

        if ($order->lines()->count() === 0) {
            return $redirect->with('warning', $this->service->explainEmptyOrder($order));
        }

        return $redirect
            ->with('success', 'Order form generated. Review and edit quantities before submitting.');
    }

    public function show(InventoryOrder $order)
    {
        $this->authorizeOrder($order);

        $order->load(['lines.item.itemUnit', 'lines.item.orderUnit', 'store', 'createdBy']);

        $emptyOrderReason = $order->lines->isEmpty()
            ? app(InventoryOrderService::class)->explainEmptyOrder($order)
            : null;

        return view('inventory.orders.show', compact('order', 'emptyOrderReason'));
    }

    public function submit(InventoryOrder $order)
    {
        $this->authorizeOrder($order);

        if (! $order->isDraft()) {
            return back()->withErrors(['status' => 'Only draft orders can be submitted.']);
        }

        $this->service->submit($order);

        return redirect()
            ->route('inventory.orders.show', $order)
            ->with('success', 'Order submitted.');
    }

    public function regenerate(InventoryOrder $order)
    {
        $this->authorizeOrder($order);

        if (! $order->isDraft()) {
            return back()->withErrors(['status' => 'Only draft orders can be regenerated.']);
        }

        $this->service->populateLines($order);

        $order = $order->fresh(['lines.item.itemUnit', 'lines.item.orderUnit', 'store']);

        $redirect = redirect()->route('inventory.orders.show', $order);

        if ($order->lines()->count() === 0) {
            return $redirect->with('warning', $this->service->explainEmptyOrder($order));
        }

        return $redirect->with('success', 'Order lines refreshed from current stock and moving averages.');
    }

    private function authorizeOrder(InventoryOrder $order): void
    {
        if ((int) $order->business_id !== (int) Auth::user()->business_id) {
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
