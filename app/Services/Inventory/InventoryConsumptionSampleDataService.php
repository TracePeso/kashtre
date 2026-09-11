<?php

namespace App\Services\Inventory;

use App\Models\InventoryDailyConsumption;
use App\Models\InventoryConsumptionEvent;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Support\ConsumptionItemMatcher;
use App\Support\HospitalConsumptionMatrix;
use App\Support\HourlyConsumptionDistribution;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryConsumptionSampleDataService
{
    public const SAMPLE_NOTE = 'Sample hospital matrix (test backfill)';

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

        $hourly = new HourlyConsumptionDistribution();
        $insertRows = [];
        $eventRows = [];
        $matchedItemIds = [];
        $now = now();

        foreach ($rows as $row) {
            $item = $matcher->match($row['item_name']);

            if (! $item) {
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

            foreach ($hourly->distribute((int) $row['quantity'], (int) $item->id, $row['date']) as $hour => $hourQty) {
                $eventRows[] = [
                    'business_id' => $businessId,
                    'store_id' => $storeId,
                    'item_id' => $item->id,
                    'quantity_suom' => $hourQty,
                    'occurred_at' => Carbon::parse($row['date'])->setTime($hour, 0),
                    'source' => InventoryDailyConsumption::SOURCE_SALE,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
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

        DB::transaction(function () use ($businessId, $storeId, $insertRows, $eventRows, $matchedItemIds): void {
            foreach (array_chunk($insertRows, 500) as $chunk) {
                DB::table('inventory_daily_consumptions')->upsert(
                    $chunk,
                    ['business_id', 'store_id', 'item_id', 'consumption_date', 'source'],
                    ['quantity_suom', 'notes', 'recorded_by_user_id', 'updated_at']
                );
            }

            $itemIds = array_keys($matchedItemIds);

            InventoryConsumptionEvent::query()
                ->where('business_id', $businessId)
                ->where('store_id', $storeId)
                ->whereIn('item_id', $itemIds)
                ->whereDate('occurred_at', '>=', $insertRows[0]['consumption_date'])
                ->whereDate('occurred_at', '<=', $insertRows[array_key_last($insertRows)]['consumption_date'])
                ->delete();

            foreach (array_chunk($eventRows, 500) as $chunk) {
                DB::table('inventory_consumption_events')->insert($chunk);
            }

            $analytics = app(InventoryStockAnalyticsService::class);
            $monthly = app(InventoryMonthlyConsumptionService::class);

            foreach ($itemIds as $itemId) {
                $stock = InventoryStockLevel::firstOrCreate(
                    [
                        'business_id' => $businessId,
                        'store_id' => $storeId,
                        'item_id' => $itemId,
                    ],
                    ['quantity_suom' => 0]
                );

                $analytics->recalculateForStockLevel($stock);

                $cursor = Carbon::parse($insertRows[0]['consumption_date'])->startOfMonth();
                $endMonth = Carbon::parse($insertRows[array_key_last($insertRows)]['consumption_date'])->startOfMonth();

                while ($cursor->lte($endMonth)) {
                    $monthly->syncMonthFromDaily($businessId, $storeId, (int) $itemId, $cursor->toDateString());
                    $cursor->addMonth();
                }
            }
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
            'events' => count($eventRows),
            'items' => count($matchedItemIds),
        ];
    }

    private function backfillStartDate(int $businessId, int $storeId): Carbon
    {
        $lastDate = InventoryDailyConsumption::query()
            ->where('business_id', $businessId)
            ->where('store_id', $storeId)
            ->max('consumption_date');

        if ($lastDate) {
            return Carbon::parse($lastDate)->addDay()->startOfDay();
        }

        return now()->subDays(9)->startOfDay();
    }
}
