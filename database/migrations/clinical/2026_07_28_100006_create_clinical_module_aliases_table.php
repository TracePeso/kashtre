<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        // SRD's `tenant_module_aliases`, renamed since this app has no
        // generic "tenant" concept — just business_id. Per-business
        // display-name override + enable/disable, mirroring the existing
        // InventoryModuleConfig/CallingModuleConfig is_active convention.
        Schema::connection('clinical')->create('clinical_module_aliases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('module_code', 64)->default('CLINICAL_ORCHESTRATOR');
            $table->string('display_name', 128)->default('Clinical Module');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'module_code'], 'uid_tenant_module');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_module_aliases');
    }
};
