<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->string('emergency_button_1_name')->nullable()->after('emergency_repeat_interval');
            $table->string('emergency_button_1_message')->nullable()->after('emergency_button_1_name');
            $table->string('emergency_button_1_color')->nullable()->after('emergency_button_1_message');
            $table->string('emergency_button_2_name')->nullable()->after('emergency_button_1_color');
            $table->string('emergency_button_2_message')->nullable()->after('emergency_button_2_name');
            $table->string('emergency_button_2_color')->nullable()->after('emergency_button_2_message');
        });
    }

    public function down(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->dropColumn([
                'emergency_button_1_name', 'emergency_button_1_message', 'emergency_button_1_color',
                'emergency_button_2_name', 'emergency_button_2_message', 'emergency_button_2_color',
            ]);
        });
    }
};
