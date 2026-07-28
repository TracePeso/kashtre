<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imaging_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_study_id')->constrained('imaging_studies')->cascadeOnDelete();
            $table->string('pacs_study_uid')->nullable(); // link to binary PACS archive
            $table->unsignedBigInteger('author_user_id')->index(); // Radiologist Staff ID
            $table->unsignedBigInteger('verified_by_user_id')->nullable()->index();
            $table->string('status')->default('DRAFT')->index(); // DRAFT, REPORTED, VERIFIED, AMENDED
            $table->boolean('is_critical_finding')->default(false);
            $table->json('structured_data_payload'); // Template sections (e.g. Lungs, Pleura, Heart, Impression)
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_reports');
    }
};
