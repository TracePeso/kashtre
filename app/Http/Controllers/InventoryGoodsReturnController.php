<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\GoodsReturnNote;
use App\Models\Item;
use App\Models\Store;
use App\Models\Supplier;
use App\Services\Inventory\InventoryGoodsReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryGoodsReturnController extends Controller
{
    use RequiresInventoryModule;

    public function __construct(
        private readonly InventoryGoodsReturnService $service
    ) {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function index()
    {
        return view('inventory.returns.index');
    }

    public function create()
    {
        $businessId = (int) Auth::user()->business_id;

        return view('inventory.returns.create', [
            'stores' => Store::optionsForSelect($businessId),
            'suppliers' => Supplier::query()->where('business_id', $businessId)->orderBy('name')->pluck('name', 'id'),
            'reasonOptions' => GoodsReturnNote::reasonOptions(),
            'items' => Item::query()
                ->where('business_id', $businessId)
                ->where('type', 'good')
                ->with('itemUnit')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'return_date' => 'required|date',
            'reason_code' => 'nullable|in:'.implode(',', array_keys(GoodsReturnNote::reasonOptions())),
            'notes' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|exists:items,id',
            'lines.*.quantity_suom' => 'required|numeric|min:0.0001',
            'lines.*.batch_number' => 'nullable|string|max:100',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        $businessId = (int) Auth::user()->business_id;

        Store::query()->where('business_id', $businessId)->where('id', $validated['store_id'])->firstOrFail();

        $note = $this->service->createDraft(
            $businessId,
            (int) $validated['store_id'],
            isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
            $validated['return_date'],
            $validated['reason_code'] ?? null,
            Auth::user(),
            $validated['notes'] ?? null
        );

        $this->service->syncLines($note, $validated['lines']);

        return redirect()
            ->route('inventory.returns.show', $note)
            ->with('success', 'Goods return draft created.');
    }

    public function show(GoodsReturnNote $returnNote)
    {
        $this->authorizeReturn($returnNote);

        $returnNote->load(['lines.item.itemUnit', 'store', 'supplier', 'createdBy']);

        return view('inventory.returns.show', ['returnNote' => $returnNote]);
    }

    public function submit(GoodsReturnNote $returnNote)
    {
        $this->authorizeReturn($returnNote);
        $this->service->submit($returnNote, Auth::user());

        return redirect()
            ->route('inventory.returns.show', $returnNote)
            ->with('success', 'Goods return submitted. System stock has been reduced.');
    }

    private function authorizeReturn(GoodsReturnNote $returnNote): void
    {
        if ((int) $returnNote->business_id !== (int) Auth::user()->business_id) {
            abort(403);
        }
    }
}
