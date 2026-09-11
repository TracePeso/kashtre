<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        Schema::connection('clinical')->create('clinical_reason_codes_master', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->string('category_code', 64); // 'SKIPPED_OBS', 'BREAK_GLASS', 'MAR_WASTAGE', ...
            $table->string('reason_code', 64);
            $table->string('display_label');
            $table->boolean('requires_free_text')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'category_code', 'reason_code'], 'uid_tenant_reason');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_reason_codes_master');
    }
};
