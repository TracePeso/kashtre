<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\InventoryStockLevel;
use App\Models\Store;
use App\Services\Inventory\InventoryExpiredEscrowService;
use App\Support\InventoryBusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class InventoryEscrowController extends Controller
{
    use RequiresInventoryModule;

    public function __construct()
    {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function index()
    {
        $businessId = InventoryBusinessContext::effectiveBusinessId();

        $levels = InventoryStockLevel::query()
            ->with(['item:id,name,code', 'store:id,name'])
            ->where('business_id', $businessId)
            ->where('expired_quantity_suom', '>', 0)
            ->orderByDesc('expired_quantity_suom')
            ->limit(200)
            ->get();

        $stores = Store::optionsForSelect($businessId);

        return view('inventory.escrow.index', compact('levels', 'stores'));
    }

    public function writeOff(Request $request, InventoryExpiredEscrowService $escrow)
    {
        InventoryBusinessContext::assertWritable();

        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
        ]);

        $businessId = InventoryBusinessContext::effectiveBusinessId();

        Store::query()
            ->where('business_id', $businessId)
            ->whereKey($validated['store_id'])
            ->firstOrFail();

        try {
            $escrow->writeOffEscrow(
                $businessId,
                (int) $validated['store_id'],
                (int) $validated['item_id'],
                (float) $validated['quantity'],
                Auth::user()
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('inventory.escrow.index')
            ->with('success', 'Expired escrow written off.');
    }
}
