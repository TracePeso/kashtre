<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('order_number', 64);
            $table->string('status', 32)->default('draft');
            $table->string('importance_filter', 32)->nullable();
            $table->string('budget_mode', 16)->nullable();
            $table->decimal('budget_value', 14, 2)->nullable();
            $table->unsignedSmallInteger('moving_average_days')->default(30);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'order_number']);
        });

        Schema::create('inventory_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_order_id')->constrained('inventory_orders')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->decimal('daily_average_suom', 14, 4)->default(0);
            $table->unsignedSmallInteger('lead_time_days')->default(0);
            $table->decimal('system_quantity_suom', 14, 4)->default(0);
            $table->decimal('suggested_quantity_suom', 14, 4)->default(0);
            $table->decimal('order_quantity_suom', 14, 4)->default(0);
            $table->decimal('order_quantity_ouom', 14, 4)->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('line_total', 14, 2)->nullable();
            $table->timestamps();

            $table->unique(['inventory_order_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_order_lines');
        Schema::dropIfExists('inventory_orders');
    }
};
