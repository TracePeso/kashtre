<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_module_configs', function (Blueprint $table) {
            $table->decimal('fixed_daily_average_suom', 14, 4)->default(0)->after('description');
            $table->decimal('safety_stock_days', 8, 2)->default(0)->after('fixed_daily_average_suom');
            $table->decimal('buffer_stock_days', 8, 2)->default(0)->after('safety_stock_days');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_module_configs', function (Blueprint $table) {
            $table->dropColumn([
                'fixed_daily_average_suom',
                'safety_stock_days',
                'buffer_stock_days',
            ]);
        });
    }
};
