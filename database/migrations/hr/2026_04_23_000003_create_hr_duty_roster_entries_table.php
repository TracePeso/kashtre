<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_duty_roster_entries')) {
            return;
        }

        Schema::create('hr_duty_roster_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('duty_roster_id')->constrained('hr_duty_rosters')->cascadeOnDelete();
            $table->date('roster_date');
            $table->foreignId('staff_assignment_id')->nullable()->constrained('hr_staff_assignments')->nullOnDelete();
            $table->string('staff_uuid')->nullable();
            $table->string('staff_name');
            $table->string('staff_cadre')->nullable();
            $table->foreignId('shift_type_id')->nullable()->constrained('hr_shift_types')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'roster_date']);
            $table->index(['duty_roster_id', 'roster_date']);
            $table->index(['staff_assignment_id', 'roster_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_duty_roster_entries');
    }
};
