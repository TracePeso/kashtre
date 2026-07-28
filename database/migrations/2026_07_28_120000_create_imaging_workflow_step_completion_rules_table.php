<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RIS Amendment v2.6, Chunk 5: what must be true before a study can
        // be moved into imaging_protocol_workflow_step_id — checked by
        // CompletionRuleService::validateStepCompletion(). "legacy_sync"
        // rows are auto-generated/kept in sync from ImagingProtocol's
        // existing preparation_requirements/readiness_checks/requires_consent
        // fields (see ImagingProtocol::syncLegacyCompletionRules()) — the
        // admin form for those fields is unchanged; "manual" rows are for
        // anything configured beyond those three (no admin UI for that yet,
        // added via tinker/seeder until one exists).
        Schema::create('imaging_workflow_step_completion_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_protocol_workflow_step_id')
                ->constrained('imaging_protocol_workflow_steps', 'id', 'iwscr_step_fk')
                ->cascadeOnDelete();
            $table->enum('rule_type', ['FIELD', 'CHECKLIST', 'ATTACHMENT', 'SIGNATURE']);
            $table->string('rule_key');
            $table->boolean('is_required')->default(true);
            $table->boolean('allow_override')->default(false);
            $table->json('authorized_override_permissions')->nullable();
            $table->string('source')->default('manual');
            $table->timestamps();

            $table->index(['imaging_protocol_workflow_step_id', 'rule_type'], 'iwscr_step_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_workflow_step_completion_rules');
    }
};
