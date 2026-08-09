<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imaging_studies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('accession_number')->unique(); // Master tracking key

            // Cross-domain references are plain indexed columns, not FK constraints,
            // so Imaging's workflow tables stay logically decoupled from the Main
            // Module ledger (per the spec's "Zero Direct Main Module Links" rule).
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('client_id')->index(); // Client::client_id
            $table->string('visit_id')->nullable()->index(); // Client::visit_id
            $table->unsignedBigInteger('main_service_point_id')->nullable()->index();
            $table->unsignedBigInteger('performed_by_user_id')->nullable()->index();

            // Imaging-internal reference — real FK is fine here.
            $table->foreignId('imaging_order_id')->nullable()->constrained('imaging_orders')->nullOnDelete();

            $table->string('protocol_code')->index();
            $table->string('modality_type'); // XRAY, CT, MRI, US, MG, FLUORO

            $table->string('status')->default('ORDER_RECEIVED')->index();
            $table->boolean('preparation_required')->default(false);
            $table->boolean('preparation_complete')->default(false);
            $table->json('readiness_check_results')->nullable();
            $table->boolean('consent_verified')->default(false);

            // Pillar 10: Imaging Procedure Record — timestamped, user-attributed trail.
            $table->json('status_history')->nullable();
            $table->timestamp('ready_for_study_at')->nullable();
            $table->timestamp('image_acquired_at')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_studies');
    }
};
