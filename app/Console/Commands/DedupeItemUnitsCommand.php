<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Soft-delete duplicate item_units rows (same business + case-insensitive name).
 * Re-points item uom_id / order_unit_id to the kept unit.
 */
class DedupeItemUnitsCommand extends Command
{
    protected $signature = 'units:dedupe-item-units
                            {--business= : Limit to one business id}
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Soft-delete duplicate Item Units per business (case-insensitive name) and re-point items';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $businessFilter = $this->option('business');

        $query = ItemUnit::query()
            ->select('business_id', DB::raw('LOWER(name) as name_key'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('business_id', DB::raw('LOWER(name)'))
            ->havingRaw('COUNT(*) > 1');

        if ($businessFilter) {
            $query->where('business_id', (int) $businessFilter);
        }

        $groups = $query->get();

        if ($groups->isEmpty()) {
            $this->info('No duplicate item units found.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $repointed = 0;

        foreach ($groups as $group) {
            $units = ItemUnit::query()
                ->where('business_id', $group->business_id)
                ->whereRaw('LOWER(name) = ?', [$group->name_key])
                ->orderBy('id')
                ->get();

            $keep = $units->sortByDesc(function (ItemUnit $unit) {
                $usage = Item::query()->where('uom_id', $unit->id)->count()
                    + Item::query()->where('order_unit_id', $unit->id)->count();

                return [$usage, -$unit->id];
            })->first();

            $dupes = $units->where('id', '!=', $keep->id);

            $this->line(sprintf(
                'Business %s · "%s" → keep id=%d, soft-delete %s',
                $group->business_id,
                $keep->name,
                $keep->id,
                $dupes->pluck('id')->implode(',')
            ));

            if ($dry) {
                continue;
            }

            DB::transaction(function () use ($keep, $dupes, &$deleted, &$repointed) {
                foreach ($dupes as $dupe) {
                    $saleUpdated = Item::query()->where('uom_id', $dupe->id)->update(['uom_id' => $keep->id]);
                    $orderUpdated = Item::query()->where('order_unit_id', $dupe->id)->update(['order_unit_id' => $keep->id]);
                    $repointed += $saleUpdated + $orderUpdated;
                    $dupe->delete();
                    $deleted++;
                }
            });
        }

        if ($dry) {
            $this->warn('Dry run only — no changes written.');
        } else {
            $this->info("Soft-deleted {$deleted} duplicate unit(s); re-pointed {$repointed} item field(s).");
        }

        return self::SUCCESS;
    }
}
