<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De-duplication ledger for events the Clinical Module pushes to us
 * (API Integration Guide §12).
 *
 * Delivery is at-least-once and a failed delivery retries with exponential
 * backoff, so the same event_id *will* arrive more than once — the guide is
 * explicit that the receiver must be idempotent on it. Without this table an
 * INFANT_REGISTRATION_REQUESTED retry registers the same newborn twice.
 *
 * Lives in the ordinary migrations directory, not database/migrations/clinical:
 * this is Main's own table, recording what Main has already processed. It must
 * survive the Clinical module moving to its own database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_inbound_events', function (Blueprint $table) {
            $table->id();

            // The uuid Clinical generates per event. Unique because that
            // constraint *is* the de-duplication — an insert that violates it
            // is a redelivery, and letting the database arbitrate avoids the
            // check-then-act race two concurrent deliveries would otherwise
            // lose.
            $table->uuid('event_id')->unique();

            $table->string('fact_token')->index();
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->string('global_client_id')->nullable()->index();
            $table->string('visit_id')->nullable();

            $table->json('payload');

            $table->string('status')->default('RECEIVED');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_inbound_events');
    }
};
