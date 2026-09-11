<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-managed dictionary of imaging modalities, replacing the
        // hardcoded XRAY/CT/MRI/US/MG/FLUORO const previously baked into
        // ListImagingProtocols/ListImagingModuleConfigs — a business (or
        // Kashtre) can now add a new modality without a code deploy.
        // dicom_code is the real DICOM Modality (0008,0060) code this app's
        // own modality_type vocabulary maps to for PACS worklist purposes
        // (see the now-removed OrthancDicomWorklistBroker::DICOM_MODALITY_CODES).
        Schema::create('imaging_modalities', function (Blueprint $table) {
            $table->id();
            // Nullable business_id = system-wide modality available to every business.
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('dicom_code')->nullable();
            $table->boolean('is_ionizing')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_modalities');
    }
};
