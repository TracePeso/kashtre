<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RIS Amendment v2.6, Chunk 3: tracks one study's live position in
        // its protocol workflow — created by WorkflowEngineService::startWorkflow()
        // (or lazily, for a study that existed before this chunk shipped,
        // by resolveOrStartExecution() positioning it at whichever step
        // matches its current status rather than restarting at step 1).
        // current_protocol_workflow_step_id points at the *slot* in the
        // workflow (imaging_protocol_workflow_steps), not the shared step
        // definition (imaging_workflow_steps) — the spec's own sample named
        // this column ambiguously as "current_step_id"; renamed here for
        // precision, since the same shared step can appear in different
        // workflows at different positions.
        Schema::create('imaging_study_workflow_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_study_id')
                ->constrained('imaging_studies')
                ->cascadeOnDelete();
            $table->foreignId('imaging_protocol_workflow_id')
                ->constrained('imaging_protocol_workflows', 'id', 'iswe_protocol_workflow_fk')
                ->cascadeOnDelete();
            $table->foreignId('current_protocol_workflow_step_id')
                ->constrained('imaging_protocol_workflow_steps', 'id', 'iswe_current_step_fk');
            $table->enum('status', ['ACTIVE', 'COMPLETED', 'CANCELLED'])->default('ACTIVE');
            $table->timestamps();

            $table->index(['imaging_study_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_study_workflow_executions');
    }
};
