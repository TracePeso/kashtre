<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->text('emergency_display_message')->nullable()->after('default_emergency_message');
        });
    }

    public function down(): void
    {
        Schema::table('calling_module_configs', function (Blueprint $table) {
            $table->dropColumn('emergency_display_message');
        });
    }
};
