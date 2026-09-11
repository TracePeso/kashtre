<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RIS Amendment v2.6, Chunk 2: a versioned workflow definition for
        // one protocol. Real FK to imaging_protocols (both sides are within
        // Imaging, per this module's own convention) — the spec's own
        // sample used a loose protocol_code string, corrected here.
        Schema::create('imaging_protocol_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_protocol_id')
                ->constrained('imaging_protocols')
                ->cascadeOnDelete();
            $table->string('workflow_name');
            $table->unsignedInteger('workflow_version');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // A protocol can have historical versions, but only one active
            // workflow at a time (StartWorkflow always resolves "the"
            // active one — Chunk 3).
            $table->unique(['imaging_protocol_id', 'workflow_version'], 'imaging_protocol_workflows_protocol_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_protocol_workflows');
    }
};
