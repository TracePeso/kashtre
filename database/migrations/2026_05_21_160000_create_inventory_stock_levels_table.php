<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('quantity_suom', 14, 4)->default(0);
            $table->decimal('daily_usage_suom', 14, 4)->nullable();
            $table->decimal('last_purchase_price', 14, 2)->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_levels');
    }
};
