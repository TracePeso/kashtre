<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        Schema::connection('clinical')->create('clinical_escalation_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->enum('severity_tier', ['INFO', 'WARNING', 'URGENT_REVIEW', 'CRITICAL_PANIC']);
            $table->char('color_hex', 7); // e.g. '#DC2626'
            $table->string('auditory_signal', 128)->nullable();
            $table->enum('screen_action', ['TOAST', 'HEADER_ALERT', 'MODAL_POPUP', 'SCREEN_LOCK']);
            $table->json('target_roles'); // e.g. ["WARD_NURSE", "DUTY_RESIDENT"]
            $table->timestamps();

            $table->unique(['business_id', 'severity_tier'], 'uid_tenant_severity');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_escalation_rules');
    }
};
