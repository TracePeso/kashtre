<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imaging_studies', function (Blueprint $table) {
            $table->boolean('is_consumption_executed')->default(false)->after('status_history');
            $table->timestamp('consumption_executed_at')->nullable()->after('is_consumption_executed');
        });
    }

    public function down(): void
    {
        Schema::table('imaging_studies', function (Blueprint $table) {
            $table->dropColumn(['is_consumption_executed', 'consumption_executed_at']);
        });
    }
};
