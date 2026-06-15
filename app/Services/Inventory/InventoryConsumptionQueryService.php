<?php

namespace App\Services\Inventory;

use App\Models\InventoryDailyConsumption;
use App\Models\InventoryConsumptionEvent;
use App\Models\Sale;
use App\Models\Store;
use App\Support\HourlyConsumptionDistribution;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryConsumptionQueryService
{
    /**
     * @return array{
     *     from: string,
     *     until: string,
     *     period_days: int,
     *     item_store_pairs: int,
     *     distinct_items: int,
     *     total_quantity_suom: float
     * }
     */
    public function periodSummary(int $businessId, string $from, string $until): array
    {
        $periodDays = $this->periodDays($from, $until);

        $row = InventoryDailyConsumption::query()
            ->where('business_id', $businessId)
            ->whereDate('consumption_date', '>=', $from)
            ->whereDate('consumption_date', '<=', $until)
            ->selectRaw('COUNT(DISTINCT CONCAT(item_id, "-", store_id)) as item_store_pairs')
            ->selectRaw('COUNT(DISTINCT item_id) as distinct_items')
            ->selectRaw('COALESCE(SUM(quantity_suom), 0) as total_quantity_suom')
            ->first();

        return [
            'from' => $from,
            'until' => $until,
            'period_days' => $periodDays,
            'item_store_pairs' => (int) ($row->item_store_pairs ?? 0),
            'distinct_items' => (int) ($row->distinct_items ?? 0),
            'total_quantity_suom' => (float) ($row->total_quantity_suom ?? 0),
        ];
    }

    /**
     * @return array{
     *     year: int,
     *     month_rows: int,
     *     distinct_items: int,
     *     total_quantity_suom: float
     * }
     */
    public function yearSummary(int $businessId, int $year): array
    {
        $row = InventoryDailyConsumption::query()
            ->where('business_id', $businessId)
            ->whereYear('consumption_date', $year)
            ->selectRaw("COUNT(DISTINCT CONCAT(item_id, '-', store_id, '-', DATE_FORMAT(consumption_date, '%Y-%m'))) as month_rows")
            ->selectRaw('COUNT(DISTINCT item_id) as distinct_items')
            ->selectRaw('COALESCE(SUM(quantity_suom), 0) as total_quantity_suom')
            ->first();

        return [
            'year' => $year,
            'month_rows' => (int) ($row->month_rows ?? 0),
            'distinct_items' => (int) ($row->distinct_items ?? 0),
            'total_quantity_suom' => (float) ($row->total_quantity_suom ?? 0),
        ];
    }

    /**
     * @return array{
     *     month: string,
     *     from: string,
     *     until: string,
     *     days_in_month: int,
     *     days_with_usage: int,
     *     total_quantity_suom: float,
     *     daily_average: float
     * }
     */
    public function monthSummary(int $businessId, int $storeId, int $itemId, string $month): array
    {
        [$from, $until] = $this->monthBounds($month);
        $daysInMonth = Carbon::parse($month.'-01')->daysInMonth;

        $row = InventoryDailyConsumption::query()
            ->where('business_id', $businessId)
            ->where('store_id', $storeId)
            ->where('item_id', $itemId)
            ->whereDate('consumption_date', '>=', $from)
            ->whereDate('consumption_date', '<=', $until)
            ->selectRaw('COUNT(DISTINCT consumption_date) as days_with_usage')
            ->selectRaw('COALESCE(SUM(quantity_suom), 0) as total_quantity_suom')
            ->first();

        $total = (float) ($row->total_quantity_suom ?? 0);

        return [
            'month' => $month,
            'from' => $from,
            'until' => $until,
            'days_in_month' => $daysInMonth,
            'days_with_usage' => (int) ($row->days_with_usage ?? 0),
            'total_quantity_suom' => $total,
            'daily_average' => round($total / $daysInMonth, 4),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function monthBounds(string $month): array
    {
        $start = Carbon::parse($month.'-01')->startOfMonth();

        return [
            $start->toDateString(),
            $start->copy()->endOfMonth()->toDateString(),
        ];
    }

    public function itemStoreMonthlySummariesQuery(int $businessId): Builder
    {
        return InventoryDailyConsumption::query()
            ->from('inventory_daily_consumptions')
            ->where('inventory_daily_consumptions.business_id', $businessId)
            ->join('items', 'items.id', '=', 'inventory_daily_consumptions.item_id')
            ->join('stores', 'stores.id', '=', 'inventory_daily_consumptions.store_id')
            ->whereNull('items.deleted_at')
            ->groupBy(
                'inventory_daily_consumptions.store_id',
                'inventory_daily_consumptions.item_id',
                DB::raw("DATE_FORMAT(inventory_daily_consumptions.consumption_date, '%Y-%m')"),
                'items.id',
                'items.name',
                'items.code',
                'stores.id',
                'stores.name',
            )
            ->select([
                'inventory_daily_consumptions.store_id',
                'inventory_daily_consumptions.item_id',
            ])
            ->selectRaw("DATE_FORMAT(inventory_daily_consumptions.consumption_date, '%Y-%m') as consumption_month")
            ->selectRaw('items.name as item_name')
            ->selectRaw('items.code as item_code')
            ->selectRaw('stores.name as store_name')
            ->selectRaw('SUM(inventory_daily_consumptions.quantity_suom) as total_quantity_suom')
            ->selectRaw('COUNT(DISTINCT inventory_daily_consumptions.consumption_date) as days_with_usage');
    }

    public function itemStoreSummariesQuery(int $businessId): Builder
    {
        return InventoryDailyConsumption::query()
            ->from('inventory_daily_consumptions')
            ->where('inventory_daily_consumptions.business_id', $businessId)
            ->join('items', 'items.id', '=', 'inventory_daily_consumptions.item_id')
            ->join('stores', 'stores.id', '=', 'inventory_daily_consumptions.store_id')
            ->whereNull('items.deleted_at')
            ->groupBy(
                'inventory_daily_consumptions.store_id',
                'inventory_daily_consumptions.item_id',
                'items.id',
                'items.name',
                'items.code',
                'stores.id',
                'stores.name',
            )
            ->select([
                'inventory_daily_consumptions.store_id',
                'inventory_daily_consumptions.item_id',
            ])
            ->selectRaw('items.name as item_name')
            ->selectRaw('items.code as item_code')
            ->selectRaw('stores.name as store_name')
            ->selectRaw('SUM(inventory_daily_consumptions.quantity_suom) as total_quantity_suom')
            ->selectRaw('COUNT(DISTINCT inventory_daily_consumptions.consumption_date) as days_with_usage');
    }

    /**
     * Daily totals for one item at one store (all sources combined).
     *
     * @return Collection<int, object{consumption_date: string, quantity_suom: float}>
     */
    public function dailyBreakdown(int $businessId, int $storeId, int $itemId, string $from, string $until): Collection
    {
        return $this->dailyBreakdownQuery($businessId, $storeId, $itemId, $from, $until)->get();
    }

    public function dailyBreakdownQuery(int $businessId, int $storeId, int $itemId, string $from, string $until): Builder
    {
        return InventoryDailyConsumption::query()
            ->where('business_id', $businessId)
            ->where('store_id', $storeId)
            ->where('item_id', $itemId)
            ->whereDate('consumption_date', '>=', $from)
            ->whereDate('consumption_date', '<=', $until)
            ->select('consumption_date')
            ->selectRaw('SUM(quantity_suom) as quantity_suom')
            ->groupBy('consumption_date')
            ->orderByDesc('consumption_date');
    }

    /**
     * Hourly totals for one item at one store on a calendar day.
     *
     * @return Collection<int, object{hour: int, label: string, quantity_suom: float}>
     */
    public function hourlyBreakdown(int $businessId, int $storeId, int $itemId, string $date): Collection
    {
        return $this->hourlyBreakdownQuery($businessId, $storeId, $itemId, $date)->get()
            ->map(function ($row) use ($date) {
                if (isset($row->label)) {
                    return (object) [
                        'hour' => (int) $row->hour,
                        'label' => (string) $row->label,
                        'quantity_suom' => (float) $row->quantity_suom,
                    ];
                }

                $hour = (int) $row->hour;
                $start = Carbon::parse($date)->setTime($hour, 0);

                return (object) [
                    'hour' => $hour,
                    'label' => $start->format('g:i A').' – '.$start->copy()->addHour()->subMinute()->format('g:i A'),
                    'quantity_suom' => (float) $row->quantity_suom,
                ];
            });
    }

    public function hourlyBreakdownQuery(int $businessId, int $storeId, int $itemId, string $date): Builder
    {
        $hasEvents = InventoryConsumptionEvent::query()
            ->where('business_id', $businessId)
            ->where('store_id', $storeId)
            ->where('item_id', $itemId)
            ->whereDate('occurred_at', $date)
            ->exists();

        if ($hasEvents) {
            return InventoryConsumptionEvent::query()
                ->from('inventory_consumption_events')
                ->where('inventory_consumption_events.business_id', $businessId)
                ->where('inventory_consumption_events.store_id', $storeId)
                ->where('inventory_consumption_events.item_id', $itemId)
                ->whereDate('inventory_consumption_events.occurred_at', $date)
                ->selectRaw('HOUR(inventory_consumption_events.occurred_at) as hour')
                ->selectRaw('SUM(inventory_consumption_events.quantity_suom) as quantity_suom')
                ->groupBy('hour')
                ->orderBy('hour');
        }

        $dailyTotal = (int) round((float) InventoryDailyConsumption::query()
            ->where('business_id', $businessId)
            ->where('store_id', $storeId)
            ->where('item_id', $itemId)
            ->whereDate('consumption_date', $date)
            ->sum('quantity_suom'));

        $rows = app(HourlyConsumptionDistribution::class)->hourlyRows($dailyTotal, $itemId, $date);

        if ($rows === []) {
            return InventoryConsumptionEvent::query()->whereRaw('0 = 1');
        }

        $union = collect($rows)->map(function ($row) {
            $hour = (int) $row->hour;
            $label = DB::getPdo()->quote($row->label);
            $quantity = (float) $row->quantity_suom;

            return "SELECT {$hour} AS hour, {$label} AS label, {$quantity} AS quantity_suom";
        })->implode(' UNION ALL ');

        return InventoryConsumptionEvent::query()
            ->from(DB::raw("({$union}) AS hourly_consumption"))
            ->select(['hour', 'label', 'quantity_suom'])
            ->orderBy('hour');
    }

    public function salesForDayQuery(int $businessId, int $storeId, int $itemId, string $date): Builder
    {
        $linkedSaleIds = InventoryConsumptionEvent::query()
            ->where('business_id', $businessId)
            ->where('store_id', $storeId)
            ->where('item_id', $itemId)
            ->whereDate('occurred_at', $date)
            ->whereNotNull('sale_id')
            ->pluck('sale_id');

        $query = Sale::query()
            ->with(['client', 'invoice', 'processedByUser', 'servicePoint', 'branch'])
            ->where('business_id', $businessId)
            ->where('item_id', $itemId);

        if ($linkedSaleIds->isNotEmpty()) {
            $query->whereIn('id', $linkedSaleIds);
        } else {
            $query->whereDate('status_changed_at', $date);

            $branchId = Store::query()->whereKey($storeId)->value('branch_id');

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
        }

        return $query->orderByDesc('status_changed_at');
    }

    /**
     * @return array{sales_count: int, sales_quantity: int, sales_total_ugx: float}
     */
    public function salesDaySummary(int $businessId, int $storeId, int $itemId, string $date): array
    {
        $sales = $this->salesForDayQuery($businessId, $storeId, $itemId, $date)->get();

        return [
            'sales_count' => $sales->count(),
            'sales_quantity' => (int) $sales->sum('quantity'),
            'sales_total_ugx' => (float) $sales->sum('total_amount'),
        ];
    }

    public function periodDays(string $from, string $until): int
    {
        return max(1, Carbon::parse($from)->diffInDays(Carbon::parse($until)) + 1);
    }

    public function periodDailyAverage(float $totalQuantitySuom, string $from, string $until): float
    {
        return round($totalQuantitySuom / $this->periodDays($from, $until), 4);
    }

    public function monthDailyAverage(float $totalQuantitySuom, string $month): float
    {
        $daysInMonth = Carbon::parse($month.'-01')->daysInMonth;

        return round($totalQuantitySuom / max(1, $daysInMonth), 4);
    }
}
