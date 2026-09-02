<?php

use App\Models\Store;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_demand_ledgers')
            || ! Schema::hasColumn('inventory_demand_ledgers', 'store_id')) {
            return;
        }

        // Prefer invoice.end_store_id when present.
        if (Schema::hasColumn('invoices', 'end_store_id')) {
            DB::statement(<<<'SQL'
                UPDATE inventory_demand_ledgers d
                INNER JOIN invoices i ON i.id = d.invoice_id
                SET d.store_id = i.end_store_id
                WHERE d.store_id IS NULL
                  AND i.end_store_id IS NOT NULL
            SQL);
        }

        // Remaining nulls: first End Store for the business (deterministic).
        $nullRows = DB::table('inventory_demand_ledgers')
            ->whereNull('store_id')
            ->select('id', 'business_id')
            ->get();

        $cache = [];
        foreach ($nullRows as $row) {
            $businessId = (int) $row->business_id;
            if (! array_key_exists($businessId, $cache)) {
                $cache[$businessId] = Store::query()
                    ->where('business_id', $businessId)
                    ->where(function ($query) {
                        $query->where('distribution_type', Store::DISTRIBUTION_END)
                            ->orWhereNull('distribution_type');
                    })
                    ->orderBy('id')
                    ->value('id');
            }

            if ($cache[$businessId]) {
                DB::table('inventory_demand_ledgers')
                    ->where('id', $row->id)
                    ->update(['store_id' => $cache[$businessId]]);
            }
        }
    }

    public function down(): void
    {
        // Intentional no-op: historical backfill is not reversible.
    }
};
