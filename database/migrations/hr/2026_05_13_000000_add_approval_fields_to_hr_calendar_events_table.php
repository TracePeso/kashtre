<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_calendar_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('hr_calendar_events', 'approval_status')) {
                $table->string('approval_status', 20)->default('approved')->after('is_active');
            }

            if (! Schema::hasColumn('hr_calendar_events', 'approved_by_user_id')) {
                $table->foreignId('approved_by_user_id')
                    ->nullable()
                    ->after('approval_status')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('hr_calendar_events', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            }
        });

        DB::table('hr_calendar_events')
            ->whereNull('approved_at')
            ->update([
                'approval_status' => 'approved',
                'approved_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('hr_calendar_events', function (Blueprint $table): void {
            if (Schema::hasColumn('hr_calendar_events', 'approved_by_user_id')) {
                $table->dropConstrainedForeignId('approved_by_user_id');
            }

            if (Schema::hasColumn('hr_calendar_events', 'approved_at')) {
                $table->dropColumn('approved_at');
            }

            if (Schema::hasColumn('hr_calendar_events', 'approval_status')) {
                $table->dropColumn('approval_status');
            }
        });
    }
};
