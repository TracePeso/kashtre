<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_attendance_ledger')) {
            return;
        }

        Schema::create('hr_attendance_ledger', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_assignment_id')->nullable()->constrained('hr_staff_assignments')->nullOnDelete();
            $table->string('staff_uuid')->nullable();
            $table->foreignId('hr_biometric_verification_id')->nullable()->constrained('hr_biometric_verifications')->nullOnDelete();
            $table->foreignId('roster_entry_id')->nullable()->constrained('hr_duty_roster_entries')->nullOnDelete();
            $table->foreignId('client_space_unit_id')->nullable()->constrained('hr_organizational_units')->nullOnDelete();
            $table->foreignId('shift_type_id')->nullable()->constrained('hr_shift_types')->nullOnDelete();
            $table->enum('punch_type', ['in', 'out']);
            $table->string('punch_source', 160);
            $table->string('provider', 80)->default('local');
            $table->string('device_id', 120)->nullable();
            $table->string('source_event_id', 191)->nullable();
            $table->timestamp('occurred_at');
            $table->foreignId('paired_with_id')->nullable()->constrained('hr_attendance_ledger')->nullOnDelete();
            $table->enum('status', ['open', 'paired', 'ignored'])->default('open');
            $table->string('ignored_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'provider', 'device_id', 'source_event_id'],
                'hr_attendance_ledger_source_event_unique'
            );
            $table->index(['organization_id', 'staff_assignment_id', 'occurred_at'], 'hr_attendance_staff_time_index');
            $table->index(['organization_id', 'staff_assignment_id', 'punch_type', 'occurred_at'], 'hr_attendance_staff_punch_time_index');
            $table->index(['organization_id', 'status', 'occurred_at'], 'hr_attendance_status_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance_ledger');
    }
};
