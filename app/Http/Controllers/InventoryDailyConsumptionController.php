<?php

namespace App\Http\Controllers;

use App\Exports\InventoryConsumptionExport;
use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\Store;
use App\Services\Inventory\InventoryConsumptionQueryService;
use App\Support\BusinessBranding;
use App\Support\InventoryBusinessContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class InventoryDailyConsumptionController extends Controller
{
    use RequiresInventoryModule;

    public function __construct()
    {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function index()
    {
        return view('inventory.consumption.index');
    }

    public function exportExcel(Request $request, InventoryConsumptionQueryService $queries): BinaryFileResponse
    {
        $payload = $this->exportPayload($request, $queries);
        $filename = $this->exportFilename($payload['meta'], 'xlsx');

        return Excel::download(
            new InventoryConsumptionExport($payload['rows'], $payload['meta']),
            $filename
        );
    }

    public function exportPdf(Request $request, InventoryConsumptionQueryService $queries): Response
    {
        $payload = $this->exportPayload($request, $queries);
        $filename = $this->exportFilename($payload['meta'], 'pdf');
        $branding = BusinessBranding::for(
            \App\Models\Business::query()->find($payload['business_id'])
        );

        return Pdf::loadView('inventory.consumption.pdf', [
            'rows' => $payload['rows'],
            'meta' => $payload['meta'],
            'branding' => $branding,
        ])
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    /**
     * @return array{
     *     business_id: int,
     *     rows: \Illuminate\Support\Collection,
     *     meta: array<string, mixed>
     * }
     */
    private function exportPayload(Request $request, InventoryConsumptionQueryService $queries): array
    {
        $businessId = (int) InventoryBusinessContext::effectiveBusinessId();

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'item_id' => 'nullable|exists:items,id',
            'period_preset' => 'nullable|string|in:7,10,30,90,custom',
            'date_from' => 'nullable|date',
            'date_until' => 'nullable|date',
        ]);

        $store = Store::query()
            ->where('business_id', $businessId)
            ->whereKey($validated['store_id'])
            ->firstOrFail();

        $itemId = isset($validated['item_id']) ? (int) $validated['item_id'] : null;
        $itemName = null;

        if ($itemId) {
            $item = Item::query()
                ->where('business_id', $businessId)
                ->whereKey($itemId)
                ->firstOrFail();
            $itemName = trim($item->name.($item->code ? " ({$item->code})" : ''));
        }

        [$from, $until] = $this->resolveExportPeriodBounds($validated, $queries);

        $summary = $queries->periodSummary($businessId, $from, $until, (int) $store->id, $itemId);

        $rows = $queries->itemStoreDailySummariesQuery(
            $businessId,
            $from,
            $until,
            (int) $store->id,
            $itemId,
        )
            ->orderByDesc('idc.consumption_date')
            ->orderBy('items.name')
            ->get();

        $periodLabel = Carbon::parse($from)->format('M j').' – '.Carbon::parse($until)->format('M j, Y');

        return [
            'business_id' => $businessId,
            'rows' => $rows,
            'meta' => [
                'store_name' => $store->name,
                'item_name' => $itemName,
                'from' => $from,
                'until' => $until,
                'period_days' => $summary['period_days'],
                'period_label' => $periodLabel,
                'total_quantity_suom' => $summary['total_quantity_suom'],
                'distinct_items' => $summary['distinct_items'],
                'item_day_rows' => $summary['item_day_rows'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: string, 1: string}
     */
    private function resolveExportPeriodBounds(array $validated, InventoryConsumptionQueryService $queries): array
    {
        $preset = $validated['period_preset'] ?? '10';

        if ($preset === 'custom') {
            $until = $validated['date_until'] ?? now()->toDateString();
            $from = $validated['date_from'] ?? $until;

            if (Carbon::parse($from)->gt(Carbon::parse($until))) {
                [$from, $until] = [$until, $from];
            }

            return [
                Carbon::parse($from)->toDateString(),
                Carbon::parse($until)->toDateString(),
            ];
        }

        if (! in_array($preset, ['7', '10', '30', '90'], true)) {
            throw ValidationException::withMessages([
                'period_preset' => 'Invalid period preset.',
            ]);
        }

        return $queries->recentDaysBounds((int) $preset);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function exportFilename(array $meta, string $extension): string
    {
        $storeSlug = str($meta['store_name'])->slug()->toString() ?: 'store';
        $from = str_replace('-', '', $meta['from']);
        $until = str_replace('-', '', $meta['until']);

        return "consumption-{$storeSlug}-{$from}-{$until}.{$extension}";
    }

    public function showMonth(Request $request, Item $item, string $month, InventoryConsumptionQueryService $queries)
    {
        $businessId = (int) InventoryBusinessContext::effectiveBusinessId();

        if ((int) $item->business_id !== $businessId) {
            abort(403);
        }

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            abort(404);
        }

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
        ]);

        $store = Store::query()
            ->where('business_id', $businessId)
            ->whereKey($validated['store_id'])
            ->firstOrFail();

        $summary = $queries->monthSummary(
            $businessId,
            (int) $store->id,
            (int) $item->id,
            $month
        );

        if ($summary['total_quantity_suom'] <= 0) {
            abort(404, 'No consumption for this item in the selected month.');
        }

        $stockLevel = InventoryStockLevel::query()
            ->where('business_id', $businessId)
            ->where('store_id', $store->id)
            ->where('item_id', $item->id)
            ->first();

        $item->load('itemUnit');

        return view('inventory.consumption.month', [
            'item' => $item,
            'store' => $store,
            'month' => $month,
            'summary' => $summary,
            'stockLevel' => $stockLevel,
        ]);
    }

    public function showDay(Request $request, Item $item, string $date, InventoryConsumptionQueryService $queries)
    {
        $businessId = (int) InventoryBusinessContext::effectiveBusinessId();

        if ((int) $item->business_id !== $businessId) {
            abort(403);
        }

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'month' => 'nullable|date_format:Y-m',
        ]);

        $store = Store::query()
            ->where('business_id', $businessId)
            ->whereKey($validated['store_id'])
            ->firstOrFail();

        $dailyRows = $queries->dailyBreakdown(
            $businessId,
            (int) $store->id,
            (int) $item->id,
            $date,
            $date
        );

        $totalQuantity = (float) ($dailyRows->first()->quantity_suom ?? 0);

        if ($totalQuantity <= 0) {
            abort(404, 'No consumption for this item on the selected day.');
        }

        $month = $validated['month'] ?? substr($date, 0, 7);

        $salesSummary = $queries->salesDaySummary(
            $businessId,
            (int) $store->id,
            (int) $item->id,
            $date
        );

        $item->load('itemUnit');

        return view('inventory.consumption.day', [
            'item' => $item,
            'store' => $store,
            'date' => $date,
            'month' => $month,
            'totalQuantity' => $totalQuantity,
            'salesSummary' => $salesSummary,
        ]);
    }
}
