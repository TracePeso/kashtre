<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('biometric_geofence_enabled')->default(false)->after('biometric_allowed_networks');
            $table->decimal('biometric_geofence_latitude', 10, 7)->nullable()->after('biometric_geofence_enabled');
            $table->decimal('biometric_geofence_longitude', 10, 7)->nullable()->after('biometric_geofence_latitude');
            $table->unsignedInteger('biometric_geofence_radius_meters')->default(100)->after('biometric_geofence_longitude');
            $table->unsignedInteger('biometric_geofence_max_accuracy_meters')->default(150)->after('biometric_geofence_radius_meters');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'biometric_geofence_enabled',
                'biometric_geofence_latitude',
                'biometric_geofence_longitude',
                'biometric_geofence_radius_meters',
                'biometric_geofence_max_accuracy_meters',
            ]);
        });
    }
};
