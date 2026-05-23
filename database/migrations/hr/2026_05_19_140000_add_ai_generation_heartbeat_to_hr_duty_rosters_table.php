<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_duty_rosters')) {
            return;
        }

        Schema::table('hr_duty_rosters', function (Blueprint $table): void {
            if (! Schema::hasColumn('hr_duty_rosters', 'ai_generation_heartbeat_at')) {
                $table->timestamp('ai_generation_heartbeat_at')
                    ->nullable()
                    ->after('ai_generation_started_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hr_duty_rosters')) {
            return;
        }

        Schema::table('hr_duty_rosters', function (Blueprint $table): void {
            if (Schema::hasColumn('hr_duty_rosters', 'ai_generation_heartbeat_at')) {
                $table->dropColumn('ai_generation_heartbeat_at');
            }
        });
    }
};
