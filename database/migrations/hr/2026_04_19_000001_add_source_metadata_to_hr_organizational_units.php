<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_organizational_units', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_organizational_units', 'source_type')) {
                $table->string('source_type')->nullable()->after('unit_kind');
            }

            if (! Schema::hasColumn('hr_organizational_units', 'source_key')) {
                $table->string('source_key')->nullable()->after('source_type');
            }

            if (! Schema::hasColumn('hr_organizational_units', 'metadata')) {
                $table->json('metadata')->nullable()->after('source_key');
            }

            $table->index(['organization_id', 'source_type', 'source_key'], 'hr_units_org_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('hr_organizational_units', function (Blueprint $table) {
            $table->dropIndex('hr_units_org_source_index');
            $table->dropColumn(['source_type', 'source_key', 'metadata']);
        });
    }
};
