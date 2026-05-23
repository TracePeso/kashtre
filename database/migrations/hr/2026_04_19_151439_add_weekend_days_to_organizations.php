<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->json('weekend_days')->nullable()->after('name');
        });

        // Default all existing organizations to Saturday (6) + Sunday (0).
        DB::table('organizations')->update(['weekend_days' => json_encode([0, 6])]);
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('weekend_days');
        });
    }
};
