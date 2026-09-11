<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->decimal('peak_consumption_increase_percent', 8, 4)
                ->nullable()
                ->default(0)
                ->after('peak_period_percent');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->dropColumn('peak_consumption_increase_percent');
        });
    }
};
