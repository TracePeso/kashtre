<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_biometric_enrollment_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_assignment_id')->constrained('hr_staff_assignments')->cascadeOnDelete();
            $table->string('staff_uuid');
            $table->string('staff_name');
            $table->string('recipient_email');
            $table->string('purpose', 40)->default('enrollment');
            $table->string('secret_code_hash');
            $table->timestamp('secret_code_sent_at')->nullable();
            $table->timestamp('secret_code_expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('capture_deadline_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->foreignId('authorized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'staff_assignment_id'], 'hr_bio_enroll_org_staff_idx');
            $table->index(['organization_id', 'authorized_by_user_id'], 'hr_bio_enroll_org_actor_idx');
            $table->index(['confirmed_at', 'capture_deadline_at'], 'hr_bio_enroll_window_idx');
            $table->index(['secret_code_expires_at', 'invalidated_at'], 'hr_bio_enroll_code_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_biometric_enrollment_sessions');
    }
};
