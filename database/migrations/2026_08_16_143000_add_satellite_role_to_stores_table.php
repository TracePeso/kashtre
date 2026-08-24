<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stores')) {
            return;
        }

        if (! Schema::hasColumn('stores', 'satellite_role')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->string('satellite_role', 32)->nullable()->after('distribution_type');
            });
        }

        // Backfill: crash-cart satellites → crash_cart; other satellites → normal.
        if (Schema::hasColumn('stores', 'is_crash_cart')) {
            DB::table('stores')
                ->where('distribution_type', 'satellite_store')
                ->where('is_crash_cart', true)
                ->whereNull('satellite_role')
                ->update(['satellite_role' => 'crash_cart']);

            DB::table('stores')
                ->where('distribution_type', 'satellite_store')
                ->where(function ($q) {
                    $q->where('is_crash_cart', false)->orWhereNull('is_crash_cart');
                })
                ->whereNull('satellite_role')
                ->update(['satellite_role' => 'normal']);
        } else {
            DB::table('stores')
                ->where('distribution_type', 'satellite_store')
                ->whereNull('satellite_role')
                ->update(['satellite_role' => 'normal']);
        }

        // Non-satellites stay null.
        DB::table('stores')
            ->where(function ($q) {
                $q->whereNull('distribution_type')
                    ->orWhere('distribution_type', '!=', 'satellite_store');
            })
            ->update(['satellite_role' => null]);
    }

    public function down(): void
    {
        if (Schema::hasTable('stores') && Schema::hasColumn('stores', 'satellite_role')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropColumn('satellite_role');
            });
        }
    }
};
