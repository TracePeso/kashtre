<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\InventoryForensicAuditLog;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
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

    public function index(Request $request)
    {
        $businessId = InventoryBusinessContext::effectiveBusinessId();
        $tab = $request->query('tab', 'expired');
        if (! in_array($tab, ['expired', 'written-off'], true)) {
            $tab = 'expired';
        }

        $levels = collect();
        $writeOffs = collect();

        if ($tab === 'expired') {
            $levels = InventoryStockLevel::query()
                ->with(['item:id,name,code', 'store:id,name'])
                ->where('business_id', $businessId)
                ->where('expired_quantity_suom', '>', 0)
                ->orderByDesc('expired_quantity_suom')
                ->limit(200)
                ->get();
        } else {
            $writeOffs = InventoryForensicAuditLog::query()
                ->where('business_id', $businessId)
                ->where('context', 'EXPIRED_ESCROW_WRITEOFF')
                ->orderByDesc('committed_at')
                ->orderByDesc('id')
                ->limit(200)
                ->get();

            $storeNames = Store::query()
                ->whereIn('id', $writeOffs->pluck('store_id')->filter()->unique())
                ->pluck('name', 'id');
            $itemRows = Item::query()
                ->whereIn('id', $writeOffs->pluck('item_id')->filter()->unique())
                ->get(['id', 'name', 'code'])
                ->keyBy('id');
            $actorNames = User::query()
                ->whereIn('id', $writeOffs->pluck('actor_user_id')->filter()->unique())
                ->pluck('name', 'id');

            $writeOffs = $writeOffs->map(function (InventoryForensicAuditLog $row) use ($storeNames, $itemRows, $actorNames) {
                $written = max(0, round((float) $row->old_qty - (float) $row->new_qty, 4));
                $item = $itemRows->get($row->item_id);

                return (object) [
                    'committed_at' => $row->committed_at,
                    'store_name' => $storeNames[$row->store_id] ?? ('Store #'.$row->store_id),
                    'item_name' => $item?->name ?? ('Item #'.$row->item_id),
                    'item_code' => $item?->code,
                    'quantity' => $written,
                    'escrow_before' => (float) $row->old_qty,
                    'escrow_after' => (float) $row->new_qty,
                    'actor_name' => $actorNames[$row->actor_user_id] ?? '—',
                ];
            });
        }

        $expiredCount = InventoryStockLevel::query()
            ->where('business_id', $businessId)
            ->where('expired_quantity_suom', '>', 0)
            ->count();

        $writtenOffCount = InventoryForensicAuditLog::query()
            ->where('business_id', $businessId)
            ->where('context', 'EXPIRED_ESCROW_WRITEOFF')
            ->count();

        $stores = Store::optionsForSelect($businessId);

        return view('inventory.escrow.index', compact(
            'levels',
            'stores',
            'tab',
            'writeOffs',
            'expiredCount',
            'writtenOffCount',
        ));
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
            ->route('inventory.escrow.index', ['tab' => 'written-off'])
            ->with('success', 'Expired escrow written off.');
    }
}
