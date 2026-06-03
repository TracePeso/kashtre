<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteLine;
use App\Models\InventoryModuleConfig;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Store;
use App\Models\Supplier;
use App\Services\GoodsReceivedNoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GoodsReceivedNoteController extends Controller
{
    public function __construct(private GoodsReceivedNoteService $service)
    {
        $this->middleware(function ($request, $next) {
            $user = auth()->user();

            if ($user->business_id === 1) {
                abort(403, 'Inventory receive is only available to business users.');
            }

            $enabled = InventoryModuleConfig::query()
                ->where('business_id', $user->business_id)
                ->where('is_active', true)
                ->exists();

            if (! $enabled) {
                abort(403, 'The inventory module is not enabled for your organisation.');
            }

            return $next($request);
        });
    }

    public function create()
    {
        $businessId = Auth::user()->business_id;

        return view('inventory.receive.create', [
            'suppliers' => Supplier::where('business_id', $businessId)->orderBy('name')->get(),
            'stores' => Store::query()
                ->forBusiness($businessId)
                ->with('parent')
                ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('name')
                ->get(),
            'itemUnits' => ItemUnit::query()
                ->where('business_id', $businessId)
                ->orderBy('name')
                ->get(),
            'items' => Item::where('business_id', $businessId)
                ->where('type', 'good')
                ->with('itemUnit')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateGrn($request);

        $user = Auth::user();
        $businessId = $user->business_id;
        $action = $request->input('action', 'draft');

        $grn = DB::transaction(function () use ($validated, $request, $user, $businessId, $action) {
            $deliveryPath = null;
            $deliveryOriginal = null;

            if ($request->hasFile('delivery_note')) {
                $file = $request->file('delivery_note');
                $deliveryOriginal = $file->getClientOriginalName();
                $deliveryPath = $file->store('inventory/delivery-notes', 'public');
            }

            $leadTime = $this->service->calculateLeadTimeDays(
                $validated['date_of_order'],
                $validated['date_of_delivery']
            );

            $grn = GoodsReceivedNote::create([
                'grn_number' => GoodsReceivedNote::generateNumber($businessId),
                'business_id' => $businessId,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'store_id' => $validated['store_id'] ?? null,
                'date_of_order' => $validated['date_of_order'],
                'date_of_delivery' => $validated['date_of_delivery'],
                'lead_time_days' => $leadTime,
                'delivery_note_path' => $deliveryPath,
                'delivery_note_original_name' => $deliveryOriginal,
                'status' => GoodsReceivedNote::STATUS_DRAFT,
                'entry_by_user_id' => $user->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->syncLines($grn, $validated['lines']);

            return $grn;
        });

        if ($action === 'submit') {
            $this->service->submit($grn, $user);

            return redirect()->route('inventory.receive.show', $grn)
                ->with('success', 'GRN submitted for approval.');
        }

        return redirect()->route('inventory.receive.show', $grn)
            ->with('success', 'GRN saved as draft.');
    }

    public function show(GoodsReceivedNote $goodsReceivedNote)
    {
        $this->authorizeBusiness($goodsReceivedNote);

        $goodsReceivedNote->load([
            'lines.item',
            'supplier',
            'store',
            'entryBy',
            'submittedBy',
            'approvals.approver',
        ]);

        $canApprove = $this->service->userCanApprove($goodsReceivedNote, Auth::user());

        return view('inventory.receive.show', compact('goodsReceivedNote', 'canApprove'));
    }

    public function submit(GoodsReceivedNote $goodsReceivedNote)
    {
        $this->authorizeBusiness($goodsReceivedNote);

        $this->service->submit($goodsReceivedNote, Auth::user());

        return redirect()->route('inventory.receive.show', $goodsReceivedNote)
            ->with('success', 'GRN submitted for approval.');
    }

    public function approve(Request $request, GoodsReceivedNote $goodsReceivedNote)
    {
        $this->authorizeBusiness($goodsReceivedNote);

        $request->validate(['comment' => 'nullable|string|max:1000']);

        $this->service->approve($goodsReceivedNote, Auth::user(), $request->input('comment'));

        $goodsReceivedNote->refresh();

        $message = $goodsReceivedNote->isApproved()
            ? 'GRN approved. Stock levels have been updated.'
            : 'Approval recorded. Awaiting next approver.';

        return redirect()->route('inventory.receive.show', $goodsReceivedNote)
            ->with('success', $message);
    }

    public function reject(Request $request, GoodsReceivedNote $goodsReceivedNote)
    {
        $this->authorizeBusiness($goodsReceivedNote);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $this->service->reject($goodsReceivedNote, Auth::user(), $validated['reason']);

        return redirect()->route('inventory.receive.show', $goodsReceivedNote)
            ->with('success', 'GRN rejected.');
    }

    private function validateGrn(Request $request): array
    {
        $businessId = Auth::user()->business_id;
        $itemUnitNames = ItemUnit::query()
            ->where('business_id', $businessId)
            ->pluck('name')
            ->all();

        return $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'store_id' => 'required|exists:stores,id',
            'date_of_order' => 'required|date',
            'date_of_delivery' => 'required|date|after_or_equal:date_of_order',
            'delivery_note' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|exists:items,id',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.batch_number' => 'nullable|string|max:100',
            'lines.*.expiry_date' => 'nullable|date',
            'lines.*.duom' => ['required', 'string', 'max:50', Rule::in($itemUnitNames)],
            'lines.*.suom' => ['required', 'string', 'max:50', Rule::in($itemUnitNames)],
            'lines.*.purchase_price' => 'required|numeric|min:0',
            'lines.*.sale_units_per_purchase_unit' => 'required|numeric|min:0.0001',
        ]);
    }

    private function syncLines(GoodsReceivedNote $grn, array $lines): void
    {
        $grn->lines()->delete();

        foreach ($lines as $line) {
            $item = Item::with('itemUnit')->findOrFail($line['item_id']);

            if ((int) $item->business_id !== (int) $grn->business_id) {
                throw ValidationException::withMessages([
                    'lines' => 'All items must belong to your organisation.',
                ]);
            }

            $quantity = (float) $line['quantity'];
            $conversion = (float) $line['sale_units_per_purchase_unit'];
            $saleUnits = GoodsReceivedNoteLine::calculateSaleUnitsPurchased($quantity, $conversion);

            GoodsReceivedNoteLine::create([
                'goods_received_note_id' => $grn->id,
                'item_id' => $item->id,
                'item_name' => $item->name,
                'quantity' => $quantity,
                'batch_number' => $line['batch_number'] ?? null,
                'expiry_date' => $line['expiry_date'] ?? null,
                'duom' => $line['duom'],
                'purchase_price' => $line['purchase_price'],
                'suom' => $line['suom'],
                'sale_units_per_purchase_unit' => $conversion,
                'sale_units_purchased' => $saleUnits,
            ]);
        }
    }

    private function authorizeBusiness(GoodsReceivedNote $grn): void
    {
        if ((int) $grn->business_id !== (int) Auth::user()->business_id) {
            abort(403);
        }
    }
}
