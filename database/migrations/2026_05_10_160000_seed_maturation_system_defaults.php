<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maturation_system_defaults')) {
            return;
        }

        /** @var array{entity: array<string, int>, service_charge: array<string, int>} $defaults */
        $defaults = require config_path('maturation_defaults.php');
        $now = now();

        foreach ($defaults['entity'] as $method => $entityDays) {
            if (DB::table('maturation_system_defaults')->where('payment_method', $method)->exists()) {
                continue;
            }

            $svcDays = (int) ($defaults['service_charge'][$method] ?? $entityDays);

            DB::table('maturation_system_defaults')->insert([
                'payment_method' => $method,
                'entity_maturation_days' => (int) $entityDays,
                'service_charge_maturation_days' => $svcDays,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Do not delete seeded defaults on rollback; table migration owns lifecycle.
    }
};
