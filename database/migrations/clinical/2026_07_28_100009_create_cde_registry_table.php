<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        Schema::connection('clinical')->create('cde_registry', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable()->index(); // null = system-wide default CDE
            $table->string('cde_code', 64); // e.g. 'TEMP_AXILLARY', 'GLUCOSE_RANDOM'
            $table->string('cde_name', 128);
            $table->enum('data_type', ['NUMERIC', 'BOOLEAN', 'TEXT', 'CODE', 'MULTI_COMPONENT']);
            $table->unsignedBigInteger('base_uom_id')->nullable();
            $table->decimal('normal_range_min', 12, 4)->nullable();
            $table->decimal('normal_range_max', 12, 4)->nullable();
            $table->decimal('critical_high', 12, 4)->nullable();
            $table->decimal('critical_low', 12, 4)->nullable();
            // Physiological sanity bounds driving the Input Safety Shield —
            // generic per-CDE, not hardcoded per drug/observation in code.
            $table->decimal('physiological_min', 12, 4)->nullable();
            $table->decimal('physiological_max', 12, 4)->nullable();
            $table->boolean('is_graphable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'cde_code'], 'uid_tenant_cde');
            $table->foreign('base_uom_id')->references('id')->on('clinical_uom_master');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('cde_registry');
    }
};
