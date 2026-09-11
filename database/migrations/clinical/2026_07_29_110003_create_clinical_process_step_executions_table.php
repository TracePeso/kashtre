<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        // The immutable audit trail SRD §4.3 calls for — one row per step
        // actually completed or skipped, insert-only (no update/delete
        // path in the engine).
        Schema::connection('clinical')->create('clinical_process_step_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')->constrained('clinical_process_executions')->cascadeOnDelete();
            $table->foreignId('step_id')->constrained('clinical_process_steps');
            $table->enum('status', ['COMPLETED', 'SKIPPED']);
            $table->unsignedBigInteger('completed_by_user_id');
            $table->timestamp('completed_at')->useCurrent();
            $table->text('override_reason')->nullable(); // required when skipping a mandatory step
            $table->text('notes')->nullable();

            $table->unique(['execution_id', 'step_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_process_step_executions');
    }
};
