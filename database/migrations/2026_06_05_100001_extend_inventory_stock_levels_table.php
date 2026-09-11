<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stock_levels', function (Blueprint $table) {
            $table->decimal('physical_quantity_suom', 14, 4)->nullable()->after('quantity_suom');
            $table->timestamp('physical_counted_at')->nullable()->after('physical_quantity_suom');
            $table->decimal('damaged_quantity_suom', 14, 4)->default(0)->after('physical_counted_at');
            $table->decimal('opening_quantity_suom', 14, 4)->nullable()->after('damaged_quantity_suom');
            $table->decimal('safety_stock_days', 8, 2)->nullable()->after('daily_usage_suom');
            $table->decimal('buffer_stock_days', 8, 2)->nullable()->after('safety_stock_days');
            $table->decimal('ma_15_days', 14, 4)->nullable()->after('buffer_stock_days');
            $table->decimal('ma_30_days', 14, 4)->nullable()->after('ma_15_days');
            $table->decimal('ma_90_days', 14, 4)->nullable()->after('ma_30_days');
            $table->decimal('ma_180_days', 14, 4)->nullable()->after('ma_90_days');
            $table->decimal('ma_360_days', 14, 4)->nullable()->after('ma_180_days');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stock_levels', function (Blueprint $table) {
            $table->dropColumn([
                'physical_quantity_suom',
                'physical_counted_at',
                'damaged_quantity_suom',
                'opening_quantity_suom',
                'safety_stock_days',
                'buffer_stock_days',
                'ma_15_days',
                'ma_30_days',
                'ma_90_days',
                'ma_180_days',
                'ma_360_days',
            ]);
        });
    }
};
