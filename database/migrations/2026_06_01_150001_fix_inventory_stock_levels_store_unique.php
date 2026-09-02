<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventory_stock_levels', 'store_id')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('inventory_stock_levels'))
            ->pluck('name');

        if (! $indexes->contains('inventory_stock_levels_business_store_item_unique')) {
            Schema::table('inventory_stock_levels', function (Blueprint $table) {
                $table->unique(
                    ['business_id', 'store_id', 'item_id'],
                    'inventory_stock_levels_business_store_item_unique'
                );
            });
        }

        if ($indexes->contains('inventory_stock_levels_business_id_item_id_unique')) {
            Schema::table('inventory_stock_levels', function (Blueprint $table) {
                $table->dropUnique('inventory_stock_levels_business_id_item_id_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('inventory_stock_levels', 'store_id')) {
            return;
        }

        Schema::table('inventory_stock_levels', function (Blueprint $table) {
            $table->unique(['business_id', 'item_id']);
            $table->dropUnique('inventory_stock_levels_business_store_item_unique');
        });
    }
};
