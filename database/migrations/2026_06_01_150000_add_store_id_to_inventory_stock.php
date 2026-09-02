<?php

use App\Models\GoodsReceivedNote;
use App\Models\InventoryStockLevel;
use App\Models\InventoryStockMovement;
use App\Models\Store;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stock_levels', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('business_id')->constrained('stores')->nullOnDelete();
        });

        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('item_id')->constrained('stores')->nullOnDelete();
        });

        $this->backfillStoreIds();

        Schema::table('inventory_stock_levels', function (Blueprint $table) {
            $table->unique(
                ['business_id', 'store_id', 'item_id'],
                'inventory_stock_levels_business_store_item_unique'
            );
        });

        Schema::table('inventory_stock_levels', function (Blueprint $table) {
            $table->dropUnique('inventory_stock_levels_business_id_item_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stock_levels', function (Blueprint $table) {
            $table->unique(['business_id', 'item_id']);
            $table->dropUnique('inventory_stock_levels_business_store_item_unique');
            $table->dropConstrainedForeignId('store_id');
        });

        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
        });
    }

    private function backfillStoreIds(): void
    {
        InventoryStockMovement::query()
            ->whereNull('store_id')
            ->whereNotNull('goods_received_note_id')
            ->with('goodsReceivedNote:id,store_id')
            ->eachById(function (InventoryStockMovement $movement) {
                $storeId = $movement->goodsReceivedNote?->store_id;

                if ($storeId) {
                    $movement->update(['store_id' => $storeId]);
                }
            });

        InventoryStockLevel::query()
            ->whereNull('store_id')
            ->eachById(function (InventoryStockLevel $level) {
                $storeId = InventoryStockMovement::query()
                    ->where('business_id', $level->business_id)
                    ->where('item_id', $level->item_id)
                    ->whereNotNull('store_id')
                    ->latest('occurred_at')
                    ->value('store_id');

                if (! $storeId) {
                    $storeId = Store::query()
                        ->where('business_id', $level->business_id)
                        ->orderBy('id')
                        ->value('id');
                }

                if ($storeId) {
                    $level->update(['store_id' => $storeId]);
                }
            });
    }
};
