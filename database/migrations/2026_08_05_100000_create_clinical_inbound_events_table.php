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
        // On a genuinely fresh database this migration is what creates the
        // table, with the full schema below. But on any environment where
        // 2026_08_08_140000_create_clinical_module_integration_tables ran
        // first — which is this migration's own timestamp order inverted,
        // and exactly what happened wherever that migration's `if (!
        // Schema::hasTable(...))` fallback already fired — the table exists
        // already, with only its narrower fallback schema (id, event_id as
        // string, fact_token, business_id, payload, response, timestamps).
        // Reconcile instead of failing: add whatever that fallback didn't
        // create, rather than assuming this file is always the first to run.
        if (! Schema::hasTable('clinical_inbound_events')) {
            Schema::create('clinical_inbound_events', function (Blueprint $table) {
                $table->id();

                // The uuid Clinical generates per event. Unique because that
                // constraint *is* the de-duplication — an insert that
                // violates it is a redelivery, and letting the database
                // arbitrate avoids the check-then-act race two concurrent
                // deliveries would otherwise lose.
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

            return;
        }

        Schema::table('clinical_inbound_events', function (Blueprint $table) {
            // ClinicalInboundEvent's own $fillable/$casts already reference
            // every one of these — on the narrower fallback schema, writing
            // any of them silently drops the value (mass-assignment) or
            // throws (a cast on a column that doesn't exist), not something
            // that shows up until the first real inbound event tries it.
            if (! Schema::hasColumn('clinical_inbound_events', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('event_id');
            }
            if (! Schema::hasColumn('clinical_inbound_events', 'global_client_id')) {
                $table->string('global_client_id')->nullable()->index()->after('business_id');
            }
            if (! Schema::hasColumn('clinical_inbound_events', 'visit_id')) {
                $table->string('visit_id')->nullable()->after('global_client_id');
            }
            if (! Schema::hasColumn('clinical_inbound_events', 'status')) {
                $table->string('status')->default('RECEIVED')->after('payload');
            }
            if (! Schema::hasColumn('clinical_inbound_events', 'error_message')) {
                $table->text('error_message')->nullable()->after('status');
            }
            if (! Schema::hasColumn('clinical_inbound_events', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('error_message');
            }
        });
    }

    public function down(): void
    {
        // The reconciling branch above only ever adds columns to a table
        // this migration did not create — down() only drops the table it
        // actually owns creating.
        if (Schema::hasTable('clinical_inbound_events') && ! Schema::hasColumn('clinical_inbound_events', 'response')) {
            Schema::dropIfExists('clinical_inbound_events');

            return;
        }

        foreach (['processed_at', 'error_message', 'status', 'visit_id', 'global_client_id', 'tenant_id'] as $column) {
            if (Schema::hasColumn('clinical_inbound_events', $column)) {
                Schema::table('clinical_inbound_events', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
