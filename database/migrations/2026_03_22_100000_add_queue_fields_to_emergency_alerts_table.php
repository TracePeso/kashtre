<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_alerts', function (Blueprint $table) {
            $table->string('color')->nullable()->after('display_message');
            $table->timestamp('scheduled_announce_at')->nullable()->after('triggered_at');
        });
    }

    public function down(): void
    {
        Schema::table('emergency_alerts', function (Blueprint $table) {
            $table->dropColumn(['color', 'scheduled_announce_at']);
        });
    }
};
