<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_consumption_events', function (Blueprint $table) {
            $table->foreignId('sale_id')
                ->nullable()
                ->after('source')
                ->constrained('sales')
                ->nullOnDelete();

            $table->index(['sale_id'], 'ice_sale_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_consumption_events', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->dropIndex('ice_sale_id');
            $table->dropColumn('sale_id');
        });
    }
};
