<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crash_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('par_quantity', 16, 4);
            $table->timestamps();

            $table->unique(['store_id', 'item_id']);
        });

        if (Schema::hasColumn('stores', 'crash_cart_status')) {
            DB::table('stores')
                ->whereIn('crash_cart_status', ['deployed', 'reconciling'])
                ->update(['crash_cart_status' => 'open']);
        }

        $crashCartIds = DB::table('stores')
            ->where('distribution_type', 'satellite_store')
            ->where(function ($q) {
                $q->where('satellite_role', 'crash_cart')
                    ->orWhere('is_crash_cart', true);
            })
            ->pluck('id');

        foreach ($crashCartIds as $storeId) {
            if (DB::table('crash_cart_items')->where('store_id', $storeId)->exists()) {
                continue;
            }

            $stockRows = DB::table('inventory_stock_levels')
                ->where('store_id', $storeId)
                ->where('quantity_suom', '>', 0)
                ->get(['item_id', 'quantity_suom']);

            foreach ($stockRows as $row) {
                DB::table('crash_cart_items')->insert([
                    'store_id' => $storeId,
                    'item_id' => $row->item_id,
                    'par_quantity' => $row->quantity_suom,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crash_cart_items');
    }
};
