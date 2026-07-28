<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-managed dictionary of named critical-finding conditions
        // (Intracranial Bleed, Pneumothorax, ...) — same master-list shape
        // as imaging_readiness_check_types.
        Schema::create('imaging_critical_finding_types', function (Blueprint $table) {
            $table->id();
            // Nullable business_id = system-wide finding type available to every business.
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->string('code')->unique();
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_critical_finding_types');
    }
};
