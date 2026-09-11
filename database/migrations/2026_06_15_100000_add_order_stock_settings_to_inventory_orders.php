<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->decimal('safety_stock_days', 8, 2)
                ->nullable()
                ->after('period_of_order_days');

            $table->decimal('buffer_stock_days', 8, 2)
                ->nullable()
                ->after('safety_stock_days');

            $table->decimal('notification_to_order_days', 8, 2)
                ->nullable()
                ->after('buffer_stock_days');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->dropColumn([
                'safety_stock_days',
                'buffer_stock_days',
                'notification_to_order_days',
            ]);
        });
    }
};
