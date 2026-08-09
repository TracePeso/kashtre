<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RIS Amendment v2.6, Chunk 4: one row per user claiming ownership of
        // a study at its current workflow step — created by
        // WorkflowOwnershipService::claimStudy()/transferStudy(), released by
        // releaseStudy() or automatically by WorkflowEngineService::completeStep()
        // when the study advances past this step. "Active" = released_at null;
        // history stays for audit (who worked this step, when).
        Schema::create('imaging_workflow_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_study_workflow_execution_id')
                ->constrained('imaging_study_workflow_executions', 'id', 'iwc_execution_fk')
                ->cascadeOnDelete();
            $table->foreignId('imaging_protocol_workflow_step_id')
                ->constrained('imaging_protocol_workflow_steps', 'id', 'iwc_step_fk');
            $table->unsignedBigInteger('assigned_user_id')->index();
            $table->timestamp('claimed_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['imaging_study_workflow_execution_id', 'released_at'], 'iwc_execution_released_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_workflow_claims');
    }
};
