<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('emergency_alerts') && ! Schema::hasColumn('emergency_alerts', 'button_name')) {
            Schema::table('emergency_alerts', function (Blueprint $table) {
                $table->string('button_name')->nullable()->after('room_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('emergency_alerts') && Schema::hasColumn('emergency_alerts', 'button_name')) {
            Schema::table('emergency_alerts', function (Blueprint $table) {
                $table->dropColumn('button_name');
            });
        }
    }
};
