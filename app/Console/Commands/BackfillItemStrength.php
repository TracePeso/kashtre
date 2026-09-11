<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Support\ItemStrengthParser;
use Illuminate\Console\Command;

/**
 * Populates items.strength from the strength embedded in items.name.
 *
 * The Clinical Module matches on term + strength; with the column empty every
 * brand of a drug scores equally and the dose choice falls to whoever reads
 * the candidate list rather than to the prescription.
 */
class BackfillItemStrength extends Command
{
    protected $signature = 'items:backfill-strength
                            {--business= : Limit to one business id}
                            {--overwrite : Also rewrite rows that already have a strength}
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Parse dose strength out of item names into items.strength';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Item::query()
            ->when($this->option('business'), fn ($q, $id) => $q->where('business_id', $id))
            ->unless($this->option('overwrite'), fn ($q) => $q->where(function ($q) {
                $q->whereNull('strength')->orWhere('strength', '');
            }));

        $updated = 0;
        $skipped = 0;

        $query->eachById(function (Item $item) use (&$updated, &$skipped, $dryRun) {
            $strength = ItemStrengthParser::parse($item->name);

            if ($strength === null) {
                $skipped++;

                return;
            }

            if ($this->output->isVerbose()) {
                $this->line(sprintf('  %-55s => %s', $item->name, $strength));
            }

            if (! $dryRun) {
                $item->forceFill(['strength' => $strength])->saveQuietly();
            }

            $updated++;
        });

        $this->info(sprintf(
            '%s %d item(s); %d had no parseable strength in the name.',
            $dryRun ? 'Would update' : 'Updated',
            $updated,
            $skipped
        ));

        return self::SUCCESS;
    }
}
