<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // History: one row per step actually completed for a study. Column
        // names spelled out precisely (the spec's own sample used
        // "study_execution_id"/"workflow_step_id", ambiguous given there
        // are two different "execution"/"step" tables in this schema).
        // room_id and executed_by stay plain indexed columns, not FKs —
        // same cross-domain decoupling rule as every other Main Module
        // User/Room reference in this module.
        Schema::create('imaging_workflow_step_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_study_workflow_execution_id')
                ->constrained('imaging_study_workflow_executions', 'id', 'iwse_execution_fk')
                ->cascadeOnDelete();
            $table->foreignId('imaging_protocol_workflow_step_id')
                ->constrained('imaging_protocol_workflow_steps', 'id', 'iwse_step_fk');
            $table->unsignedBigInteger('executed_by')->nullable()->index();
            $table->unsignedBigInteger('room_id')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_workflow_step_executions');
    }
};
