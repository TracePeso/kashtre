<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            $table->index(
                ['business_id', 'store_id', 'item_id', 'occurred_at'],
                'inventory_movements_business_store_item_occurred_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            $table->dropIndex('inventory_movements_business_store_item_occurred_idx');
        });
    }
};
