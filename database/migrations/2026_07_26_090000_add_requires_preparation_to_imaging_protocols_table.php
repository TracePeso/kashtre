<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Default true preserves today's exact behavior for every existing
        // protocol — nothing changes until an admin explicitly opts a
        // protocol out of the Preparation phase (see
        // ImagingStudy::canMarkReadyForStudy()/markReadyForStudy()).
        Schema::table('imaging_protocols', function (Blueprint $table) {
            $table->boolean('requires_preparation')->default(true)->after('involves_ionizing_radiation');
        });
    }

    public function down(): void
    {
        Schema::table('imaging_protocols', function (Blueprint $table) {
            $table->dropColumn('requires_preparation');
        });
    }
};
