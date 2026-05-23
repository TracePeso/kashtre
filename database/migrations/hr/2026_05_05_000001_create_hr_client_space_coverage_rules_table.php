<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_client_space_coverage_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('organizational_unit_id')->constrained('hr_organizational_units')->cascadeOnDelete();
            $table->string('cadre_or_discipline');
            $table->foreignId('shift_type_id')->constrained('hr_shift_types')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedSmallInteger('required_headcount');
            $table->boolean('is_active')->default(true);
            $table->string('notes', 1000)->nullable();
            $table->timestamps();

            $table->unique([
                'organizational_unit_id',
                'cadre_or_discipline',
                'shift_type_id',
                'day_of_week',
            ], 'hr_client_space_coverage_rules_unique');
            $table->index(['organization_id', 'organizational_unit_id'], 'hr_client_space_coverage_rules_org_unit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_client_space_coverage_rules');
    }
};
