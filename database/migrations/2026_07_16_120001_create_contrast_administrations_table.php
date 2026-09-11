<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pillar 12: Contrast Management — chemical metadata for
        // contrast-enhanced studies (agent, volume, injection time,
        // adverse reactions).
        Schema::create('contrast_administrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_study_id')->constrained('imaging_studies')->cascadeOnDelete();
            $table->string('contrast_agent_name');
            $table->decimal('volume_ml', 8, 2);
            $table->timestamp('injection_time')->nullable();
            $table->text('adverse_reactions')->nullable();
            $table->unsignedBigInteger('administered_by_user_id')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrast_administrations');
    }
};
