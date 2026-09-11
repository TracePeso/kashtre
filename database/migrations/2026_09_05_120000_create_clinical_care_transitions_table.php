<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local index only — Care Transitions (v6.1 Volume 8, API_GUIDE_V6.1 §1) has
 * no endpoint to list a patient's transitions and no recheck endpoint, so
 * without this Main has no memory of what it started the moment the page is
 * reloaded. Every field here mirrors what Clinical's own record already
 * carries; this table is never the source of truth for status — `show()`
 * always re-reads that from Clinical and updates the mirror.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarded for the same reason as clinical_floor_stock_reviews above —
        // 2026_08_05_100000's clinical_inbound_events collision confirmed
        // divergent deploy histories re-creating the same table is a real
        // failure mode here, not a hypothetical one.
        if (Schema::hasTable('clinical_care_transitions')) {
            return;
        }

        Schema::create('clinical_care_transitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('client_id');
            $table->string('visit_id')->nullable();
            $table->string('public_id')->unique();
            $table->string('transition_type');
            $table->string('status');
            $table->timestamp('planned_at')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('record_version')->default(1);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_care_transitions');
    }
};
