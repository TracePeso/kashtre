<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `items.other_names` was created with utf8mb4_bin while every other text
 * column on the table is utf8mb4_unicode_ci. A binary collation makes LIKE
 * case-sensitive, so the Clinical Module's catalogue search matched
 * "Metronidazole" but not "metronidazole" — the lowercase generic name being
 * the single most likely thing a clinician types.
 *
 * Fixing the column rather than lower-casing in PHP keeps the match in SQL,
 * where it can still use an index and stays consistent with `name`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('items', 'other_names')) {
            return;
        }

        if ($this->currentCollation() === 'utf8mb4_unicode_ci') {
            return;
        }

        DB::statement('ALTER TABLE `items` MODIFY `other_names` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('items', 'other_names')) {
            return;
        }

        DB::statement('ALTER TABLE `items` MODIFY `other_names` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL');
    }

    private function currentCollation(): ?string
    {
        $column = collect(DB::select('SHOW FULL COLUMNS FROM `items`'))
            ->firstWhere('Field', 'other_names');

        return $column->Collation ?? null;
    }
};
