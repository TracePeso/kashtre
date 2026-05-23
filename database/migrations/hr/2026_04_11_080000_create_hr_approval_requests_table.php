<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_workflow_id')->constrained('hr_approval_workflows')->cascadeOnDelete();
            $table->enum('approval_category', ['leave', 'roster', 'coverage', 'offsite_duty']);
            $table->string('requester_staff_uuid');
            $table->string('requester_name');
            $table->string('subject');
            $table->text('details')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->enum('current_level', ['primary', 'secondary', 'tertiary'])->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'approval_category']);
            $table->index(['organization_id', 'status']);
            $table->index(['current_level', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_approval_requests');
    }
};
