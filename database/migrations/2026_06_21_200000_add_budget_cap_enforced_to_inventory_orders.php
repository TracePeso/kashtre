<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->boolean('budget_cap_enforced')->default(true)->after('budget_value');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->dropColumn('budget_cap_enforced');
        });
    }
};
