<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'presentation_timezone')) {
                $table->string('presentation_timezone', 64)->nullable()->after('email');
            }
        });

        Schema::create('core_time_tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_key', 64)->unique();
            $table->unsignedInteger('day_rollover_offset_minutes')->default(0);
            $table->boolean('enforce_financial_periods')->default(false);
            $table->boolean('allow_user_presentation_timezone')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_time_tenant_settings');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'presentation_timezone')) {
                $table->dropColumn('presentation_timezone');
            }
        });
    }
};
