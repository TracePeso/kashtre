<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_order_lines', function (Blueprint $table) {
            $table->decimal('stock_days_at_order', 20, 4)->nullable()->change();
            $table->decimal('days_left_at_order', 20, 4)->nullable()->change();
            $table->decimal('order_days', 20, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_order_lines', function (Blueprint $table) {
            $table->decimal('stock_days_at_order', 8, 1)->nullable()->change();
            $table->decimal('days_left_at_order', 8, 1)->nullable()->change();
            $table->decimal('order_days', 10, 4)->nullable()->change();
        });
    }
};
