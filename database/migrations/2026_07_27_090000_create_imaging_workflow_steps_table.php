<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RIS Amendment v2.6, Chunk 1: admin-managed dictionary of reusable
        // workflow steps — same master-list shape as imaging_modalities /
        // imaging_readiness_check_types. Nullable business_id = system-wide
        // step available to every business (steps are meant to be shared
        // across protocols/modalities without duplication, per the spec).
        Schema::create('imaging_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->string('step_code')->unique();
            $table->string('step_name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_workflow_steps');
    }
};
