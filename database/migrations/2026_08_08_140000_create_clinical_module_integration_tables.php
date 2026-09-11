<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kashtre_clinical_module_settings')) {
            Schema::create('kashtre_clinical_module_settings', function (Blueprint $table) {
                $table->id();
                $table->string('url')->nullable();
                $table->text('service_key')->nullable(); // key Clinical issues to Main (outbound)
                $table->text('inbound_api_key')->nullable(); // key Main expects on inbound Clinical APIs
                $table->boolean('encounter_webhook_enabled')->default(true);
                $table->timestamps();
            });
        }

        // clinical_inbound_events is created by
        // 2026_08_05_100000_create_clinical_inbound_events_table — the
        // de-duplication ledger. All this migration needs is the `response`
        // column the integration service replays on a redelivery.
        if (! Schema::hasTable('clinical_inbound_events')) {
            Schema::create('clinical_inbound_events', function (Blueprint $table) {
                $table->id();
                $table->string('event_id')->unique();
                $table->string('fact_token')->nullable();
                $table->unsignedBigInteger('business_id')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('clinical_inbound_events', 'response')) {
            Schema::table('clinical_inbound_events', function (Blueprint $table) {
                $table->json('response')->nullable()->after('payload');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clinical_inbound_events', 'response')) {
            Schema::table('clinical_inbound_events', function (Blueprint $table) {
                $table->dropColumn('response');
            });
        }

        Schema::dropIfExists('kashtre_clinical_module_settings');
    }
};
