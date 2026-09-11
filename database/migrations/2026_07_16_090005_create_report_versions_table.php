<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable, append-only version history. Rows here are never updated —
        // every report change writes a new snapshot instead.
        Schema::create('report_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_report_id')->constrained('imaging_reports')->cascadeOnDelete();
            $table->unsignedBigInteger('modifier_user_id')->index();
            $table->string('status'); // report status at the moment of this snapshot
            $table->json('historical_payload_snapshot'); // un-overwritten raw report copy
            $table->text('amendment_justification_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_versions');
    }
};
