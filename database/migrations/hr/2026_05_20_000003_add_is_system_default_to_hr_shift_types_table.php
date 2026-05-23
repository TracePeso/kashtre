<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_shift_types')) {
            return;
        }

        if (! Schema::hasColumn('hr_shift_types', 'is_system_default')) {
            Schema::table('hr_shift_types', function (Blueprint $table): void {
                $table->boolean('is_system_default')->default(false)->after('is_rosterable');
            });
        }

        $organizationIds = DB::table('hr_shift_types')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('organization_id');

        foreach ($organizationIds as $organizationId) {
            $defaultShiftId = DB::table('hr_shift_types')
                ->where('organization_id', $organizationId)
                ->whereNull('deleted_at')
                ->orderByRaw("CASE WHEN code = 'DAY' THEN 0 ELSE 1 END")
                ->orderBy('id')
                ->value('id');

            DB::table('hr_shift_types')
                ->where('organization_id', $organizationId)
                ->whereNull('deleted_at')
                ->update(['is_system_default' => false]);

            if ($defaultShiftId) {
                DB::table('hr_shift_types')
                    ->where('id', $defaultShiftId)
                    ->update(['is_system_default' => true]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('hr_shift_types') || ! Schema::hasColumn('hr_shift_types', 'is_system_default')) {
            return;
        }

        Schema::table('hr_shift_types', function (Blueprint $table): void {
            $table->dropColumn('is_system_default');
        });
    }
};
