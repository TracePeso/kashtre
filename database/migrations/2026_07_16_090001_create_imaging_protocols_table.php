<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imaging_protocols', function (Blueprint $table) {
            $table->id();
            // Nullable business_id = system-wide protocol available to every business.
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('modality_type')->index(); // XRAY, CT, MRI, US, MG, FLUORO
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_consent')->default(false);
            $table->json('preparation_requirements')->nullable(); // fasting, bowel prep, contrast screening
            $table->json('readiness_checks')->nullable(); // full bladder, creatinine, MRI safety checklist
            $table->json('reporting_template')->nullable(); // structured report sections
            $table->json('consumables_recipe')->nullable(); // [{item_id, sku, qty}]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_protocols');
    }
};
