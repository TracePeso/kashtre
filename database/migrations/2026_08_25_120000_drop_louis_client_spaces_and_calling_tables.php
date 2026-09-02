<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        foreach ([
            'client_space_store_assignments',
            'client_spaces',
            'p2p_call_signals',
            'p2p_calls',
            'caller_logs',
            'callers',
            'emergency_alerts',
            'pa_sections',
            'calling_module_configs',
            'kashtre_hr_module_settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        // Removed modules are intentionally not recreated.
    }
};
