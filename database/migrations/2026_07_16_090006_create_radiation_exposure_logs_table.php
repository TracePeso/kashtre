<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radiation_exposure_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_study_id')->constrained('imaging_studies')->cascadeOnDelete();
            $table->string('client_id')->index(); // long-term cumulative indexing
            $table->decimal('dose_area_product_gy', 8, 4)->nullable(); // automated DICOM SR ingestion
            $table->integer('exposure_time_ms')->nullable();
            $table->string('kvp_metrics')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radiation_exposure_logs');
    }
};
