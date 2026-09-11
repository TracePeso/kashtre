<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pillar 11: Consent Management. consent_verified (Chunk 0) is the
        // gate; these columns are the audit trail behind it — who obtained
        // it, when, and any notes (e.g. which form/witness).
        Schema::table('imaging_studies', function (Blueprint $table) {
            $table->unsignedBigInteger('consent_verified_by_user_id')->nullable()->index()->after('consent_verified');
            $table->timestamp('consent_verified_at')->nullable()->after('consent_verified_by_user_id');
            $table->text('consent_notes')->nullable()->after('consent_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('imaging_studies', function (Blueprint $table) {
            $table->dropColumn(['consent_verified_by_user_id', 'consent_verified_at', 'consent_notes']);
        });
    }
};
