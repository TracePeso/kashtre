<?php

namespace App\Services\Inventory;

use App\Models\InventoryDailyConsumption;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Support\ConsumptionItemMatcher;
use App\Support\HospitalConsumptionMatrix;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryConsumptionSampleDataService
{
    public const SAMPLE_NOTE = 'Sample hospital matrix (test backfill)';

    /** Enough weeks for Inventory AI, short enough for a Livewire request. */
    public const MAX_BACKFILL_DAYS = 28;

    /**
     * @return array{from: ?string, until: ?string, days: int, already_current: bool}
     */
    public function pendingBackfillRange(int $businessId, int $storeId): array
    {
        $today = now()->startOfDay();
        $from = $this->backfillStartDate($businessId, $storeId);

        if ($from->gt($today)) {
            return [
                'from' => null,
                'until' => null,
                'days' => 0,
                'already_current' => true,
            ];
        }

        return [
            'from' => $from->toDateString(),
            'until' => $today->toDateString(),
            'days' => (int) $from->diffInDays($today) + 1,
            'already_current' => false,
        ];
    }

    /**
     * @return array{
     *     success: bool,
     *     message: string,
     *     from: ?string,
     *     until: ?string,
     *     rows: int,
     *     events: int,
     *     items: int
     * }
     */
    public function backfillToToday(int $businessId, int $storeId, int $recordedByUserId): array
    {
        $range = $this->pendingBackfillRange($businessId, $storeId);

        if ($range['already_current']) {
            return [
                'success' => false,
                'message' => 'Consumption is already up to date through today.',
                'from' => null,
                'until' => null,
                'rows' => 0,
                'events' => 0,
                'items' => 0,
            ];
        }

        $from = Carbon::parse($range['from']);
        $until = Carbon::parse($range['until']);

        $matrixItems = require database_path('seeders/data/hospital_consumption_items.php');
        $catalog = Item::query()
            ->where('business_id', $businessId)
            ->where('type', 'good')
            ->get(['id', 'name']);

        $matcher = new ConsumptionItemMatcher($catalog);
        $generator = new HospitalConsumptionMatrix();
        $rows = $generator->generateForRange($matrixItems, $from, $until);

        $insertRows = [];
        $matchedItemIds = [];
        $matchedByName = [];
        $now = now();

        foreach ($rows as $row) {
            $name = $row['item_name'];
            if (! array_key_exists($name, $matchedByName)) {
                $matchedByName[$name] = $matcher->match($name);
            }

            $item = $matchedByName[$name];
            if ($item === null) {
                continue;
            }

            $matchedItemIds[$item->id] = true;

            $insertRows[] = [
                'business_id' => $businessId,
                'store_id' => $storeId,
                'item_id' => $item->id,
                'consumption_date' => $row['date'],
                'quantity_suom' => $row['quantity'],
                'source' => InventoryDailyConsumption::SOURCE_SALE,
                'notes' => self::SAMPLE_NOTE,
                'recorded_by_user_id' => $recordedByUserId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($insertRows === []) {
            return [
                'success' => false,
                'message' => 'No sample items matched your catalogue for this date range.',
                'from' => $from->toDateString(),
                'until' => $until->toDateString(),
                'rows' => 0,
                'events' => 0,
                'items' => 0,
            ];
        }

        $itemIds = array_keys($matchedItemIds);

        DB::transaction(function () use ($businessId, $storeId, $insertRows, $itemIds, $from, $until): void {
            foreach (array_chunk($insertRows, 500) as $chunk) {
                DB::table('inventory_daily_consumptions')->upsert(
                    $chunk,
                    ['business_id', 'store_id', 'item_id', 'consumption_date', 'source'],
                    ['quantity_suom', 'notes', 'recorded_by_user_id', 'updated_at']
                );
            }

            $this->refreshStockAverages($businessId, $storeId, $itemIds);
            $this->syncMonthlyTotals($businessId, $storeId, $itemIds, $from, $until);
        });

        return [
            'success' => true,
            'message' => sprintf(
                'Generated test consumption from %s to %s.',
                $from->format('M j, Y'),
                $until->format('M j, Y')
            ),
            'from' => $from->toDateString(),
            'until' => $until->toDateString(),
            'rows' => count($insertRows),
            'events' => 0,
            'items' => count($matchedItemIds),
        ];
    }

    private function backfillStartDate(int $businessId, int $storeId): Carbon
    {
        $today = now()->startOfDay();
        $earliest = $today->copy()->subDays(self::MAX_BACKFILL_DAYS - 1);

        $lastDate = InventoryDailyConsumption::query()
            ->where('business_id', $businessId)
            ->where('store_id', $storeId)
            ->max('consumption_date');

        if (! $lastDate) {
            return $earliest;
        }

        $from = Carbon::parse($lastDate)->addDay()->startOfDay();

        return $from->gt($earliest) ? $from : $earliest;
    }

    /**
     * @param  list<int>  $itemIds
     */
    private function refreshStockAverages(int $businessId, int $storeId, array $itemIds): void
    {
        $today = now()->startOfDay();
        $windows = InventoryStockAnalyticsService::MOVING_AVERAGE_WINDOWS;
        $maxDays = max(array_keys($windows));
        $from = $today->copy()->subDays($maxDays - 1)->toDateString();

        $daily = DB::table('inventory_daily_consumptions')
            ->selectRaw('item_id, consumption_date, SUM(quantity_suom) as value')
            ->where('business_id', $businessId)
            ->where('store_id', $storeId)
            ->whereIn('item_id', $itemIds)
            ->whereIn('source', InventoryDailyConsumption::demandSources())
            ->whereDate('consumption_date', '>=', $from)
            ->groupBy('item_id', 'consumption_date')
            ->get()
            ->groupBy('item_id');

        $now = now();
        $rows = [];

        foreach ($itemIds as $itemId) {
            $points = $daily->get($itemId, collect());
            $updates = [
                'business_id' => $businessId,
                'store_id' => $storeId,
                'item_id' => $itemId,
                'quantity_suom' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach ($windows as $days => $column) {
                $windowFrom = $today->copy()->subDays($days - 1);
                $total = 0.0;
                foreach ($points as $point) {
                    if (Carbon::parse($point->consumption_date)->gte($windowFrom)) {
                        $total += (float) $point->value;
                    }
                }
                $updates[$column] = round($total / max(1, $days), 4);
            }

            $updates['daily_usage_suom'] = $updates['ma_30_days'];
            $rows[] = $updates;
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('inventory_stock_levels')->upsert(
                $chunk,
                ['business_id', 'store_id', 'item_id'],
                array_merge(array_values($windows), ['daily_usage_suom', 'updated_at'])
            );
        }
    }

    /**
     * @param  list<int>  $itemIds
     */
    private function syncMonthlyTotals(int $businessId, int $storeId, array $itemIds, Carbon $from, Carbon $until): void
    {
        $cursor = $from->copy()->startOfMonth();
        $endMonth = $until->copy()->startOfMonth();
        $now = now();

        while ($cursor->lte($endMonth)) {
            $monthStart = $cursor->toDateString();
            $monthEnd = $cursor->copy()->endOfMonth()->toDateString();

            $totals = DB::table('inventory_daily_consumptions')
                ->selectRaw('item_id, COALESCE(SUM(quantity_suom), 0) as total_quantity_suom, COUNT(DISTINCT consumption_date) as days_with_usage')
                ->where('business_id', $businessId)
                ->where('store_id', $storeId)
                ->whereIn('item_id', $itemIds)
                ->whereIn('source', InventoryDailyConsumption::demandSources())
                ->whereBetween('consumption_date', [$monthStart, $monthEnd])
                ->groupBy('item_id')
                ->get()
                ->keyBy('item_id');

            $upserts = [];
            $emptyIds = [];

            foreach ($itemIds as $itemId) {
                $row = $totals->get($itemId);
                $total = (float) ($row->total_quantity_suom ?? 0);

                if ($total <= 0) {
                    $emptyIds[] = $itemId;

                    continue;
                }

                $upserts[] = [
                    'business_id' => $businessId,
                    'store_id' => $storeId,
                    'item_id' => $itemId,
                    'consumption_month' => $monthStart,
                    'total_quantity_suom' => $total,
                    'days_with_usage' => (int) ($row->days_with_usage ?? 0),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($emptyIds !== []) {
                DB::table('inventory_monthly_consumptions')
                    ->where('business_id', $businessId)
                    ->where('store_id', $storeId)
                    ->whereIn('item_id', $emptyIds)
                    ->whereDate('consumption_month', $monthStart)
                    ->delete();
            }

            foreach (array_chunk($upserts, 200) as $chunk) {
                DB::table('inventory_monthly_consumptions')->upsert(
                    $chunk,
                    ['business_id', 'store_id', 'item_id', 'consumption_month'],
                    ['total_quantity_suom', 'days_with_usage', 'updated_at']
                );
            }

            $cursor->addMonth();
        }
    }
}
