<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The modalities the previous hardcoded ImagingStudy::IONIZING_MODALITIES
     * const flagged as ionizing — backfilled here so existing protocols keep
     * showing the study page's Radiation Exposure card exactly as before,
     * now driven by this explicit per-protocol toggle instead of a modality
     * name match.
     */
    private const PREVIOUSLY_IONIZING_MODALITIES = ['XRAY', 'CT', 'MG', 'FLUORO'];

    public function up(): void
    {
        Schema::table('imaging_protocols', function (Blueprint $table) {
            $table->boolean('involves_ionizing_radiation')->default(false)->after('modality_type');
        });

        DB::table('imaging_protocols')
            ->whereIn('modality_type', self::PREVIOUSLY_IONIZING_MODALITIES)
            ->update(['involves_ionizing_radiation' => true]);
    }

    public function down(): void
    {
        Schema::table('imaging_protocols', function (Blueprint $table) {
            $table->dropColumn('involves_ionizing_radiation');
        });
    }
};
