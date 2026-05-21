<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->unsignedSmallInteger('emergency_repeat_count')->default(3)->after('default_emergency_message');
            $table->unsignedSmallInteger('emergency_repeat_interval')->default(5)->after('emergency_repeat_count');
        });
    }

    public function down(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->dropColumn(['emergency_repeat_count', 'emergency_repeat_interval']);
        });
    }
};
