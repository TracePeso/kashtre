<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_policy_versions') || ! Schema::hasColumn('hr_policy_versions', 'anchor_window_minutes')) {
            return;
        }

        DB::statement('ALTER TABLE hr_policy_versions MODIFY anchor_window_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        if (! Schema::hasTable('hr_policy_versions') || ! Schema::hasColumn('hr_policy_versions', 'anchor_window_minutes')) {
            return;
        }

        DB::statement('ALTER TABLE hr_policy_versions MODIFY anchor_window_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 1440');
    }
};
