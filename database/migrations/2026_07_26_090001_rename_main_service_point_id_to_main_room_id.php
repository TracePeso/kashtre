<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // main_service_point_id stored ServicePoint ids (a billing/queueing
        // construct), when what the Imaging config actually needs is Room
        // ids (the physical-location model behind the "Select Your Room"
        // login flow) — ServicePoint.room_id is optional and often unset,
        // so existing rows can't be reliably translated. Per explicit
        // decision: configs get re-entered rather than guessed at.
        DB::table('imaging_service_point_configs')->delete();

        Schema::table('imaging_service_point_configs', function (Blueprint $table) {
            $table->renameColumn('main_service_point_id', 'main_room_id');
        });

        // Historical studies keep their own record intact — just null the
        // stale ServicePoint-id reference. Every consumer already treats a
        // null main_room_id as "not routed to a specific room yet"
        // (resolveHardwareAeTitle() returns null, the study page hides the
        // room breadcrumb segment, RadiologyRecipeEngine falls through to
        // its other store-resolution tiers) — a pre-existing, safe no-op
        // path, not new behavior.
        DB::table('imaging_studies')->update(['main_service_point_id' => null]);

        Schema::table('imaging_studies', function (Blueprint $table) {
            $table->renameColumn('main_service_point_id', 'main_room_id');
        });
    }

    public function down(): void
    {
        Schema::table('imaging_service_point_configs', function (Blueprint $table) {
            $table->renameColumn('main_room_id', 'main_service_point_id');
        });

        Schema::table('imaging_studies', function (Blueprint $table) {
            $table->renameColumn('main_room_id', 'main_service_point_id');
        });
    }
};
