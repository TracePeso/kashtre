<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_duty_rosters')) {
            return;
        }

        Schema::table('hr_duty_rosters', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_duty_rosters', 'discipline_titles')) {
                $table->json('discipline_titles')->nullable()->after('cadre_or_discipline');
            }
        });

        DB::table('hr_duty_rosters')
            ->whereNull('discipline_titles')
            ->whereNotNull('cadre_or_discipline')
            ->orderBy('id')
            ->get(['id', 'cadre_or_discipline'])
            ->each(function (object $roster): void {
                $title = trim((string) $roster->cadre_or_discipline);

                if ($title === '') {
                    return;
                }

                DB::table('hr_duty_rosters')
                    ->where('id', $roster->id)
                    ->update([
                        'discipline_titles' => json_encode([$title], JSON_THROW_ON_ERROR),
                    ]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hr_duty_rosters')) {
            return;
        }

        Schema::table('hr_duty_rosters', function (Blueprint $table) {
            if (Schema::hasColumn('hr_duty_rosters', 'discipline_titles')) {
                $table->dropColumn('discipline_titles');
            }
        });
    }
};
