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
            if (! Schema::hasColumn('hr_duty_rosters', 'ai_generation_status')) {
                $table->string('ai_generation_status', 30)->nullable()->after('rejected_at');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'ai_generation_token')) {
                $table->uuid('ai_generation_token')->nullable()->after('ai_generation_status');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'ai_generation_message')) {
                $table->text('ai_generation_message')->nullable()->after('ai_generation_token');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'ai_generation_attempts')) {
                $table->unsignedInteger('ai_generation_attempts')->default(0)->after('ai_generation_message');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'ai_generation_started_at')) {
                $table->timestamp('ai_generation_started_at')->nullable()->after('ai_generation_attempts');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'ai_generation_completed_at')) {
                $table->timestamp('ai_generation_completed_at')->nullable()->after('ai_generation_started_at');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'ai_generation_failed_at')) {
                $table->timestamp('ai_generation_failed_at')->nullable()->after('ai_generation_completed_at');
            }

        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hr_duty_rosters')) {
            return;
        }

        Schema::table('hr_duty_rosters', function (Blueprint $table): void {
            foreach ([
                'ai_generation_failed_at',
                'ai_generation_completed_at',
                'ai_generation_started_at',
                'ai_generation_attempts',
                'ai_generation_message',
                'ai_generation_token',
                'ai_generation_status',
            ] as $column) {
                if (Schema::hasColumn('hr_duty_rosters', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
