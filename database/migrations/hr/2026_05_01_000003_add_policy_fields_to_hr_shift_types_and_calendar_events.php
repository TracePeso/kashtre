<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_shift_types', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_shift_types', 'gross_duration_minutes')) {
                $table->unsignedSmallInteger('gross_duration_minutes')->nullable()->after('end_time');
            }

            if (! Schema::hasColumn('hr_shift_types', 'net_duration_minutes')) {
                $table->unsignedSmallInteger('net_duration_minutes')->nullable()->after('break_duration_minutes');
            }

            if (! Schema::hasColumn('hr_shift_types', 'is_rosterable')) {
                $table->boolean('is_rosterable')->default(true)->after('is_active');
            }
        });

        Schema::table('hr_calendar_events', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_calendar_events', 'reward_type')) {
                $table->string('reward_type', 40)->nullable()->after('affects_rosters');
            }

            if (! Schema::hasColumn('hr_calendar_events', 'blocks_rosters')) {
                $table->boolean('blocks_rosters')->default(false)->after('reward_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hr_calendar_events', function (Blueprint $table) {
            if (Schema::hasColumn('hr_calendar_events', 'blocks_rosters')) {
                $table->dropColumn('blocks_rosters');
            }

            if (Schema::hasColumn('hr_calendar_events', 'reward_type')) {
                $table->dropColumn('reward_type');
            }
        });

        Schema::table('hr_shift_types', function (Blueprint $table) {
            if (Schema::hasColumn('hr_shift_types', 'is_rosterable')) {
                $table->dropColumn('is_rosterable');
            }

            if (Schema::hasColumn('hr_shift_types', 'net_duration_minutes')) {
                $table->dropColumn('net_duration_minutes');
            }

            if (Schema::hasColumn('hr_shift_types', 'gross_duration_minutes')) {
                $table->dropColumn('gross_duration_minutes');
            }
        });
    }
};
