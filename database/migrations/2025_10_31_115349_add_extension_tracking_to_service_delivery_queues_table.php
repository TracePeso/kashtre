<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('service_delivery_queues', 'extended_at')) {
            Schema::table('service_delivery_queues', function (Blueprint $table) {
                $table->timestamp('extended_at')->nullable()->after('partially_done_at');
            });
        }

        // We can use a raw statement for ENUM change if needed, but safely
        DB::statement("ALTER TABLE service_delivery_queues MODIFY COLUMN status ENUM('pending', 'in_progress', 'partially_done', 'completed', 'cancelled', 'not_done') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('service_delivery_queues', 'extended_at')) {
            Schema::table('service_delivery_queues', function (Blueprint $table) {
                $table->dropColumn('extended_at');
            });
        }

        DB::statement("ALTER TABLE service_delivery_queues MODIFY COLUMN status ENUM('pending', 'in_progress', 'partially_done', 'completed', 'cancelled') DEFAULT 'pending'");
    }
};
