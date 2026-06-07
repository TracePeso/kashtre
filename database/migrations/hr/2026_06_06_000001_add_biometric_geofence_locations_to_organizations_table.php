<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organizations')) {
            return;
        }

        Schema::table('organizations', function (Blueprint $table): void {
            if (! Schema::hasColumn('organizations', 'biometric_geofence_locations')) {
                $table->json('biometric_geofence_locations')
                    ->nullable()
                    ->after('biometric_geofence_max_accuracy_meters');
            }
        });

        DB::table('organizations')
            ->select([
                'id',
                'biometric_geofence_latitude',
                'biometric_geofence_longitude',
                'biometric_geofence_radius_meters',
                'biometric_geofence_max_accuracy_meters',
            ])
            ->orderBy('id')
            ->get()
            ->each(function (object $organization): void {
                if ($organization->biometric_geofence_latitude === null || $organization->biometric_geofence_longitude === null) {
                    return;
                }

                DB::table('organizations')
                    ->where('id', $organization->id)
                    ->update([
                        'biometric_geofence_locations' => json_encode([[
                            'name' => 'Primary office',
                            'latitude' => (float) $organization->biometric_geofence_latitude,
                            'longitude' => (float) $organization->biometric_geofence_longitude,
                            'radius_meters' => max(25, (int) ($organization->biometric_geofence_radius_meters ?: 100)),
                            'max_accuracy_meters' => max(5, (int) ($organization->biometric_geofence_max_accuracy_meters ?: 150)),
                        ]]),
                    ]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('organizations')) {
            return;
        }

        Schema::table('organizations', function (Blueprint $table): void {
            if (Schema::hasColumn('organizations', 'biometric_geofence_locations')) {
                $table->dropColumn('biometric_geofence_locations');
            }
        });
    }
};
