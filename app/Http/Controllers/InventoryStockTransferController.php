<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Services\Inventory\InventoryStockTransferService;
use App\Support\InventoryBusinessContext;
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
        $businessId = (int) InventoryBusinessContext::effectiveBusinessId();

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
            'storesList' => Store::query()
                ->forBusiness($businessId)
                ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('name')
                ->get()
                ->map(fn (Store $store) => [
                    'id' => $store->id,
                    'label' => $store->selectLabel(),
                    'parent_id' => $store->parent_id,
                ])
                ->values(),
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

        $businessId = (int) InventoryBusinessContext::effectiveBusinessId();
        $this->assertStore($businessId, (int) $validated['from_store_id']);
        $this->assertStore($businessId, (int) $validated['to_store_id']);

        $fromStore = Store::query()->findOrFail((int) $validated['from_store_id']);
        $toStore = Store::query()->findOrFail((int) $validated['to_store_id']);

        if (! $fromStore->canTransferStockTo($toStore)) {
            return back()
                ->withInput()
                ->withErrors([
                    'to_store_id' => 'Child stores cannot transfer stock directly to other child stores. Move stock through the parent distribution store first.',
                ]);
        }

        $transfer = $this->service->createDraft(
            $businessId,
            (int) $validated['from_store_id'],
            (int) $validated['to_store_id'],
            $validated['lines'],
            Auth::user(),
            $validated['notes'] ?? null
        );

        $transfer = $this->service->submit($transfer, Auth::user());

        return redirect()
            ->route('inventory.transfers.show', $transfer)
            ->with('success', 'Transfer request submitted. Awaiting configured approvers.');
    }

    public function show(StockTransfer $transfer)
    {
        $this->authorizeTransfer($transfer);

        $transfer->load([
            'lines.item.itemUnit',
            'fromStore',
            'toStore',
            'inventoryOrder',
            'requestedBy',
            'approvedBy',
            'receivedBy',
            'approvals.approver',
        ]);

        $canApprove = $this->service->userCanApprove($transfer, Auth::user());

        return view('inventory.transfers.show', compact('transfer', 'canApprove'));
    }

    public function submit(StockTransfer $transfer)
    {
        $this->authorizeTransfer($transfer);
        $this->service->submit($transfer, Auth::user());

        return redirect()
            ->route('inventory.transfers.show', $transfer)
            ->with('success', 'Transfer submitted for approval.');
    }

    public function approve(Request $request, StockTransfer $transfer)
    {
        $this->authorizeTransfer($transfer);

        $request->validate(['comment' => 'nullable|string|max:1000']);

        $this->service->approve($transfer, Auth::user(), $request->input('comment'));

        $transfer->refresh();

        $message = $transfer->isApproved()
            ? 'All approvers have signed off. Stock at '.$transfer->fromStore->selectLabel().' is now in transit — destination must confirm receipt.'
            : 'Your approval was recorded. Waiting for the next approver — stock is not moved yet.';

        return redirect()
            ->route('inventory.transfers.show', $transfer)
            ->with('success', $message);
    }

    public function receive(StockTransfer $transfer)
    {
        $this->authorizeTransfer($transfer);
        $this->service->receive($transfer, Auth::user());

        return redirect()
            ->route('inventory.transfers.show', $transfer)
            ->with('success', 'Transfer received. Stock added to receiving store'
                .($transfer->inventory_order_id ? '; linked internal order updated.' : '.'));
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
        if ((int) $transfer->business_id !== (int) InventoryBusinessContext::effectiveBusinessId()) {
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
