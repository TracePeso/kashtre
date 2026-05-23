<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('hr_shift_types')
            ->where('code', 'DAY')
            ->whereNull('deleted_at')
            ->update([
                'name' => 'Regular working Hours',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('hr_shift_types')
            ->where('code', 'DAY')
            ->where('name', 'Regular working Hours')
            ->whereNull('deleted_at')
            ->update([
                'name' => 'Day Shift',
                'updated_at' => now(),
            ]);
    }
};
