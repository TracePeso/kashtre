<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('reference', 64);
            $table->string('status', 32)->default('draft');
            $table->timestamp('counted_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'reference']);
        });

        Schema::create('inventory_stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_stock_count_id')->constrained('inventory_stock_counts')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('system_quantity_suom', 14, 4)->default(0);
            $table->decimal('physical_quantity_suom', 14, 4)->default(0);
            $table->decimal('damaged_quantity_suom', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(['inventory_stock_count_id', 'item_id'], 'inv_stock_count_lines_count_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_count_lines');
        Schema::dropIfExists('inventory_stock_counts');
    }
};
