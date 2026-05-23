<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_open_shifts') || ! Schema::hasColumn('hr_open_shifts', 'source_type')) {
            return;
        }

        DB::table('hr_open_shifts')
            ->where('source_type', 'coverage_gap')
            ->where('status', 'open')
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);

        DB::statement("ALTER TABLE hr_open_shifts MODIFY source_type VARCHAR(40) NOT NULL DEFAULT 'manual'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('hr_open_shifts') || ! Schema::hasColumn('hr_open_shifts', 'source_type')) {
            return;
        }

        DB::statement("ALTER TABLE hr_open_shifts MODIFY source_type VARCHAR(40) NOT NULL DEFAULT 'coverage_gap'");
    }
};
