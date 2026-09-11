<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pillar 16: Recovery & Post-Procedure Tracking — Procedure -> Recovery
        // -> Discharge for sedation/interventional protocols. One record per
        // study (a single continuous recovery episode).
        Schema::create('recovery_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_study_id')->constrained('imaging_studies')->cascadeOnDelete();
            $table->timestamp('monitoring_started_at')->nullable();
            $table->text('vital_signs_notes')->nullable();
            $table->boolean('discharge_criteria_met')->default(false);
            $table->timestamp('discharge_cleared_at')->nullable();
            $table->unsignedBigInteger('discharge_cleared_by_user_id')->nullable()->index();
            $table->text('discharge_notes')->nullable();
            $table->timestamps();

            $table->unique('imaging_study_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recovery_records');
    }
};
