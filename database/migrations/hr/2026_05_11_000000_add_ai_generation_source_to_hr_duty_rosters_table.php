<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_duty_rosters', function (Blueprint $table): void {
            if (! Schema::hasColumn('hr_duty_rosters', 'ai_generation_source')) {
                $table->string('ai_generation_source', 20)->nullable()->after('ai_generation_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hr_duty_rosters', function (Blueprint $table): void {
            if (Schema::hasColumn('hr_duty_rosters', 'ai_generation_source')) {
                $table->dropColumn('ai_generation_source');
            }
        });
    }
};
