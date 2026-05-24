<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->string('emergency_tts_voice_id')->nullable()->after('emergency_flash_frequency');
            $table->string('emergency_tts_voice_name')->nullable()->after('emergency_tts_voice_id');
            $table->float('emergency_tts_stability')->default(0.5)->after('emergency_tts_voice_name');
            $table->float('emergency_tts_similarity_boost')->default(0.75)->after('emergency_tts_stability');
            $table->float('emergency_tts_speed')->default(1.0)->after('emergency_tts_similarity_boost');
        });
    }

    public function down(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->dropColumn([
                'emergency_tts_voice_id',
                'emergency_tts_voice_name',
                'emergency_tts_stability',
                'emergency_tts_similarity_boost',
                'emergency_tts_speed',
            ]);
        });
    }
};
