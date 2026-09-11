<?php

namespace App\Console\Commands;

use App\Domain\Units\Services\InventoryUnitGateway;
use App\Models\Item;
use App\Models\ItemUnit;
use Database\Seeders\Units\CoreUnitSeedPackSeeder;
use Illuminate\Console\Command;

class InstallCoreUnitEngineCommand extends Command
{
    protected $signature = 'units:install
        {--map-legacy : Map item_units names into legacy mapping table}
        {--link-items : Backfill sale/order unit public IDs on items}';

    protected $description = 'Install Shared Unit Engine seed pack and optionally map legacy inventory units';

    public function handle(InventoryUnitGateway $gateway): int
    {
        $this->info('Seeding core unit pack…');
        $this->call('db:seed', ['--class' => CoreUnitSeedPackSeeder::class, '--force' => true]);

        if ($this->option('map-legacy') || $this->option('link-items')) {
            $this->info('Mapping legacy item_units…');
            $count = 0;
            ItemUnit::query()->orderBy('id')->chunkById(200, function ($units) use ($gateway, &$count) {
                foreach ($units as $unit) {
                    $gateway->mapLegacyName((string) $unit->business_id, (string) $unit->name);
                    $count++;
                }
            });
            $this->info("Mapped {$count} item_unit name(s).");
        }

        if ($this->option('link-items')) {
            $this->info('Linking items to canonical unit public IDs…');
            $linked = 0;
            Item::query()->orderBy('id')->chunkById(100, function ($items) use ($gateway, &$linked) {
                foreach ($items as $item) {
                    $before = [$item->sale_unit_public_id, $item->order_unit_public_id];
                    $gateway->ensureItemUnitLinks($item->fresh());
                    $item->refresh();
                    if ($before[0] !== $item->sale_unit_public_id || $before[1] !== $item->order_unit_public_id) {
                        $linked++;
                    }
                }
            });
            $this->info("Updated {$linked} item(s).");
        }

        $this->info('Done. Set UNIT_ENGINE_ENABLED=true to dual-run Inventory packaging conversions.');

        return self::SUCCESS;
    }
}
