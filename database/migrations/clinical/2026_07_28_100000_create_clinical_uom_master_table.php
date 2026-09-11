<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        Schema::connection('clinical')->create('clinical_uom_master', function (Blueprint $table) {
            $table->id();
            // Null business_id = system-wide pre-seeded default, visible to
            // every business. A specific business_id row overrides/extends
            // the default set for that business only (Settings UI, later
            // chunk). Note: MySQL unique indexes treat each NULL as
            // distinct, so uniqueness of the global defaults is enforced by
            // the seeder's updateOrInsert, not this index alone.
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->string('unit_label', 32); // e.g. 'mmol/L', 'mg/dL', '°C'
            $table->string('ucum_code', 32)->nullable();
            $table->string('category', 64); // e.g. 'Volumetric Concentration'
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'unit_label']);
            $table->index(['business_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_uom_master');
    }
};
