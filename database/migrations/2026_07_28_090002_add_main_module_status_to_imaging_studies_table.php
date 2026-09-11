<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RIS Amendment v2.6, Chunk 3: the coarse PENDING/IN_PROGRESS/COMPLETED
        // bucket WorkflowStatusMapperService computes from the current
        // workflow step's main_status. Deliberately not pushed anywhere
        // yet — no real Main Module record to sync to exists today (see
        // the roadmap's grounding note); this column holds the computed
        // value, ready to sync once a real integration target is confirmed.
        Schema::table('imaging_studies', function (Blueprint $table) {
            $table->string('main_module_status')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('imaging_studies', function (Blueprint $table) {
            $table->dropColumn('main_module_status');
        });
    }
};
