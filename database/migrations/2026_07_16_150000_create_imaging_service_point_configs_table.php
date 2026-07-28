<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pillar 1.1: maps a Main Module ServicePoint (hardware room) to its
        // DICOM AE Title, without adding an Imaging-specific column to the
        // shared ServicePoint table — same cross-domain decoupling rule as
        // the rest of this module.
        Schema::create('imaging_service_point_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('main_service_point_id')->index();
            $table->string('hardware_ae_title')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'main_service_point_id'], 'imaging_sp_configs_business_sp_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_service_point_configs');
    }
};
