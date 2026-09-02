<?php

use App\Models\Store;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parentIds = DB::table('stores')
            ->whereNotNull('parent_id')
            ->distinct()
            ->pluck('parent_id');

        DB::table('stores')
            ->where(function ($query) use ($parentIds) {
                $query->whereNull('parent_id');

                if ($parentIds->isNotEmpty()) {
                    $query->orWhereIn('id', $parentIds);
                }
            })
            ->update(['distribution_type' => Store::DISTRIBUTION_INTERIM]);
    }

    public function down(): void
    {
        // Historical distribution types cannot be restored reliably.
    }
};
