<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organizations')) {
            Schema::table('organizations', function (Blueprint $table): void {
                if (! Schema::hasColumn('organizations', 'biometric_late_clock_in_enabled')) {
                    $table->boolean('biometric_late_clock_in_enabled')->default(false)->after('biometric_geofence_max_accuracy_meters');
                }

                if (! Schema::hasColumn('organizations', 'biometric_late_clock_in_threshold_minutes')) {
                    $table->unsignedInteger('biometric_late_clock_in_threshold_minutes')->nullable()->after('biometric_late_clock_in_enabled');
                }
            });
        }

        if (Schema::hasTable('hr_attendance_ledger')) {
            Schema::table('hr_attendance_ledger', function (Blueprint $table): void {
                if (! Schema::hasColumn('hr_attendance_ledger', 'is_late_clock_in')) {
                    $table->boolean('is_late_clock_in')->default(false)->after('status');
                }

                if (! Schema::hasColumn('hr_attendance_ledger', 'minutes_late')) {
                    $table->unsignedInteger('minutes_late')->nullable()->after('is_late_clock_in');
                }

                if (! Schema::hasColumn('hr_attendance_ledger', 'is_late_flagged')) {
                    $table->boolean('is_late_flagged')->default(false)->after('minutes_late');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hr_attendance_ledger')) {
            Schema::table('hr_attendance_ledger', function (Blueprint $table): void {
                foreach (['is_late_flagged', 'minutes_late', 'is_late_clock_in'] as $column) {
                    if (Schema::hasColumn('hr_attendance_ledger', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('organizations')) {
            Schema::table('organizations', function (Blueprint $table): void {
                foreach (['biometric_late_clock_in_threshold_minutes', 'biometric_late_clock_in_enabled'] as $column) {
                    if (Schema::hasColumn('organizations', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
