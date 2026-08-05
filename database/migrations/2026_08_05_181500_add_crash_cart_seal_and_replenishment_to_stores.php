<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (! Schema::hasColumn('stores', 'crash_cart_seal_number')) {
                $table->string('crash_cart_seal_number', 64)->nullable()->after('crash_cart_status');
            }
            if (! Schema::hasColumn('stores', 'crash_cart_sealed_at')) {
                $table->timestamp('crash_cart_sealed_at')->nullable()->after('crash_cart_seal_number');
            }
            if (! Schema::hasColumn('stores', 'crash_cart_deployed_at')) {
                $table->timestamp('crash_cart_deployed_at')->nullable()->after('crash_cart_sealed_at');
            }
            if (! Schema::hasColumn('stores', 'crash_cart_last_replenishment_order_id')) {
                $table->foreignId('crash_cart_last_replenishment_order_id')
                    ->nullable()
                    ->after('crash_cart_deployed_at')
                    ->constrained('inventory_orders')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'crash_cart_last_replenishment_order_id')) {
                $table->dropConstrainedForeignId('crash_cart_last_replenishment_order_id');
            }
            foreach (['crash_cart_deployed_at', 'crash_cart_sealed_at', 'crash_cart_seal_number'] as $col) {
                if (Schema::hasColumn('stores', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
