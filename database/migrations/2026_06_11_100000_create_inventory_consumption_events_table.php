<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_consumption_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('quantity_suom', 14, 4);
            $table->timestamp('occurred_at');
            $table->string('source', 32)->default('manual');
            $table->timestamps();

            $table->index(
                ['business_id', 'store_id', 'item_id', 'occurred_at'],
                'ice_business_store_item_occurred'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_consumption_events');
    }
};
