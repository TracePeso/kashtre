<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_biometric_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_assignment_id')->constrained('hr_staff_assignments')->cascadeOnDelete();
            $table->string('staff_uuid');
            $table->string('staff_name');
            $table->enum('modality', ['fingerprint', 'face']);
            $table->string('label')->nullable();
            $table->string('provider')->default('local');
            $table->string('device_id')->nullable();
            $table->string('external_reference')->nullable();
            $table->string('template_digest', 128)->nullable();
            $table->longText('template_payload')->nullable();
            $table->longText('face_descriptor')->nullable();
            $table->decimal('quality_score', 6, 2)->nullable();
            $table->decimal('verification_threshold', 6, 4)->default(0.8500);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('enrolled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'modality', 'status']);
            $table->index(['organization_id', 'staff_uuid']);
            $table->index('template_digest');
            $table->index('external_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_biometric_profiles');
    }
};
