<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Business;
use App\Models\InventoryDailyConsumption;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\Store;
use App\Services\Inventory\InventoryStockAnalyticsService;
use App\Support\HospitalConsumptionMatrix;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SampleHospitalConsumptionSeeder extends Seeder
{
    private const SAMPLE_NOTE = 'Sample hospital matrix (180-day rolling window)';

    private const ACCOUNT_NUMBER = 'KS1759822163';

    private const BRANCH_NAME = 'Kololo';

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
        $now = now();

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
                'recorded_by_user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->command->info('Generated '.number_format(count($rows)).' matrix cells; inserting '.number_format(count($insertRows)).' consumption row(s).');

        foreach (array_chunk($insertRows, 500) as $chunk) {
            DB::table('inventory_daily_consumptions')->upsert(
                $chunk,
                ['business_id', 'store_id', 'item_id', 'consumption_date', 'source'],
                ['quantity_suom', 'notes', 'updated_at']
            );
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

/**
 * @internal
 */
class ConsumptionItemMatcher
{
    /** @var array<string, Item> */
    private array $exact = [];

    /** @var Collection<int, Item> */
    private Collection $items;

    public function __construct(Collection $items)
    {
        $this->items = $items;

        foreach ($items as $item) {
            $this->exact[$this->normalize($item->name)] = $item;
        }
    }

    public function match(string $spreadsheetName): ?Item
    {
        $key = $this->normalize($spreadsheetName);

        if (isset($this->exact[$key])) {
            return $this->exact[$key];
        }

        $best = null;
        $bestScore = PHP_INT_MAX;

        foreach ($this->items as $item) {
            $candidate = $this->normalize($item->name);

            if ($candidate === $key) {
                return $item;
            }

            if (str_contains($candidate, $key) || str_contains($key, $candidate)) {
                $score = levenshtein($key, $candidate);

                if ($score < $bestScore) {
                    $bestScore = $score;
                    $best = $item;
                }
            }
        }

        if ($best !== null && $bestScore <= max(8, (int) (strlen($key) * 0.35))) {
            return $best;
        }

        foreach ($this->items as $item) {
            $candidate = $this->normalize($item->name);
            $score = levenshtein($key, $candidate);

            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        return $best !== null && $bestScore <= 4 ? $best : null;
    }

    private function normalize(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9]+/', '', $name) ?? '';

        return $name;
    }
}
