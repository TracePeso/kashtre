<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        // Engineering doc's `tenant_ddi_dictionary`, renamed to match this
        // app's business_id convention. Rows are checked bidirectionally
        // in code (A-B or B-A) rather than duplicated in the seed data.
        Schema::connection('clinical')->create('clinical_ddi_dictionary', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable()->index(); // null = system-wide default
            $table->string('drug_a_code', 64); // Item::code
            $table->string('drug_b_code', 64);
            $table->enum('severity', ['INFO', 'WARNING', 'HARD_BLOCK']);
            $table->string('description');
            $table->timestamps();

            $table->unique(['business_id', 'drug_a_code', 'drug_b_code'], 'uid_ddi_pair');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_ddi_dictionary');
    }
};
