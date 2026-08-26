<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Duplicate of 2026_04_12_100000_create_client_spaces_table.
     * Kept as a no-op so existing migrate history remains valid.
     */
    public function up(): void
    {
        // Intentionally empty — client_spaces is created by 2026_04_12_100000.
    }

    public function down(): void
    {
        // Intentionally empty — do not drop the table created by the earlier migration.
        // Only drop if somehow this migration created it alone (should never happen).
        if (! Schema::hasTable('client_spaces')) {
            return;
        }
    }
};
