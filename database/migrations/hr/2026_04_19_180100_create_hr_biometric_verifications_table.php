<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_biometric_verifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hr_biometric_profile_id')->nullable()->constrained('hr_biometric_profiles')->nullOnDelete();
            $table->foreignId('staff_assignment_id')->nullable()->constrained('hr_staff_assignments')->nullOnDelete();
            $table->string('staff_uuid')->nullable();
            $table->enum('modality', ['fingerprint', 'face']);
            $table->enum('result', ['success', 'failed'])->default('failed');
            $table->decimal('score', 7, 4)->nullable();
            $table->decimal('threshold', 6, 4)->nullable();
            $table->string('provider')->default('local');
            $table->string('device_id')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'modality', 'result']);
            $table->index(['organization_id', 'staff_uuid']);
            $table->index('verified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_biometric_verifications');
    }
};
