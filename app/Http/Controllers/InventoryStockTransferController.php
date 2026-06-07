<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Services\Inventory\InventoryStockTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryStockTransferController extends Controller
{
    use RequiresInventoryModule;

    public function __construct(
        private readonly InventoryStockTransferService $service
    ) {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function index()
    {
        return view('inventory.transfers.index');
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

        return view('inventory.transfers.create', [
            'stores' => Store::optionsForSelect($businessId),
            'items' => $items,
            'stockByStore' => $stockByStore,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_store_id' => 'required|exists:stores,id',
            'to_store_id' => 'required|exists:stores,id|different:from_store_id',
            'notes' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|exists:items,id',
            'lines.*.quantity_suom' => 'required|numeric|min:0.0001',
        ]);

        $businessId = (int) Auth::user()->business_id;
        $this->assertStore($businessId, (int) $validated['from_store_id']);
        $this->assertStore($businessId, (int) $validated['to_store_id']);

        $transfer = $this->service->createDraft(
            $businessId,
            (int) $validated['from_store_id'],
            (int) $validated['to_store_id'],
            $validated['lines'],
            Auth::user(),
            $validated['notes'] ?? null
        );

        return redirect()
            ->route('inventory.transfers.show', $transfer)
            ->with('success', 'Transfer request created. Submit when ready.');
    }

    public function show(StockTransfer $transfer)
    {
        $this->authorizeTransfer($transfer);

        $transfer->load(['lines.item.itemUnit', 'fromStore', 'toStore', 'requestedBy', 'approvedBy', 'receivedBy']);

        return view('inventory.transfers.show', compact('transfer'));
    }

    public function submit(StockTransfer $transfer)
    {
        $this->authorizeTransfer($transfer);
        $this->service->submit($transfer, Auth::user());

        return redirect()
            ->route('inventory.transfers.show', $transfer)
            ->with('success', 'Transfer submitted to dispatch store for approval.');
    }

    public function approve(StockTransfer $transfer)
    {
        $this->authorizeTransfer($transfer);
        $this->service->approve($transfer, Auth::user());

        return redirect()
            ->route('inventory.transfers.show', $transfer)
            ->with('success', 'Transfer approved. Stock deducted from dispatch store.');
    }

    public function receive(StockTransfer $transfer)
    {
        $this->authorizeTransfer($transfer);
        $this->service->receive($transfer, Auth::user());

        return redirect()
            ->route('inventory.transfers.show', $transfer)
            ->with('success', 'Transfer received. Stock added to receiving store.');
    }

    public function reject(Request $request, StockTransfer $transfer)
    {
        $this->authorizeTransfer($transfer);

        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        $this->service->reject($transfer, Auth::user(), $validated['reason']);

        return redirect()
            ->route('inventory.transfers.show', $transfer)
            ->with('success', 'Transfer rejected.');
    }

    private function authorizeTransfer(StockTransfer $transfer): void
    {
        if ((int) $transfer->business_id !== (int) Auth::user()->business_id) {
            abort(403);
        }
    }

    private function assertStore(int $businessId, int $storeId): void
    {
        Store::query()
            ->where('business_id', $businessId)
            ->where('id', $storeId)
            ->firstOrFail();
    }
}
