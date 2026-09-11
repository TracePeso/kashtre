<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        Schema::connection('clinical')->create('clinical_uom_conversions_master', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->string('cde_code', 64); // e.g. 'GLUCOSE_RANDOM'
            $table->unsignedBigInteger('from_unit_id');
            $table->unsignedBigInteger('to_unit_id');
            $table->enum('conversion_type', ['MULTIPLIER', 'DIVISOR', 'POLYNOMIAL'])->default('MULTIPLIER');
            $table->decimal('factor', 16, 8)->default(1);
            // Only consulted for conversion_type=POLYNOMIAL. Not a generic
            // expression evaluator (no eval()) — CdeExecutionEngine matches
            // this against a small known set of named formulas (currently
            // 'C_TO_F' / 'F_TO_C'). Extend that match arm before adding a
            // new POLYNOMIAL row here.
            $table->string('formula_expression')->nullable();
            $table->unsignedTinyInteger('decimal_precision')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'cde_code', 'from_unit_id', 'to_unit_id'], 'uid_conv_rule');
            $table->foreign('from_unit_id')->references('id')->on('clinical_uom_master');
            $table->foreign('to_unit_id')->references('id')->on('clinical_uom_master');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_uom_conversions_master');
    }
};
