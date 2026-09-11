<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imaging_reports', function (Blueprint $table) {
            // Additive alongside the existing is_critical_finding boolean —
            // that flag and every consumer of it are unchanged; this just
            // names *which* dictionary condition was flagged, when one was.
            $table->string('critical_finding_code')->nullable()->after('is_critical_finding');
        });
    }

    public function down(): void
    {
        Schema::table('imaging_reports', function (Blueprint $table) {
            $table->dropColumn('critical_finding_code');
        });
    }
};
