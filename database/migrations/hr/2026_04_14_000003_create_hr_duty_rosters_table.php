<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_duty_rosters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizational_unit_id')->constrained('hr_organizational_units')->cascadeOnDelete();
            $table->string('cadre_or_discipline')->nullable();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('created_by')->nullable(); // staff_uuid
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['organizational_unit_id', 'cadre_or_discipline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_duty_rosters');
    }
};
