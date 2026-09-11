<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The outbox model stores an encrypted payload string via Eloquent casts.
        // MySQL JSON columns require valid JSON values, which fails the CHECK/JSON_VALID constraint.
        // Switch payload storage to LONGTEXT so encrypted strings can be persisted.
        DB::statement('ALTER TABLE inventory_main_module_outbox MODIFY payload LONGTEXT NULL');
    }

    public function down(): void
    {
        // Revert to JSON column (may fail if encrypted payload isn't valid JSON).
        DB::statement('ALTER TABLE inventory_main_module_outbox MODIFY payload JSON NULL');
    }
};

