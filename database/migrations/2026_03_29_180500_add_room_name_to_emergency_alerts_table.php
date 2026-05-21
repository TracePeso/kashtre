<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('emergency_alerts') && ! Schema::hasColumn('emergency_alerts', 'room_name')) {
            Schema::table('emergency_alerts', function (Blueprint $table) {
                $table->string('room_name')->nullable()->after('service_point_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('emergency_alerts') && Schema::hasColumn('emergency_alerts', 'room_name')) {
            Schema::table('emergency_alerts', function (Blueprint $table) {
                $table->dropColumn('room_name');
            });
        }
    }
};
