<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pillar 5.1: Peer Review & Randomized Double-Reading. A blind QA
        // worklist entry cloned off a report the moment it's verified,
        // rolled at the business's configured peer_review_rate.
        Schema::create('peer_review_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_study_id')->constrained('imaging_studies')->cascadeOnDelete();
            $table->foreignId('imaging_report_id')->constrained('imaging_reports')->cascadeOnDelete();
            $table->unsignedBigInteger('original_author_user_id')->index();
            $table->unsignedBigInteger('reviewer_user_id')->nullable()->index();
            $table->string('qa_status')->default('AWAITING_BLIND_READ')->index();
            $table->json('reviewer_payload')->nullable(); // reviewer's own independent findings
            $table->string('variation_score')->nullable(); // CONCORDANT, MINOR_DISCORDANCE, MAJOR_DISCORDANCE
            $table->text('reviewer_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peer_review_cases');
    }
};
