<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_approval_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('hr_approval_requests')->cascadeOnDelete();
            $table->foreignId('approval_step_id')->nullable()->constrained('hr_approval_steps')->nullOnDelete();
            $table->string('actor_staff_uuid')->nullable();
            $table->string('actor_name')->nullable();
            $table->enum('action', ['submitted', 'approved', 'rejected', 'cancelled', 'advanced']);
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('comments')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['approval_request_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_approval_events');
    }
};
