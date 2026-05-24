<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('emergency_alerts') && ! Schema::hasColumn('emergency_alerts', 'activated_at')) {
            Schema::table('emergency_alerts', function (Blueprint $table) {
                $table->timestamp('activated_at')->nullable()->after('triggered_at');
            });

            DB::table('emergency_alerts')
                ->whereNull('activated_at')
                ->update([
                    'activated_at' => DB::raw('triggered_at'),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('emergency_alerts') && Schema::hasColumn('emergency_alerts', 'activated_at')) {
            Schema::table('emergency_alerts', function (Blueprint $table) {
                $table->dropColumn('activated_at');
            });
        }
    }
};
