<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->string('tts_voice_id')->nullable()->after('is_active');
            $table->string('tts_voice_name')->nullable()->after('tts_voice_id');
            $table->float('tts_stability')->default(0.5)->after('tts_voice_name');
            $table->float('tts_similarity_boost')->default(0.75)->after('tts_stability');
            $table->float('tts_speed')->default(1.0)->after('tts_similarity_boost');
            $table->text('announcement_message')->nullable()->after('tts_speed');
        });
    }

    public function down(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->dropColumn([
                'tts_voice_id',
                'tts_voice_name',
                'tts_stability',
                'tts_similarity_boost',
                'tts_speed',
                'announcement_message',
            ]);
        });
    }
};
