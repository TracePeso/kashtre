<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->unsignedInteger('emergency_key_cooldown')->default(180)->after('emergency_display_duration');
            // Stored in seconds; UI converts to/from seconds
        });
    }

    public function down(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->dropColumn('emergency_key_cooldown');
        });
    }
};
