<?php

namespace App\Console\Commands;

use App\Models\BranchItemPrice;
use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillItemPurchasePrices extends Command
{
    protected $signature = 'items:backfill-purchase-prices
                            {--ratio=1 : Purchase price as a decimal ratio of sale price}
                            {--overwrite : Replace purchase prices that already exist}
                            {--dry-run : Show how many records would change without saving}';

    protected $description = 'Backfill item and branch purchase prices from their sale prices';

    public function handle(): int
    {
        $ratio = filter_var($this->option('ratio'), FILTER_VALIDATE_FLOAT);

        if ($ratio === false || $ratio < 0) {
            $this->error('The --ratio option must be a number greater than or equal to zero.');

            return self::FAILURE;
        }

        $overwrite = (bool) $this->option('overwrite');
        $itemQuery = Item::query();
        $branchQuery = BranchItemPrice::query();

        if (! $overwrite) {
            $itemQuery->whereNull('purchase_price');
            $branchQuery->whereNull('purchase_price');
        }

        $itemCount = (clone $itemQuery)->count();
        $branchCount = (clone $branchQuery)->count();

        if ($this->option('dry-run')) {
            $this->info("Would update {$itemCount} items and {$branchCount} branch prices using ratio {$ratio}.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($itemQuery, $branchQuery, $ratio): void {
            $itemQuery->chunkById(500, function ($items) use ($ratio): void {
                foreach ($items as $item) {
                    $item->updateQuietly([
                        'purchase_price' => round(max(0, (float) $item->default_price) * $ratio, 2),
                    ]);
                }
            });

            $branchQuery->chunkById(500, function ($branchPrices) use ($ratio): void {
                foreach ($branchPrices as $branchPrice) {
                    $branchPrice->updateQuietly([
                        'purchase_price' => round(max(0, (float) $branchPrice->price) * $ratio, 2),
                    ]);
                }
            });
        });

        $this->info("Updated {$itemCount} items and {$branchCount} branch prices using ratio {$ratio}.");

        return self::SUCCESS;
    }
}
