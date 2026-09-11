<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        Schema::connection('clinical')->create('clinical_scoring_dictionaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->string('score_code', 64); // 'NEWS2', 'SATS', 'APGAR', 'GCS', 'EGFR_CKD_EPI'
            $table->string('score_name', 128);
            $table->json('matrix_payload'); // range rules, weights, coefficients
            $table->string('version', 16)->default('v1.0');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'score_code', 'version'], 'uid_score_code');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_scoring_dictionaries');
    }
};
