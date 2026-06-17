<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_monthly_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->date('consumption_month');
            $table->decimal('total_quantity_suom', 14, 4)->default(0);
            $table->unsignedSmallInteger('days_with_usage')->default(0);
            $table->timestamps();

            $table->unique(
                ['business_id', 'store_id', 'item_id', 'consumption_month'],
                'imc_business_store_item_month'
            );
            $table->index(['business_id', 'consumption_month'], 'imc_business_month');
            $table->index(['business_id', 'store_id', 'consumption_month'], 'imc_business_store_month');
        });

        DB::statement("
            INSERT INTO inventory_monthly_consumptions (
                business_id, store_id, item_id, consumption_month,
                total_quantity_suom, days_with_usage, created_at, updated_at
            )
            SELECT
                business_id,
                store_id,
                item_id,
                STR_TO_DATE(CONCAT(month_key, '-01'), '%Y-%m-%d'),
                total_quantity_suom,
                days_with_usage,
                NOW(),
                NOW()
            FROM (
                SELECT
                    business_id,
                    store_id,
                    item_id,
                    DATE_FORMAT(consumption_date, '%Y-%m') AS month_key,
                    SUM(quantity_suom) AS total_quantity_suom,
                    COUNT(DISTINCT consumption_date) AS days_with_usage
                FROM inventory_daily_consumptions
                GROUP BY
                    business_id,
                    store_id,
                    item_id,
                    DATE_FORMAT(consumption_date, '%Y-%m')
            ) AS aggregated
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_monthly_consumptions');
    }
};
