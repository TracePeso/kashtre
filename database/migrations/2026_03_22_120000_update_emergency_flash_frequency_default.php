<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->integer('emergency_flash_frequency')->default(2)->change();
        });

        // Reset any existing rows that still hold the old ms default (1200)
        // to the new minutes default (1)
        DB::table('calling_module_configs')
            ->where('emergency_flash_frequency', 1200)
            ->update(['emergency_flash_frequency' => 1]);
    }

    public function down(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->integer('emergency_flash_frequency')->default(1200)->change();
        });
    }
};
