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
            // Calling, P2P, PA, and emergency-alert tables are deliberately
            // NOT in this list — that subsystem was restored (rebase onto
            // the imaging module), so its tables must survive this cleanup.
            // Only Client Spaces and the HR module bridge stay dropped.
            'client_space_store_assignments',
            'client_spaces',
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
