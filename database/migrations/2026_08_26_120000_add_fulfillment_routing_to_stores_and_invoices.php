<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (! Schema::hasColumn('stores', 'default_fulfillment_strategy')) {
                $table->string('default_fulfillment_strategy', 40)
                    ->nullable()
                    ->after('distribution_type');
            }
            if (! Schema::hasColumn('stores', 'supports_approved_pool')) {
                $table->boolean('supports_approved_pool')
                    ->default(true)
                    ->after('default_fulfillment_strategy');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'fulfillment_strategy')) {
                $table->string('fulfillment_strategy', 40)->nullable()->after('branch_id');
            }
            if (! Schema::hasColumn('invoices', 'end_store_id')) {
                $table->unsignedBigInteger('end_store_id')->nullable()->after('fulfillment_strategy');
                $table->index('end_store_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'supports_approved_pool')) {
                $table->dropColumn('supports_approved_pool');
            }
            if (Schema::hasColumn('stores', 'default_fulfillment_strategy')) {
                $table->dropColumn('default_fulfillment_strategy');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'end_store_id')) {
                $table->dropColumn('end_store_id');
            }
            if (Schema::hasColumn('invoices', 'fulfillment_strategy')) {
                $table->dropColumn('fulfillment_strategy');
            }
        });
    }
};
