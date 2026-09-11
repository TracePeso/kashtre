<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'hire_date')) {
                $table->date('hire_date')->nullable()->after('birth_date');
            }
            if (! Schema::hasColumn('users', 'employment_type')) {
                $table->string('employment_type', 50)->nullable()->after('hire_date');
            }
            if (! Schema::hasColumn('users', 'employee_code')) {
                $table->string('employee_code', 100)->nullable()->after('employment_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['hire_date', 'employment_type', 'employee_code'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
