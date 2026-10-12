<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_ai_advice_logs', function (Blueprint $table) {
            $table->foreignId('inventory_order_id')
                ->nullable()
                ->after('store_id')
                ->constrained('inventory_orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_ai_advice_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_order_id');
        });
    }
};
