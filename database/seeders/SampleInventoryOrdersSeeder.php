<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Business;
use App\Models\InventoryOrder;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\InventoryOrderService;
use Illuminate\Database\Seeder;

class SampleInventoryOrdersSeeder extends Seeder
{
    private const ACCOUNT_NUMBER = 'KS1759822163';

    private const BRANCH_NAME = 'Kololo';

    public function run(): void
    {
        $business = Business::query()
            ->where('account_number', self::ACCOUNT_NUMBER)
            ->orWhere('name', 'like', '%Exquisite Test Life%')
            ->first();

        if (! $business) {
            $this->command->error('Business "Exquisite Test Life" ('.self::ACCOUNT_NUMBER.') not found.');

            return;
        }

        $branch = Branch::query()
            ->where('business_id', $business->id)
            ->where('name', self::BRANCH_NAME)
            ->first();

        $store = Store::query()
            ->where('business_id', $business->id)
            ->when($branch, fn ($query) => $query->where('branch_id', $branch->id))
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->first();

        $user = User::query()
            ->where('business_id', $business->id)
            ->orderBy('id')
            ->first();

        if (! $store || ! $user) {
            $this->command->error('Store or user not found for '.$business->name.'.');

            return;
        }

        $this->command->info(sprintf(
            'Sample orders for %s → %s / %s (store #%d)',
            $business->name,
            self::BRANCH_NAME,
            $store->name,
            $store->id
        ));

        $tagged = $this->tagStockedGoods($business->id, $store->id);
        $this->command->info("Tagged {$tagged} stocked good(s) with importance categories.");

        $service = app(InventoryOrderService::class);

        $refreshed = 0;
        InventoryOrder::query()
            ->where('business_id', $business->id)
            ->where('status', InventoryOrder::STATUS_DRAFT)
            ->whereDoesntHave('lines')
            ->each(function (InventoryOrder $order) use ($service, &$refreshed) {
                $service->populateLines($order);
                $refreshed++;
                $this->command->line("  Refreshed {$order->order_number} → {$order->lines()->count()} line(s)");
            });

        if ($refreshed > 0) {
            $this->command->info("Refreshed {$refreshed} empty draft order(s).");
        }

        $samples = [
            [
                'label' => 'Essential · period (30d) · peak 25%',
                'importance' => Item::IMPORTANCE_ESSENTIAL,
                'budget_mode' => null,
                'budget_value' => null,
                'period' => 30,
                'peak' => 25,
                'peak_increase' => [55, 40, 0, 30, 0],
            ],
            [
                'label' => 'All items · period (45d)',
                'importance' => null,
                'budget_mode' => null,
                'budget_value' => null,
                'period' => 45,
                'peak' => 0,
                'peak_increase' => [],
            ],
            [
                'label' => 'Essential · budget days (60)',
                'importance' => Item::IMPORTANCE_ESSENTIAL,
                'budget_mode' => InventoryOrder::BUDGET_MODE_DAYS,
                'budget_value' => 60,
                'period' => 30,
                'peak' => 0,
                'peak_increase' => [],
            ],
            [
                'label' => 'All items · budget days (90)',
                'importance' => null,
                'budget_mode' => InventoryOrder::BUDGET_MODE_DAYS,
                'budget_value' => 90,
                'period' => 30,
                'peak' => 0,
                'peak_increase' => [],
            ],
        ];

        foreach ($samples as $sample) {
            $existing = InventoryOrder::query()
                ->where('business_id', $business->id)
                ->where('store_id', $store->id)
                ->where('notes', 'Sample: '.$sample['label'])
                ->first();

            if ($existing) {
                $this->command->line("  Skipped (exists): {$existing->order_number} — {$sample['label']}");

                continue;
            }

            $order = $service->createDraft(
                (int) $business->id,
                (int) $store->id,
                $user,
                $sample['importance'],
                $sample['budget_mode'],
                $sample['budget_value'],
                (float) $sample['period'],
                'Sample: '.$sample['label'],
                null,
                null,
                (float) $sample['peak'],
            );

            $this->applyPeakIncreases($order, $sample['peak_increase']);
            $this->command->info("  Created {$order->order_number} — {$sample['label']} ({$order->lines()->count()} lines)");
        }

        $this->command->newLine();
        $this->command->info('Open Inventory → Order Goods to review the sample orders.');
    }

    private function tagStockedGoods(int $businessId, int $storeId): int
    {
        $stockLevels = InventoryStockLevel::query()
            ->where('business_id', $businessId)
            ->where('store_id', $storeId)
            ->where('quantity_suom', '>', 0)
            ->with('item')
            ->orderBy('item_id')
            ->get();

        $tagged = 0;

        foreach ($stockLevels as $index => $stock) {
            $item = $stock->item;

            if (! $item || $item->type !== 'good') {
                continue;
            }

            if ($item->importance_category) {
                continue;
            }

            $category = $index % 3 === 2
                ? Item::IMPORTANCE_NON_ESSENTIAL
                : Item::IMPORTANCE_ESSENTIAL;

            $item->update(['importance_category' => $category]);
            $tagged++;
        }

        return $tagged;
    }

    /**
     * @param  array<int, float>  $increases  Indexed by line position
     */
    private function applyPeakIncreases(InventoryOrder $order, array $increases): void
    {
        if ($increases === []) {
            return;
        }

        $service = app(InventoryOrderService::class);

        $order->lines()->orderBy('id')->get()->each(function ($line, int $index) use ($service, $increases) {
            if (! isset($increases[$index]) || (float) $increases[$index] <= 0) {
                return;
            }

            $service->updateLinePeakIncrease($line, (float) $increases[$index]);
        });
    }
}
