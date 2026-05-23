<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_holiday_leave_credits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_assignment_id')->constrained('hr_staff_assignments')->cascadeOnDelete();
            $table->foreignId('hr_calendar_event_id')->constrained('hr_calendar_events')->cascadeOnDelete();
            $table->foreignId('source_in_ledger_id')->nullable()->constrained('hr_attendance_ledger')->nullOnDelete();
            $table->foreignId('source_out_ledger_id')->nullable()->constrained('hr_attendance_ledger')->nullOnDelete();
            $table->date('earned_on');
            $table->decimal('credit_days', 5, 2)->default(1);
            $table->string('status', 20)->default('available');
            $table->date('used_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'staff_assignment_id', 'hr_calendar_event_id', 'earned_on'],
                'hr_holiday_leave_credit_unique'
            );
            $table->index(['organization_id', 'staff_assignment_id', 'status'], 'hr_holiday_leave_credit_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_holiday_leave_credits');
    }
};
