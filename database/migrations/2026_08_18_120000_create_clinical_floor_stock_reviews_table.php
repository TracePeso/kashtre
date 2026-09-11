<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visibility only — a pharmacy/ward staff checklist for floor-stock usage
 * a clinician recorded from the Clinical chart panel, so it does not vanish
 * unbilled just because nobody happened to know it existed. It never writes
 * to inventory_usage_events, invoices, or any stock table: billing itself
 * still only happens through the existing Record Usage screen
 * (InventoryRecordUsageService), by a human, on this business's own terms —
 * ticking "Reviewed" here means only "a staff member has seen this line and
 * dealt with it," not that anything was billed automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarded like every other table-creating migration touching Clinical
        // integration in this stretch — 2026_08_05_100000's own conflict with
        // 2026_08_08_140000 (two migrations independently creating
        // clinical_inbound_events on divergent deploy histories) confirmed
        // this exact failure mode is real, not theoretical, for this codebase.
        if (Schema::hasTable('clinical_floor_stock_reviews')) {
            return;
        }

        Schema::create('clinical_floor_stock_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            // Clinical's own outbox event_id — unique per business so a
            // redelivered fact does not create a second checklist line.
            $table->string('clinical_event_id');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'clinical_event_id'], 'cfsr_business_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_floor_stock_reviews');
    }
};
