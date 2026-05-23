<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_shift_types', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_shift_types', 'break_durations_minutes')) {
                $table->json('break_durations_minutes')->nullable()->after('break_duration_minutes');
            }
        });

        DB::table('hr_shift_types')
            ->select(['id', 'break_duration_minutes'])
            ->orderBy('id')
            ->chunkById(100, function ($shifts): void {
                foreach ($shifts as $shift) {
                    $minutes = max(0, (int) $shift->break_duration_minutes);

                    DB::table('hr_shift_types')
                        ->where('id', $shift->id)
                        ->update([
                            'break_durations_minutes' => json_encode(
                                $minutes > 0 ? [['duration_minutes' => $minutes]] : []
                            ),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('hr_shift_types', function (Blueprint $table) {
            if (Schema::hasColumn('hr_shift_types', 'break_durations_minutes')) {
                $table->dropColumn('break_durations_minutes');
            }
        });
    }
};
