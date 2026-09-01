<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_demand_ledgers', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_demand_ledgers', 'store_id')) {
                $table->unsignedBigInteger('store_id')->nullable()->after('business_id');
                $table->index(['business_id', 'store_id', 'item_id', 'occurred_at'], 'demand_ledgers_store_item_occurred_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_demand_ledgers', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_demand_ledgers', 'store_id')) {
                $table->dropIndex('demand_ledgers_store_item_occurred_idx');
                $table->dropColumn('store_id');
            }
        });
    }
};
