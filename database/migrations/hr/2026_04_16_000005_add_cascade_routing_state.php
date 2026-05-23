<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_organizational_units', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_organizational_units', 'head_staff_uuid')) {
                $table->string('head_staff_uuid')->nullable()->after('type');
                $table->string('head_name')->nullable()->after('head_staff_uuid');
                $table->index(['organization_id', 'head_staff_uuid'], 'hr_units_org_head_index');
            }
        });

        Schema::table('hr_staff_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_staff_assignments', 'routed_by_user_id')) {
                $table->foreignId('routed_by_user_id')
                    ->nullable()
                    ->after('assigned_by')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->string('routed_by_staff_uuid')->nullable()->after('routed_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hr_staff_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('hr_staff_assignments', 'routed_by_user_id')) {
                $table->dropForeign(['routed_by_user_id']);
                $table->dropColumn([
                    'routed_by_user_id',
                    'routed_by_staff_uuid',
                ]);
            }
        });

        Schema::table('hr_organizational_units', function (Blueprint $table) {
            if (Schema::hasColumn('hr_organizational_units', 'head_staff_uuid')) {
                $table->dropIndex('hr_units_org_head_index');
                $table->dropColumn(['head_staff_uuid', 'head_name']);
            }
        });
    }
};
