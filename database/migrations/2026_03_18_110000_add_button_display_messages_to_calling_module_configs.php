<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->text('emergency_button_1_display_message')->nullable()->after('emergency_button_1_message');
            $table->text('emergency_button_2_display_message')->nullable()->after('emergency_button_2_message');
        });
    }

    public function down(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->dropColumn(['emergency_button_1_display_message', 'emergency_button_2_display_message']);
        });
    }
};
