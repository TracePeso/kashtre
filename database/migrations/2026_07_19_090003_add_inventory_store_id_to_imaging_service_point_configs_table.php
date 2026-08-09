<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imaging_service_point_configs', function (Blueprint $table) {
            // Plain indexed reference to Store.id, not a FK — same
            // cross-domain-decoupling rule as hardware_ae_title's own
            // main_service_point_id column on this table.
            $table->unsignedBigInteger('inventory_store_id')->nullable()->index()->after('main_service_point_id');
        });
    }

    public function down(): void
    {
        Schema::table('imaging_service_point_configs', function (Blueprint $table) {
            $table->dropColumn('inventory_store_id');
        });
    }
};
