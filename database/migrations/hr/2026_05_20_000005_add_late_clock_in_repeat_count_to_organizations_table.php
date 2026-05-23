<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organizations')) {
            return;
        }

        Schema::table('organizations', function (Blueprint $table): void {
            if (! Schema::hasColumn('organizations', 'biometric_late_clock_in_repeat_count')) {
                $table->unsignedInteger('biometric_late_clock_in_repeat_count')
                    ->default(3)
                    ->after('biometric_late_clock_in_threshold_minutes');
            }
        });

        DB::table('organizations')
            ->whereNull('biometric_late_clock_in_repeat_count')
            ->update(['biometric_late_clock_in_repeat_count' => 3]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('organizations')) {
            return;
        }

        Schema::table('organizations', function (Blueprint $table): void {
            if (Schema::hasColumn('organizations', 'biometric_late_clock_in_repeat_count')) {
                $table->dropColumn('biometric_late_clock_in_repeat_count');
            }
        });
    }
};
