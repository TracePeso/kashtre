<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imaging_protocols', function (Blueprint $table) {
            $table->boolean('requires_recovery')->default(false)->after('is_contrast_enhanced');
        });
    }

    public function down(): void
    {
        Schema::table('imaging_protocols', function (Blueprint $table) {
            $table->dropColumn('requires_recovery');
        });
    }
};
