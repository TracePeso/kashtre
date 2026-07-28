<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // (business_id, model_type, created_at) looks like the obvious
        // choice for ListImagingAuditLog::table()'s where(business_id) +
        // whereIn(model_type) + order by created_at desc query, but
        // EXPLAIN showed MySQL can't use a multi-value IN column to
        // satisfy an ORDER BY that follows it — it still filesorts.
        // (business_id, created_at) — leaving model_type as a post-filter
        // — lets the index satisfy the sort directly (confirmed via
        // EXPLAIN: no filesort), which matters more at the row counts this
        // table is meant to handle than trimming the pre-filter row count.
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['business_id', 'created_at'], 'activity_logs_business_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('activity_logs_business_created_idx');
        });
    }
};
