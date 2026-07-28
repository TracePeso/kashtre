<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RIS Amendment v2.6, Chunk 6: a resolvable record of a consumption
        // attempt that couldn't actually deplete stock — created by
        // RadiologyRecipeEngine when it can't resolve a store OR the
        // resolved store has no stock (today both cases are a silent
        // no-op; the first was already logged, the second wasn't logged at
        // all). imaging_study_id is a real FK (within the Imaging domain,
        // same as every other imaging_studies reference in this module);
        // imaging_protocol_workflow_step_id is nullable — the one
        // remaining legacy trigger call (RecoveryRecord's RECOVERY_COMPLETE
        // case) has no real workflow step to attribute the exception to.
        // resolved_by_user_id is a plain indexed column, not a FK (User is
        // Main Module).
        Schema::create('imaging_consumption_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_study_id')
                ->constrained('imaging_studies')
                ->cascadeOnDelete();
            $table->foreignId('imaging_protocol_workflow_step_id')
                ->nullable()
                ->constrained('imaging_protocol_workflow_steps', 'id', 'ice_step_fk')
                ->nullOnDelete();
            $table->text('exception_reason');
            $table->boolean('resolved')->default(false);
            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['imaging_study_id', 'resolved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_consumption_exceptions');
    }
};
