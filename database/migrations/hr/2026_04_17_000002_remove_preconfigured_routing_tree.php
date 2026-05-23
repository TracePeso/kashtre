<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('hr_organizational_units') ||
            ! Schema::hasColumn('hr_organizational_units', 'unit_kind')
        ) {
            return;
        }

        $routingNodeQuery = DB::table('hr_organizational_units')
            ->where(function ($query): void {
                $query->where('unit_kind', 'routing_node')
                    ->orWhereNull('unit_kind');
            });

        // Manual routing trees are attached to explicit tier levels. This migration
        // only clears the old generated tree, so late-running deployments must not
        // delete real branches that users have already configured.
        if (Schema::hasColumn('hr_organizational_units', 'tier_level_id')) {
            $routingNodeQuery->whereNull('tier_level_id');
        }

        if (Schema::hasColumn('hr_organizational_units', 'deleted_at')) {
            $routingNodeQuery->whereNull('deleted_at');
        }

        $routingNodeIds = $routingNodeQuery->pluck('id');

        if ($routingNodeIds->isEmpty()) {
            return;
        }

        $now = now();
        $unitsHaveUpdatedAt = Schema::hasColumn('hr_organizational_units', 'updated_at');
        $assignmentsHaveUpdatedAt = Schema::hasTable('hr_staff_assignments')
            && Schema::hasColumn('hr_staff_assignments', 'updated_at');
        $routesTableExists = Schema::hasTable('hr_client_space_routes');

        DB::transaction(function () use ($routingNodeIds, $now, $unitsHaveUpdatedAt, $assignmentsHaveUpdatedAt, $routesTableExists): void {
            $clientSpaceUpdate = ['parent_id' => null];
            $affectedClientSpaceIds = collect();

            if ($unitsHaveUpdatedAt) {
                $clientSpaceUpdate['updated_at'] = $now;
            }

            if ($routesTableExists) {
                $affectedClientSpaceIds = DB::table('hr_client_space_routes')
                    ->whereIn('routing_unit_id', $routingNodeIds)
                    ->pluck('client_space_unit_id')
                    ->unique();

                DB::table('hr_client_space_routes')
                    ->whereIn('routing_unit_id', $routingNodeIds)
                    ->delete();
            }

            DB::table('hr_organizational_units')
                ->where('unit_kind', 'client_space')
                ->whereIn('parent_id', $routingNodeIds)
                ->update($clientSpaceUpdate);

            if ($routesTableExists) {
                foreach ($affectedClientSpaceIds as $clientSpaceId) {
                    $remainingRoutes = DB::table('hr_client_space_routes')
                        ->where('client_space_unit_id', $clientSpaceId)
                        ->orderByDesc('is_primary')
                        ->orderBy('id')
                        ->get(['id', 'routing_unit_id', 'is_primary']);

                    if ($remainingRoutes->isEmpty()) {
                        continue;
                    }

                    $preferredRoute = $remainingRoutes->firstWhere('is_primary', true) ?? $remainingRoutes->first();

                    DB::table('hr_client_space_routes')
                        ->where('client_space_unit_id', $clientSpaceId)
                        ->update([
                            'is_primary' => false,
                            'updated_at' => $now,
                        ]);

                    DB::table('hr_client_space_routes')
                        ->where('id', $preferredRoute->id)
                        ->update([
                            'is_primary' => true,
                            'updated_at' => $now,
                        ]);

                    DB::table('hr_organizational_units')
                        ->where('id', $clientSpaceId)
                        ->update($unitsHaveUpdatedAt
                            ? ['parent_id' => $preferredRoute->routing_unit_id, 'updated_at' => $now]
                            : ['parent_id' => $preferredRoute->routing_unit_id]);
                }
            }

            if (Schema::hasTable('hr_staff_assignments') && Schema::hasColumn('hr_staff_assignments', 'organizational_unit_id')) {
                $assignmentUpdate = [
                    'organizational_unit_id' => null,
                ];

                if ($assignmentsHaveUpdatedAt) {
                    $assignmentUpdate['updated_at'] = $now;
                }

                if (Schema::hasColumn('hr_staff_assignments', 'status')) {
                    $assignmentUpdate['status'] = 'orphaned';
                }

                DB::table('hr_staff_assignments')
                    ->whereIn('organizational_unit_id', $routingNodeIds)
                    ->update($assignmentUpdate);
            }

            $unitUpdate = $unitsHaveUpdatedAt ? ['updated_at' => $now] : [];

            if (Schema::hasColumn('hr_organizational_units', 'deleted_at')) {
                $unitUpdate['deleted_at'] = $now;

                DB::table('hr_organizational_units')
                    ->whereIn('id', $routingNodeIds)
                    ->update($unitUpdate);

                return;
            }

            DB::table('hr_organizational_units')
                ->whereIn('id', $routingNodeIds)
                ->delete();
        });
    }

    public function down(): void
    {
        // Intentionally not restored: this migration clears obsolete preconfigured routing nodes.
    }
};
