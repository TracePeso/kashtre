<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('biometric_network_restriction_enabled')->default(false)->after('weekend_days');
            $table->text('biometric_allowed_networks')->nullable()->after('biometric_network_restriction_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['biometric_network_restriction_enabled', 'biometric_allowed_networks']);
        });
    }
};
