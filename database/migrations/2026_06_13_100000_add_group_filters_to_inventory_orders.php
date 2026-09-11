<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->foreignId('group_id')
                ->nullable()
                ->after('importance_filter')
                ->constrained('groups')
                ->nullOnDelete();

            $table->foreignId('subgroup_id')
                ->nullable()
                ->after('group_id')
                ->constrained('sub_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropForeign(['subgroup_id']);
            $table->dropColumn(['group_id', 'subgroup_id']);
        });
    }
};
