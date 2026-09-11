<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reusable master list backing imaging_protocols.preparation_requirements
        // and .readiness_checks (still JSON arrays of these codes) — an admin
        // picks from this list instead of typing free tags per protocol.
        Schema::create('imaging_readiness_check_types', function (Blueprint $table) {
            $table->id();
            // Nullable business_id = system-wide check type available to every business.
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->string('code')->unique();
            $table->string('label');
            $table->string('category')->index(); // PREPARATION or READINESS
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_readiness_check_types');
    }
};
