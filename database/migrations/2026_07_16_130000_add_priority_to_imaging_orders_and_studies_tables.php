<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pillar 14: Radiologist Worklists — "Urgent Cases" needs a real
        // field to query on. Same low/normal/high/urgent vocabulary as
        // ServiceQueue elsewhere in this app.
        Schema::table('imaging_orders', function (Blueprint $table) {
            $table->string('priority')->default('normal')->after('clinical_indication');
        });

        Schema::table('imaging_studies', function (Blueprint $table) {
            $table->string('priority')->default('normal')->after('modality_type');
        });
    }

    public function down(): void
    {
        Schema::table('imaging_orders', function (Blueprint $table) {
            $table->dropColumn('priority');
        });

        Schema::table('imaging_studies', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
