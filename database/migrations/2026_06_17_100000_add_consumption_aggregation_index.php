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
                ['business_id', 'consumption_date', 'store_id', 'item_id'],
                'idc_business_date_store_item'
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventory_daily_consumptions', function (Blueprint $table) {
            $table->dropIndex('idc_business_date_store_item');
        });
    }
};
