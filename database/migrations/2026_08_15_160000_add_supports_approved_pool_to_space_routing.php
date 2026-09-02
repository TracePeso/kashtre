<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_space_store_assignments')
            && ! Schema::hasColumn('client_space_store_assignments', 'supports_approved_pool')) {
            Schema::table('client_space_store_assignments', function (Blueprint $table) {
                $table->boolean('supports_approved_pool')
                    ->default(true)
                    ->after('fulfillment_strategy');
            });
        }

        if (Schema::hasTable('inventory_fulfillment_lines')
            && ! Schema::hasColumn('inventory_fulfillment_lines', 'supports_approved_pool')) {
            Schema::table('inventory_fulfillment_lines', function (Blueprint $table) {
                $table->boolean('supports_approved_pool')
                    ->default(true)
                    ->after('fulfillment_strategy');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_fulfillment_lines')
            && Schema::hasColumn('inventory_fulfillment_lines', 'supports_approved_pool')) {
            Schema::table('inventory_fulfillment_lines', function (Blueprint $table) {
                $table->dropColumn('supports_approved_pool');
            });
        }

        if (Schema::hasTable('client_space_store_assignments')
            && Schema::hasColumn('client_space_store_assignments', 'supports_approved_pool')) {
            Schema::table('client_space_store_assignments', function (Blueprint $table) {
                $table->dropColumn('supports_approved_pool');
            });
        }
    }
};
