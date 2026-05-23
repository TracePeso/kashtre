<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_approval_steps', function (Blueprint $table) {
            $table->boolean('is_current')->default(false)->after('status');
            $table->index(['approver_staff_uuid', 'status', 'is_current'], 'approval_step_current_approver_index');
        });

        DB::table('hr_approval_requests')
            ->where('status', 'pending')
            ->orderBy('id')
            ->each(function ($request) {
                $step = DB::table('hr_approval_steps')
                    ->where('approval_request_id', $request->id)
                    ->where('status', 'pending')
                    ->orderByRaw("CASE approver_level WHEN 'primary' THEN 1 WHEN 'secondary' THEN 2 WHEN 'tertiary' THEN 3 ELSE 4 END")
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->first();

                if ($step) {
                    DB::table('hr_approval_steps')
                        ->where('id', $step->id)
                        ->update(['is_current' => true]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('hr_approval_steps', function (Blueprint $table) {
            $table->dropIndex('approval_step_current_approver_index');
            $table->dropColumn('is_current');
        });
    }
};
