<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_approval_workflows')) {
            return;
        }

        if (DB::getDriverName() === 'mysql' && $this->mysqlIndexExists('hr_approval_workflows_organization_id_approval_category_unique')) {
            Schema::table('hr_approval_workflows', function (Blueprint $table): void {
                $table->dropUnique('hr_approval_workflows_organization_id_approval_category_unique');
            });
        }

        if (! $this->indexExists('hr_approval_workflows', 'approval_workflows_roster_scope_lookup')) {
            Schema::table('hr_approval_workflows', function (Blueprint $table): void {
                $table->index(
                    ['organization_id', 'approval_category', 'organizational_unit_id'],
                    'approval_workflows_roster_scope_lookup'
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('hr_approval_workflows')) {
            return;
        }

        if ($this->indexExists('hr_approval_workflows', 'approval_workflows_roster_scope_lookup')) {
            Schema::table('hr_approval_workflows', function (Blueprint $table): void {
                $table->dropIndex('approval_workflows_roster_scope_lookup');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            return collect($indexes)->contains(fn ($index): bool => ($index->name ?? null) === $indexName);
        }

        if (DB::getDriverName() === 'mysql') {
            return $this->mysqlIndexExists($indexName);
        }

        return false;
    }

    private function mysqlIndexExists(string $indexName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'hr_approval_workflows')
            ->where('index_name', $indexName)
            ->exists();
    }
};
