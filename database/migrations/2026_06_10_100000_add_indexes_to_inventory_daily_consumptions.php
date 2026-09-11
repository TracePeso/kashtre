<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_daily_consumptions', function (Blueprint $table) {
            $table->index(
                ['business_id', 'consumption_date'],
                'idc_business_consumption_date'
            );
            $table->index(
                ['business_id', 'store_id', 'consumption_date'],
                'idc_business_store_date'
            );
            $table->index(
                ['business_id', 'item_id', 'consumption_date'],
                'idc_business_item_date'
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventory_daily_consumptions', function (Blueprint $table) {
            $table->dropIndex('idc_business_consumption_date');
            $table->dropIndex('idc_business_store_date');
            $table->dropIndex('idc_business_item_date');
        });
    }
};
