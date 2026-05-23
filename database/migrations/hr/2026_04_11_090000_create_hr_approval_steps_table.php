<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('hr_approval_requests')->cascadeOnDelete();
            $table->enum('approver_level', ['primary', 'secondary', 'tertiary']);
            $table->string('approver_staff_uuid');
            $table->string('approver_name');
            $table->enum('status', ['pending', 'approved', 'rejected', 'skipped'])->default('pending');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('acted_at')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->index(['approval_request_id', 'status']);
            $table->index(['approver_staff_uuid', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_approval_steps');
    }
};
