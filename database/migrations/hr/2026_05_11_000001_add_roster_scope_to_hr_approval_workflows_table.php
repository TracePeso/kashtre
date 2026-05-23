<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_approval_workflows')) {
            return;
        }

        try {
            Schema::table('hr_approval_workflows', function (Blueprint $table): void {
                $table->dropUnique('hr_approval_workflows_organization_id_approval_category_unique');
            });
        } catch (\Throwable) {
            // Older or repaired environments may already have dropped this legacy uniqueness rule.
        }

        Schema::table('hr_approval_workflows', function (Blueprint $table): void {
            if (! Schema::hasColumn('hr_approval_workflows', 'organizational_unit_id')) {
                $table->foreignId('organizational_unit_id')
                    ->nullable()
                    ->after('organization_id')
                    ->constrained('hr_organizational_units')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('hr_approval_workflows', 'discipline_title')) {
                $table->string('discipline_title')->nullable()->after('approval_category');
            }

            $table->index(
                ['organization_id', 'approval_category', 'organizational_unit_id'],
                'approval_workflows_roster_scope_lookup'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hr_approval_workflows')) {
            return;
        }

        Schema::table('hr_approval_workflows', function (Blueprint $table): void {
            $table->dropIndex('approval_workflows_roster_scope_lookup');

            if (Schema::hasColumn('hr_approval_workflows', 'organizational_unit_id')) {
                $table->dropConstrainedForeignId('organizational_unit_id');
            }

            if (Schema::hasColumn('hr_approval_workflows', 'discipline_title')) {
                $table->dropColumn('discipline_title');
            }

            $table->unique(['organization_id', 'approval_category']);
        });
    }
};
