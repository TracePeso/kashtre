<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventory_stock_levels')->update([
            'physical_quantity_suom' => DB::raw('quantity_suom'),
        ]);
    }

    public function down(): void
    {
        // Cannot restore prior physical counts.
    }
};
