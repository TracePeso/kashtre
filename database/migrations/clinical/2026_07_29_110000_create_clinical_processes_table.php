<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        // SRD §4.3 clinical_process_registry, renamed to match this app's
        // model-naming convention (ClinicalProcess, not ClinicalProcessRegistry).
        Schema::connection('clinical')->create('clinical_processes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable()->index(); // null = system-wide default catalog
            $table->string('process_code', 64); // 'ADMISSION', 'TRANSFER', 'DISCHARGE', 'REFERRAL', 'DEATH_CERT'
            $table->string('process_name', 128);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'process_code']);
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_processes');
    }
};
