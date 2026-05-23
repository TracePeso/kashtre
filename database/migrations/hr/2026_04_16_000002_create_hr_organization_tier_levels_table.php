<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_organization_tier_levels', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('level_order');
            $table->timestamps();

            $table->unique(['organization_id', 'level_order']);
        });

        Schema::table('hr_organizational_units', function (Blueprint $table) {
            $table->foreignId('tier_level_id')
                ->nullable()
                ->after('parent_id')
                ->constrained('hr_organization_tier_levels')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_organizational_units', function (Blueprint $table) {
            $table->dropForeign(['tier_level_id']);
            $table->dropColumn('tier_level_id');
        });

        Schema::dropIfExists('hr_organization_tier_levels');
    }
};
