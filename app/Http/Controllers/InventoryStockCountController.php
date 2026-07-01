<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Support\InventoryBusinessContext;
use App\Models\InventoryStockCount;
use App\Models\Store;
use App\Services\Inventory\InventoryStockCountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryStockCountController extends Controller
{
    use RequiresInventoryModule;

    public function __construct(
        private readonly InventoryStockCountService $service
    ) {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function index()
    {
        return view('inventory.stock-counts.index');
    }

    public function create()
    {
        $businessId = (int) InventoryBusinessContext::effectiveBusinessId();

        return view('inventory.stock-counts.create', [
            'stores' => Store::optionsForSelect($businessId),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        $businessId = (int) InventoryBusinessContext::effectiveBusinessId();
        $storeId = (int) $validated['store_id'];

        $store = Store::query()
            ->where('business_id', $businessId)
            ->where('id', $storeId)
            ->firstOrFail();

        $count = $this->service->createDraft(
            $businessId,
            $store->id,
            Auth::user(),
            $validated['notes'] ?? null
        );

        return redirect()
            ->route('inventory.stock-counts.show', $count)
            ->with('success', 'Stock count created. Enter physical quantities, then submit for approval.');
    }

    public function show(InventoryStockCount $stockCount)
    {
        $this->authorizeStockCount($stockCount);

        $stockCount->load([
            'lines.item.itemUnit',
            'store',
            'createdBy',
            'submittedBy',
            'finalizedBy',
            'approvals.approver',
        ]);

        $canApprove = $this->service->userCanApprove($stockCount, Auth::user());

        return view('inventory.stock-counts.show', compact('stockCount', 'canApprove'));
    }

    public function submit(InventoryStockCount $stockCount)
    {
        $this->authorizeStockCount($stockCount);

        $this->service->submit($stockCount, Auth::user());

        return redirect()
            ->route('inventory.stock-counts.show', $stockCount)
            ->with('success', 'Stock count submitted for approval.');
    }

    public function approve(Request $request, InventoryStockCount $stockCount)
    {
        $this->authorizeStockCount($stockCount);

        $request->validate(['comment' => 'nullable|string|max:1000']);

        $this->service->approve($stockCount, Auth::user(), $request->input('comment'));

        $stockCount->refresh();

        $message = $stockCount->isApproved()
            ? 'Stock count approved. Physical stock and shrinkage have been recorded.'
            : 'Approval recorded. Awaiting next approver.';

        return redirect()
            ->route('inventory.stock-counts.show', $stockCount)
            ->with('success', $message);
    }

    public function reject(Request $request, InventoryStockCount $stockCount)
    {
        $this->authorizeStockCount($stockCount);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $this->service->reject($stockCount, Auth::user(), $validated['reason']);

        return redirect()
            ->route('inventory.stock-counts.show', $stockCount)
            ->with('success', 'Stock count rejected.');
    }

    private function authorizeStockCount(InventoryStockCount $stockCount): void
    {
        if ((int) $stockCount->business_id !== InventoryBusinessContext::effectiveBusinessId()) {
            abort(403);
        }
    }
}
