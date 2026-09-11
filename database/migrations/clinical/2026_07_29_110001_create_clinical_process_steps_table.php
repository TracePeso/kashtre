<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        Schema::connection('clinical')->create('clinical_process_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained('clinical_processes')->cascadeOnDelete(); // same connection
            $table->unsignedTinyInteger('step_order');
            $table->string('step_code', 64);
            $table->string('step_name', 128);
            $table->boolean('is_mandatory')->default(true);
            $table->string('required_role', 64)->nullable();
            // Fixed, engine-known behaviours a step can trigger on
            // completion (bed allocation/release for now) — a config flag
            // per Imaging's own `triggers_consumption` precedent, not a
            // hardcoded step_code check in the engine.
            $table->string('side_effect', 32)->nullable(); // 'ALLOCATE_BED', 'RELEASE_BED'
            $table->timestamps();

            $table->unique(['process_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_process_steps');
    }
};
