<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Business;
use App\Models\InventoryDailyConsumption;
use App\Models\InventoryConsumptionEvent;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\InventoryStockAnalyticsService;
use App\Support\ConsumptionItemMatcher;
use App\Support\HospitalConsumptionMatrix;
use App\Support\HourlyConsumptionDistribution;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SampleHospitalConsumptionSeeder extends Seeder
{
    private const SAMPLE_NOTE = 'Sample hospital matrix (180-day rolling window)';

    private const ACCOUNT_NUMBER = 'KS1759822163';

    private const BRANCH_NAME = 'Kololo';

    private const RECORDED_BY_EMAIL = 'katznicho@gmail.com';

    public function run(): void
    {
        $business = Business::query()
            ->where('account_number', self::ACCOUNT_NUMBER)
            ->orWhere('name', 'like', '%Exquisite Test Life%')
            ->first();

        if (! $business) {
            $this->command->error('Business "Exquisite Test Life" (KS1759822163) not found.');

            return;
        }

        $branch = Branch::query()
            ->where('business_id', $business->id)
            ->where('name', self::BRANCH_NAME)
            ->first();

        if (! $branch) {
            $this->command->error('Branch "'.self::BRANCH_NAME.'" not found for '.$business->name.'.');

            return;
        }

        $store = Store::query()
            ->where('business_id', $business->id)
            ->where('branch_id', $branch->id)
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->first();

        if (! $store) {
            $this->command->error('No store found for '.$business->name.' / '.self::BRANCH_NAME.'.');

            return;
        }

        $recordedBy = User::query()
            ->where('business_id', $business->id)
            ->where('email', self::RECORDED_BY_EMAIL)
            ->first();

        if (! $recordedBy) {
            $this->command->error('User '.self::RECORDED_BY_EMAIL.' not found on '.$business->name.'.');

            return;
        }

        $matrixItems = require database_path('seeders/data/hospital_consumption_items.php');
        $catalog = Item::query()
            ->where('business_id', $business->id)
            ->where('type', 'good')
            ->get(['id', 'name']);

        $matcher = new ConsumptionItemMatcher($catalog);
        $generator = new HospitalConsumptionMatrix();
        $rows = $generator->generate($matrixItems);

        $this->command->info(sprintf(
            'Seeding consumption for %s (%s) → %s / %s (store #%d)',
            $business->name,
            $business->account_number,
            self::BRANCH_NAME,
            $store->name,
            $store->id
        ));

        $sampleItemIds = InventoryDailyConsumption::query()
            ->where('business_id', $business->id)
            ->where('store_id', $store->id)
            ->where('notes', 'like', 'Sample hospital matrix%')
            ->distinct()
            ->pluck('item_id');

        if ($sampleItemIds->isNotEmpty()) {
            $clearedEvents = InventoryConsumptionEvent::query()
                ->where('business_id', $business->id)
                ->where('store_id', $store->id)
                ->whereIn('item_id', $sampleItemIds)
                ->delete();

            if ($clearedEvents > 0) {
                $this->command->warn("Removed {$clearedEvents} previous sample consumption event(s).");
            }
        }

        $cleared = InventoryDailyConsumption::query()
            ->where('business_id', $business->id)
            ->where('store_id', $store->id)
            ->where('notes', 'like', 'Sample hospital matrix%')
            ->delete();

        if ($cleared > 0) {
            $this->command->warn("Removed {$cleared} previous sample consumption row(s).");
        }

        $matchedItemIds = [];
        $unmatched = [];
        $insertRows = [];
        $eventRows = [];
        $now = now();
        $hourly = new HourlyConsumptionDistribution();

        foreach ($rows as $row) {
            $item = $matcher->match($row['item_name']);

            if (! $item) {
                $unmatched[$row['item_name']] = true;

                continue;
            }

            $matchedItemIds[$item->id] = true;

            $insertRows[] = [
                'business_id' => $business->id,
                'store_id' => $store->id,
                'item_id' => $item->id,
                'consumption_date' => $row['date'],
                'quantity_suom' => $row['quantity'],
                'source' => InventoryDailyConsumption::SOURCE_SALE,
                'notes' => self::SAMPLE_NOTE,
                'recorded_by_user_id' => $recordedBy->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach ($hourly->distribute((int) $row['quantity'], (int) $item->id, $row['date']) as $hour => $hourQty) {
                $eventRows[] = [
                    'business_id' => $business->id,
                    'store_id' => $store->id,
                    'item_id' => $item->id,
                    'quantity_suom' => $hourQty,
                    'occurred_at' => Carbon::parse($row['date'])->setTime($hour, 0),
                    'source' => InventoryDailyConsumption::SOURCE_SALE,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->command->info('Generated '.number_format(count($rows)).' matrix cells; inserting '.number_format(count($insertRows)).' consumption row(s).');

        foreach (array_chunk($insertRows, 500) as $chunk) {
            DB::table('inventory_daily_consumptions')->upsert(
                $chunk,
                ['business_id', 'store_id', 'item_id', 'consumption_date', 'source'],
                ['quantity_suom', 'notes', 'recorded_by_user_id', 'updated_at']
            );
        }

        foreach (array_chunk($eventRows, 500) as $chunk) {
            DB::table('inventory_consumption_events')->insert($chunk);
        }

        $analytics = app(InventoryStockAnalyticsService::class);
        $itemIds = array_keys($matchedItemIds);

        $this->command->info('Recalculating moving averages for '.count($itemIds).' item(s)...');

        foreach ($itemIds as $itemId) {
            $stock = InventoryStockLevel::firstOrCreate(
                [
                    'business_id' => $business->id,
                    'store_id' => $store->id,
                    'item_id' => $itemId,
                ],
                ['quantity_suom' => 0]
            );

            $analytics->recalculateForStockLevel($stock);
        }

        $this->command->newLine();
        $this->command->info('Sample consumption seeded successfully.');
        $this->command->table(
            ['Metric', 'Value'],
            [
                ['Matrix items', count($matrixItems)],
                ['Items matched', count($itemIds)],
                ['Items unmatched', count($unmatched)],
                ['Consumption rows', number_format(count($insertRows))],
                ['Date range', HospitalConsumptionMatrix::dateRangeLabel()],
            ]
        );

        if ($unmatched !== []) {
            $this->command->warn('Unmatched matrix items (not in pricelist):');
            foreach (array_keys($unmatched) as $name) {
                $this->command->line('  - '.$name);
            }
        }
    }
}
