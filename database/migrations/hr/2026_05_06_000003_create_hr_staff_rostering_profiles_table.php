<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_staff_rostering_profiles')) {
            return;
        }

        Schema::create('hr_staff_rostering_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_assignment_id')->constrained('hr_staff_assignments')->cascadeOnDelete();
            $table->string('rostering_mode', 30)->default('dynamic');
            $table->foreignId('fixed_shift_type_id')->nullable()->constrained('hr_shift_types')->nullOnDelete();
            $table->json('fixed_days_of_week')->nullable();
            $table->json('preferred_shift_type_ids')->nullable();
            $table->json('excluded_shift_type_ids')->nullable();
            $table->unsignedInteger('max_night_shifts_per_cycle')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['staff_assignment_id'], 'hr_staff_rostering_profiles_staff_unique');
            $table->index(['organization_id', 'rostering_mode'], 'hr_staff_rostering_profiles_mode_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_staff_rostering_profiles');
    }
};
