<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kashtre_clinical_module_settings', function (Blueprint $table) {
            $table->id();
            $table->string('url')->nullable();
            $table->text('service_key')->nullable(); // key Clinical issues to Main (outbound)
            $table->text('inbound_api_key')->nullable(); // key Main expects on inbound Clinical APIs
            $table->boolean('encounter_webhook_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('clinical_inbound_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('fact_token')->nullable();
            $table->unsignedBigInteger('business_id')->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_inbound_events');
        Schema::dropIfExists('kashtre_clinical_module_settings');
    }
};
