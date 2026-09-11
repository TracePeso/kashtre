<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contrast_administrations', function (Blueprint $table) {
            // Optional — the existing free-text flow (contrast_agent_name/
            // volume_ml typed directly) is unaffected when this is null.
            $table->unsignedBigInteger('contrast_vial_id')->nullable()->index()->after('imaging_study_id');
        });
    }

    public function down(): void
    {
        Schema::table('contrast_administrations', function (Blueprint $table) {
            $table->dropColumn('contrast_vial_id');
        });
    }
};
