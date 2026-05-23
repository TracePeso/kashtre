<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_biometric_verifications', function (Blueprint $table) {
            $table->string('source_event_id', 191)->nullable()->after('device_id');
            $table->string('event_type', 80)->nullable()->after('source_event_id');

            $table->unique(
                ['organization_id', 'provider', 'device_id', 'source_event_id'],
                'hr_bio_verifications_source_event_unique'
            );
            $table->index(['organization_id', 'provider', 'event_type'], 'hr_bio_verifications_provider_event_index');
        });
    }

    public function down(): void
    {
        Schema::table('hr_biometric_verifications', function (Blueprint $table) {
            $table->dropUnique('hr_bio_verifications_source_event_unique');
            $table->dropIndex('hr_bio_verifications_provider_event_index');
            $table->dropColumn(['source_event_id', 'event_type']);
        });
    }
};
