<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        // Minimal diagnosis record — SRD's DISCHARGE_SUMMARY_ICD11 process
        // step (Chunk 5) references "ICD-11 Finalization" but nothing
        // structured existed to finalize; this is that structure, and
        // also what FHIR's Condition resource (Chunk 9) is built from.
        Schema::connection('clinical')->create('clinical_conditions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('client_id')->index();
            $table->string('visit_id')->nullable();
            $table->string('icd11_code', 32)->nullable(); // plain code, no local ICD-11 catalog table exists yet
            $table->string('description');
            $table->enum('clinical_status', ['ACTIVE', 'RESOLVED', 'INACTIVE'])->default('ACTIVE');
            $table->unsignedBigInteger('recorded_by_user_id');
            $table->timestamp('recorded_at')->useCurrent();

            $table->index(['business_id', 'client_id'], 'idx_condition_patient');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_conditions');
    }
};
