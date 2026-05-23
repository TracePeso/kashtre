<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_approval_requests')) {
            return;
        }

        Schema::table('hr_approval_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_approval_requests', 'linked_roster_id')) {
                $table->foreignId('linked_roster_id')
                    ->nullable()
                    ->after('approval_category')
                    ->constrained('hr_duty_rosters')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hr_approval_requests') || ! Schema::hasColumn('hr_approval_requests', 'linked_roster_id')) {
            return;
        }

        Schema::table('hr_approval_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('linked_roster_id');
        });
    }
};
