<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RIS Amendment v2.6, Chunk 5: dedicated, queryable audit trail for
        // every time a blocked step-completion was overridden — created by
        // CompletionRuleService::recordOverride(), never edited afterward.
        // user_id is a plain indexed column, not a FK (same cross-domain
        // decoupling rule as every other Main Module User reference here).
        Schema::create('imaging_workflow_step_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_study_workflow_execution_id')
                ->constrained('imaging_study_workflow_executions', 'id', 'iwso_execution_fk')
                ->cascadeOnDelete();
            $table->foreignId('imaging_protocol_workflow_step_id')
                ->constrained('imaging_protocol_workflow_steps', 'id', 'iwso_step_fk');
            $table->unsignedBigInteger('user_id')->index();
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_workflow_step_overrides');
    }
};
