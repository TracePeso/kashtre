<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_staff_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_staff_assignments', 'staff_department')) {
                $table->string('staff_department')->nullable()->after('staff_cadre');
            }

            if (! Schema::hasColumn('hr_staff_assignments', 'staff_title')) {
                $table->string('staff_title')->nullable()->after('staff_department');
            }

            $table->index(['organization_id', 'staff_department'], 'hr_staff_assignments_org_department_index');
            $table->index(['organization_id', 'staff_title'], 'hr_staff_assignments_org_title_index');
        });
    }

    public function down(): void
    {
        Schema::table('hr_staff_assignments', function (Blueprint $table) {
            $table->dropIndex('hr_staff_assignments_org_department_index');
            $table->dropIndex('hr_staff_assignments_org_title_index');
            $table->dropColumn(['staff_department', 'staff_title']);
        });
    }
};
