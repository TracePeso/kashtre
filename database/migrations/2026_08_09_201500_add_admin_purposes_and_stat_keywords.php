<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_module_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_module_configs', 'admin_usage_purposes')) {
                $table->json('admin_usage_purposes')->nullable()->after('visit_reactivation_lookback_days');
            }
            if (! Schema::hasColumn('inventory_module_configs', 'stat_priority_keywords')) {
                $table->json('stat_priority_keywords')->nullable()->after('admin_usage_purposes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_module_configs', function (Blueprint $table) {
            foreach (['admin_usage_purposes', 'stat_priority_keywords'] as $column) {
                if (Schema::hasColumn('inventory_module_configs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
