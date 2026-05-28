<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_leave_types', function (Blueprint $table): void {
            $table->string('advance_notice_timing', 20)
                ->default('pre')
                ->after('days_deducted_per_workday');
            $table->unsignedSmallInteger('advance_notice_days')
                ->default(0)
                ->after('advance_notice_timing');
        });

        DB::table('hr_leave_types')
            ->update([
                'advance_notice_timing' => 'pre',
                'advance_notice_days' => 0,
            ]);
    }

    public function down(): void
    {
        Schema::table('hr_leave_types', function (Blueprint $table): void {
            $table->dropColumn([
                'advance_notice_timing',
                'advance_notice_days',
            ]);
        });
    }
};
