<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_open_shifts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_space_unit_id')->constrained('hr_organizational_units')->cascadeOnDelete();
            $table->foreignId('duty_roster_id')->nullable()->constrained('hr_duty_rosters')->nullOnDelete();
            $table->foreignId('shift_type_id')->constrained('hr_shift_types')->cascadeOnDelete();
            $table->foreignId('source_staff_assignment_id')->nullable()->constrained('hr_staff_assignments')->nullOnDelete();
            $table->foreignId('source_duty_roster_entry_id')->nullable()->constrained('hr_duty_roster_entries')->nullOnDelete();
            $table->foreignId('filled_by_staff_assignment_id')->nullable()->constrained('hr_staff_assignments')->nullOnDelete();
            $table->foreignId('filled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('roster_date');
            $table->string('discipline_key', 120);
            $table->string('discipline_label', 120);
            $table->unsignedInteger('expected_headcount')->default(1);
            $table->string('source_type', 40)->default('coverage_gap');
            $table->string('status', 20)->default('open');
            $table->text('source_reason')->nullable();
            $table->timestamp('filled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'roster_date'], 'hr_open_shifts_status_idx');
            $table->index(
                ['duty_roster_id', 'roster_date', 'shift_type_id', 'discipline_key'],
                'hr_open_shifts_roster_group_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_open_shifts');
    }
};
