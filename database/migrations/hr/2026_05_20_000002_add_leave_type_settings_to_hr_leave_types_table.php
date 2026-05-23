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
            $table->string('session_type', 40)->default('full_day')->after('code');
            $table->decimal('days_deducted_per_workday', 4, 2)->default(1)->after('session_type');
            $table->boolean('tracks_balance')->default(true)->after('max_days_per_year');
            $table->boolean('is_paid')->default(true)->after('tracks_balance');
        });

        DB::table('hr_leave_types')
            ->where('name', 'like', '%Morning%')
            ->update([
                'session_type' => 'morning_absent',
                'days_deducted_per_workday' => 0.50,
            ]);

        DB::table('hr_leave_types')
            ->where('name', 'like', '%Afternoon%')
            ->update([
                'session_type' => 'afternoon_absent',
                'days_deducted_per_workday' => 0.50,
            ]);

        DB::table('hr_leave_types')
            ->where(function ($query): void {
                $query
                    ->where('name', 'like', '%without pay%')
                    ->orWhere('name', 'like', '%unpaid%');
            })
            ->update(['is_paid' => false]);
    }

    public function down(): void
    {
        Schema::table('hr_leave_types', function (Blueprint $table): void {
            $table->dropColumn([
                'session_type',
                'days_deducted_per_workday',
                'tracks_balance',
                'is_paid',
            ]);
        });
    }
};
