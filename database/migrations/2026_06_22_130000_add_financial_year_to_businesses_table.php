<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->unsignedTinyInteger('financial_year_start_month')->default(1)->after('default_payment_terms_days');
            $table->unsignedTinyInteger('financial_year_start_day')->default(1)->after('financial_year_start_month');
        });

        if (Schema::hasTable('inventory_module_configs')) {
            $configs = DB::table('inventory_module_configs')
                ->select('business_id', 'financial_year_start_month')
                ->get();

            foreach ($configs as $config) {
                DB::table('businesses')
                    ->where('id', $config->business_id)
                    ->update(['financial_year_start_month' => $config->financial_year_start_month]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['financial_year_start_month', 'financial_year_start_day']);
        });
    }
};
