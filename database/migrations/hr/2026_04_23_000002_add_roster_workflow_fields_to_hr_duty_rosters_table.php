<?php

use App\Models\HrDutyRoster;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_duty_rosters')) {
            return;
        }

        Schema::table('hr_duty_rosters', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_duty_rosters', 'approval_request_id')) {
                $table->foreignId('approval_request_id')
                    ->nullable()
                    ->after('status')
                    ->constrained('hr_approval_requests')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'approval_status')) {
                $table->string('approval_status')
                    ->default(HrDutyRoster::APPROVAL_NOT_SUBMITTED)
                    ->after('approval_request_id');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('approval_status');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('submitted_at');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('published_at');
            }
        });

        DB::table('hr_duty_rosters')
            ->whereNull('approval_status')
            ->update([
                'approval_status' => HrDutyRoster::APPROVAL_NOT_SUBMITTED,
            ]);

        DB::table('hr_duty_rosters')
            ->where('status', HrDutyRoster::STATUS_PUBLISHED)
            ->update([
                'approval_status' => HrDutyRoster::APPROVAL_APPROVED,
                'published_at' => DB::raw('COALESCE(published_at, updated_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('hr_duty_rosters')) {
            return;
        }

        Schema::table('hr_duty_rosters', function (Blueprint $table) {
            if (Schema::hasColumn('hr_duty_rosters', 'approval_request_id')) {
                $table->dropConstrainedForeignId('approval_request_id');
            }

            foreach (['approval_status', 'submitted_at', 'published_at', 'rejected_at'] as $column) {
                if (Schema::hasColumn('hr_duty_rosters', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
