<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->unsignedSmallInteger('emergency_flash_on')->default(3)->after('emergency_flash_frequency');
            $table->unsignedSmallInteger('emergency_flash_off')->default(1)->after('emergency_flash_on');
        });
    }

    public function down(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->dropColumn(['emergency_flash_on', 'emergency_flash_off']);
        });
    }
};
