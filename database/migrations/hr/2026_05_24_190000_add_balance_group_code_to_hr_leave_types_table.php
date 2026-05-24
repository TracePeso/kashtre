<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_leave_types', function (Blueprint $table): void {
            $table->string('balance_group_code', 40)->nullable()->after('code');
        });

        DB::table('hr_leave_types')
            ->whereNull('balance_group_code')
            ->update([
                'balance_group_code' => DB::raw('UPPER(code)'),
            ]);

        DB::table('hr_leave_types')
            ->whereRaw('UPPER(code) IN (?, ?)', ['L1', 'L2'])
            ->update(['balance_group_code' => 'L']);

        DB::table('hr_leave_types')
            ->whereRaw('UPPER(code) IN (?, ?)', ['S1', 'S2'])
            ->update(['balance_group_code' => 'S']);
    }

    public function down(): void
    {
        Schema::table('hr_leave_types', function (Blueprint $table): void {
            $table->dropColumn('balance_group_code');
        });
    }
};
