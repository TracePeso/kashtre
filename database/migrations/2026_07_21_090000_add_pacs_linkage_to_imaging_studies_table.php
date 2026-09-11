<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imaging_studies', function (Blueprint $table) {
            // Plain nullable columns, no FK — these are PACS-side
            // identifiers, not Main Module references, matching this
            // table's existing style.
            $table->string('study_instance_uid', 64)->nullable()->unique()->after('accession_number');
            $table->string('orthanc_study_id', 64)->nullable()->index()->after('study_instance_uid');
            $table->string('orthanc_worklist_id', 64)->nullable()->index()->after('orthanc_study_id');
        });
    }

    public function down(): void
    {
        Schema::table('imaging_studies', function (Blueprint $table) {
            $table->dropColumn(['study_instance_uid', 'orthanc_study_id', 'orthanc_worklist_id']);
        });
    }
};
