<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        // One row per actual admission/transfer/discharge/referral/death
        // event for a specific patient — mirrors Imaging's
        // ImagingStudyWorkflowExecution (config vs. instance split).
        Schema::connection('clinical')->create('clinical_process_executions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('client_id')->index();
            $table->string('visit_id')->nullable();
            $table->foreignId('process_id')->constrained('clinical_processes'); // same connection
            $table->enum('status', ['IN_PROGRESS', 'COMPLETED', 'CANCELLED'])->default('IN_PROGRESS');
            $table->foreignId('current_step_id')->nullable()->constrained('clinical_process_steps');
            $table->text('initiation_note')->nullable(); // admission note / referral justification / etc.
            $table->unsignedBigInteger('started_by_user_id');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();

            $table->index(['business_id', 'client_id', 'status'], 'idx_process_exec_patient');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_process_executions');
    }
};
