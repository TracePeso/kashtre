<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_duty_rosters')) {
            return;
        }

        Schema::table('hr_duty_rosters', function (Blueprint $table): void {
            if (! Schema::hasColumn('hr_duty_rosters', 'team_definitions')) {
                $table->json('team_definitions')->nullable()->after('discipline_titles');
            }

            if (! Schema::hasColumn('hr_duty_rosters', 'team_assignments')) {
                $table->json('team_assignments')->nullable()->after('team_definitions');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hr_duty_rosters')) {
            return;
        }

        Schema::table('hr_duty_rosters', function (Blueprint $table): void {
            foreach (['team_assignments', 'team_definitions'] as $column) {
                if (Schema::hasColumn('hr_duty_rosters', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
