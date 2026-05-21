<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->unsignedSmallInteger('emergency_display_duration')->default(0)->after('emergency_display_message');
        });
    }

    public function down(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->dropColumn('emergency_display_duration');
        });
    }
};
