<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pa_sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('business_id')->index();
            $table->timestamps();
        });

        Schema::create('pa_section_callers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pa_section_id');
            $table->unsignedBigInteger('caller_id');
            $table->unique(['pa_section_id', 'caller_id']);
            $table->foreign('pa_section_id')->references('id')->on('pa_sections')->onDelete('cascade');
            $table->foreign('caller_id')->references('id')->on('callers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pa_section_callers');
        Schema::dropIfExists('pa_sections');
    }
};
