<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_staff_assignments', function (Blueprint $table) {
            $table->foreignId('organizational_unit_id')
                  ->nullable()
                  ->after('organization_id')
                  ->constrained('hr_organizational_units')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_staff_assignments', function (Blueprint $table) {
            $table->dropForeign(['organizational_unit_id']);
            $table->dropColumn('organizational_unit_id');
        });
    }
};
