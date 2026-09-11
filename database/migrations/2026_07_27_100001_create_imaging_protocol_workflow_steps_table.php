<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The ordered composition of a protocol workflow — real FKs to
        // imaging_protocol_workflows and imaging_workflow_steps (both
        // within Imaging). ris_status is a plain string, not an FK/enum,
        // since it's meant to hold this app's existing ImagingStudy status
        // vocabulary (ORDER_RECEIVED, PREPARATION_REQUIRED, ...) — kept
        // free-text so a facility can map a step to a status this app adds
        // later without a migration. main_status stays the fixed 3-value
        // ENUM the spec defines, since that's a deliberately coarse,
        // stable bucket (see the class-level docblock on ProtocolWorkflowStep
        // for the default mapping rationale).
        Schema::create('imaging_protocol_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_protocol_workflow_id')
                ->constrained('imaging_protocol_workflows', 'id', 'ipws_workflow_fk')
                ->cascadeOnDelete();
            $table->foreignId('imaging_workflow_step_id')
                ->constrained('imaging_workflow_steps', 'id', 'ipws_step_fk')
                ->cascadeOnDelete();
            $table->unsignedInteger('sequence_no');
            $table->string('ris_status');
            $table->enum('main_status', ['PENDING', 'IN_PROGRESS', 'COMPLETED']);
            $table->boolean('triggers_consumption')->default(false);
            $table->timestamps();

            $table->unique(['imaging_protocol_workflow_id', 'sequence_no'], 'imaging_protocol_workflow_steps_workflow_seq_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_protocol_workflow_steps');
    }
};
