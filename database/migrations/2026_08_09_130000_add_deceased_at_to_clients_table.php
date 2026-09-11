<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records a patient death so the end-of-life kill-switch (SRD §13.3) can stop
 * recurring billing.
 *
 * Main has no death concept of its own — nothing in the encounter lifecycle
 * knows a patient has died — so the fact arrives from the Clinical Module as a
 * PATIENT_DECEASED event. Until it is recorded somewhere, a deceased patient
 * keeps accruing recurring charges.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('clients', 'deceased_at')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->timestamp('deceased_at')->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('clients', 'deceased_at')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['deceased_at']);
            $table->dropColumn('deceased_at');
        });
    }
};
