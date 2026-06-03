<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_daily_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->date('consumption_date');
            $table->decimal('quantity_suom', 14, 4);
            $table->string('source', 32)->default('manual');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['business_id', 'store_id', 'item_id', 'consumption_date', 'source'],
                'inventory_daily_consumptions_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_daily_consumptions');
    }
};
