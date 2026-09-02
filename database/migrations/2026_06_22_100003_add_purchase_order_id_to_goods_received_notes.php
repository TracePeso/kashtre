<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_received_notes', function (Blueprint $table) {
            $table->foreignId('inventory_purchase_order_id')
                ->nullable()
                ->after('inventory_order_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_received_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_purchase_order_id');
        });
    }
};
