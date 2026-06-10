<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_module_configs', function (Blueprint $table) {
            $table->unsignedTinyInteger('financial_year_start_month')
                ->default(1)
                ->after('period_of_order_days');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_module_configs', function (Blueprint $table) {
            $table->dropColumn('financial_year_start_month');
        });
    }
};
