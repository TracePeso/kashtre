<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Department;
use App\Models\HrClientSpaceRoute;
use App\Models\HrOrganizationalUnit;
use App\Models\HrOrganizationTierLevel;
use App\Models\HrStaffRoutingEvent;
use App\Models\Organization;
use App\Models\Section;
use App\Models\StaffAssignment;
use App\Models\Title;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BuildHrRoutingStructure extends Command
{
    protected $signature = 'hr:build-routing-structure
        {--organization-id= : Existing HR organization id}
        {--business-id= : Main business id to map into HR}
        {--business-uuid= : Main business uuid to map into HR}
        {--levels= : Comma-separated ordered routing level names}
        {--auto-discover : Build routing structures for every business in the system}
        {--if-missing : Only build targets that do not already have routing nodes}
        {--upgrade-generated : Rebuild auto-generated routing structures that still use the shallow legacy layout}
        {--fresh : Remove existing routing levels and routing nodes before rebuilding}
        {--dry-run : Show the intended changes without saving}
        {--force : Skip the fresh rebuild confirmation prompt}';

    protected $description = 'Build a deep HR routing structure organogram from seeded business data';

    public function handle(): int
    {
        $targets = $this->resolveTargets();

        if ($targets->isEmpty()) {
            $this->warn('No eligible HR routing targets were found.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $fresh = (bool) $this->option('fresh');
        $ifMissing = (bool) $this->option('if-missing');
        $upgradeGenerated = (bool) $this->option('upgrade-generated');
        $summaries = [];
        $skippedTargets = 0;

        foreach ($targets as $target) {
            $business = $target['business'];
            $organization = $target['organization'];
            $organizationWasCreated = $target['organization_was_created'];
            $existingRoutingNodeCount = $this->existingRoutingNodeCount($organization);
            $needsUpgrade = $upgradeGenerated && $this->generatedStructureNeedsUpgrade($organization);
            $effectiveFresh = $fresh || $needsUpgrade;

            if ($ifMissing && $existingRoutingNodeCount > 0 && ! $needsUpgrade) {
                $label = $organization->name ?: "Organization {$organization->id}";
                $this->line("Skipping {$label}: routing structure already exists.");
                $skippedTargets++;
                continue;
            }

            $levelNames = $this->resolveLevelNames($business);

            if (empty($levelNames)) {
                $label = $organization->name ?: "Organization {$organization->id}";
                $this->warn("Skipping {$label}: no routing levels were resolved.");
                $skippedTargets++;
                continue;
            }

            if ($needsUpgrade) {
                $label = $organization->name ?: "Organization {$organization->id}";
                $this->line("Upgrading {$label}: rebuilding legacy auto-generated routing structure to the deeper layout.");
            }

            if ($fresh && ! $dryRun && ! $this->option('force')) {
                $label = $organization->name ?: "Organization {$organization->id}";

                if (! $this->confirm("Rebuild the HR routing structure for {$label}? Existing routing nodes and levels will be replaced.")) {
                    $this->warn("Skipped {$label}.");
                    $skippedTargets++;
                    continue;
                }
            }

            try {
                DB::beginTransaction();
                $summary = $this->buildStructure(
                    $organization,
                    $business,
                    $levelNames,
                    $effectiveFresh,
                    $organizationWasCreated,
                    $existingRoutingNodeCount
                );

                if ($dryRun) {
                    DB::rollBack();
                } else {
                    DB::commit();
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            $summaries[] = $summary;
        }

        if (empty($summaries)) {
            $this->info($skippedTargets > 0
                ? 'No HR routing structures needed building.'
                : 'No HR routing structures were built.');

            return self::SUCCESS;
        }

        $this->info($dryRun ? 'Dry run complete. No changes were saved.' : 'HR routing structure build complete.');

        foreach ($summaries as $index => $summary) {
            if ($index > 0) {
                $this->newLine();
            }

            $this->renderSummary($summary);
        }

        if ($skippedTargets > 0) {
            $this->line("Targets skipped: {$skippedTargets}");
        }

        return self::SUCCESS;
    }

    private function renderSummary(array $summary): void
    {
        $this->line("Organization: {$summary['organization_name']} (#{$summary['organization_id']})");

        if ($summary['business_name']) {
            $this->line("Business: {$summary['business_name']} (#{$summary['business_id']})");
        }

        $this->line('Levels: '.implode(' -> ', $summary['level_names']));
        $this->line("Tier levels created: {$summary['tier_levels_created']}");
        $this->line("Tier levels updated: {$summary['tier_levels_updated']}");
        $this->line("Routing nodes created: {$summary['routing_nodes_created']}");
        $this->line("Routing nodes updated: {$summary['routing_nodes_updated']}");
        $this->line("Branch nodes created: {$summary['branch_nodes_created']}");
        $this->line("Department nodes created: {$summary['department_nodes_created']}");
        $this->line("Section nodes created: {$summary['section_nodes_created']}");
        $this->line("Cadre nodes created: {$summary['cadre_nodes_created']}");
        $this->line("Role-band nodes created: {$summary['role_band_nodes_created']}");
        $this->line("Title nodes created: {$summary['title_nodes_created']}");
        $this->line("Staff assignments created from users: {$summary['staff_assignments_created_from_users']}");
        $this->line("Staff assignments refreshed from users: {$summary['staff_assignments_updated_from_users']}");
        $this->line("Staff routed into generated nodes: {$summary['staff_routed_to_generated_nodes']}");
        $this->line("Staff already aligned: {$summary['staff_already_aligned']}");
        $this->line("Staff skipped because they are inactive: {$summary['staff_skipped_inactive']}");
        $this->line("Staff skipped because they are already in client spaces: {$summary['staff_skipped_client_space']}");

        if ($summary['staff_assignment_mode']) {
            $this->line("Staff placement mode: {$summary['staff_assignment_mode']}");
        }

        if ($summary['staff_skipped_missing_target'] > 0) {
            $this->line("Staff skipped because no routing target could be resolved: {$summary['staff_skipped_missing_target']}");
        }

        if ($summary['fresh']) {
            $this->line("Routing nodes cleared first: {$summary['routing_nodes_cleared']}");
            $this->line("Tier levels cleared first: {$summary['tier_levels_cleared']}");
        }

        if (! empty($summary['tree_preview'])) {
            $this->line('Organogram Preview:');
            foreach ($summary['tree_preview'] as $line) {
                $this->line("  {$line}");
            }
        }
    }

    private function resolveTargets(): Collection
    {
        $explicitTarget = $this->resolveExplicitTarget();

        if ($explicitTarget !== null) {
            return collect([$explicitTarget]);
        }

        if (! $this->option('auto-discover')) {
            $this->error('Choose a target with --organization-id, --business-id, --business-uuid, or use --auto-discover.');

            return collect();
        }

        return Business::query()
            ->where('uuid', '!=', 'demo-business-uuid')
            ->orderBy('id')
            ->get()
            ->map(function (Business $business): array {
                [$organization, $organizationWasCreated] = $this->organizationForBusiness($business);

                return [
                    'business' => $business,
                    'organization' => $organization,
                    'organization_was_created' => $organizationWasCreated,
                ];
            });
    }

    private function resolveExplicitTarget(): ?array
    {
        $organizationId = $this->option('organization-id');
        $businessId = $this->option('business-id');
        $businessUuid = $this->option('business-uuid');

        if ($organizationId) {
            $organization = Organization::query()->find($organizationId);

            if (! $organization) {
                throw new RuntimeException("Organization {$organizationId} was not found.");
            }

            $business = $organization->external_business_uuid
                ? Business::query()->where('uuid', $organization->external_business_uuid)->first()
                : null;

            return [
                'business' => $business,
                'organization' => $organization,
                'organization_was_created' => false,
            ];
        }

        $business = null;

        if ($businessId) {
            $business = Business::query()->find($businessId);
        } elseif ($businessUuid) {
            $business = Business::query()->where('uuid', $businessUuid)->first();
        }

        if (! $business) {
            return null;
        }

        [$organization, $organizationWasCreated] = $this->organizationForBusiness($business);

        return [
            'business' => $business,
            'organization' => $organization,
            'organization_was_created' => $organizationWasCreated,
        ];
    }

    private function organizationForBusiness(Business $business): array
    {
        $organization = Organization::query()->firstOrCreate(
            ['external_business_uuid' => $business->uuid],
            ['name' => $business->name]
        );

        if ($organization->name !== $business->name) {
            $organization->forceFill(['name' => $business->name])->save();
        }

        return [$organization, $organization->wasRecentlyCreated];
    }

    private function existingRoutingNodeCount(Organization $organization): int
    {
        return HrOrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->routingNodes()
            ->count();
    }

    private function generatedStructureNeedsUpgrade(Organization $organization): bool
    {
        $routingNodes = HrOrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->routingNodes()
            ->get(['source_type', 'metadata']);

        if ($routingNodes->isEmpty()) {
            return false;
        }

        $allGenerated = $routingNodes->every(function (HrOrganizationalUnit $node): bool {
            $generatedBy = (string) data_get($node->metadata, 'generated_by', '');

            return str_starts_with((string) $node->source_type, 'generated_')
                && $generatedBy === 'hr:build-routing-structure';
        });

        if (! $allGenerated) {
            return false;
        }

        $maxLevelOrder = (int) HrOrganizationTierLevel::query()
            ->where('organization_id', $organization->id)
            ->max('level_order');

        $tierLevelCount = HrOrganizationTierLevel::query()
            ->where('organization_id', $organization->id)
            ->count();

        return $tierLevelCount < 7 || $maxLevelOrder < 7;
    }

    private function resolveLevelNames(?Business $business): array
    {
        $provided = $this->option('levels');

        if (filled($provided)) {
            return collect(explode(',', (string) $provided))
                ->map(fn (string $name): string => trim($name))
                ->filter()
                ->unique()
                ->take(7)
                ->values()
                ->all();
        }

        return [
            'Directorate',
            'Branch',
            'Department',
            'Section',
            'Cadre',
            'Role Band',
            'Title',
        ];
    }

    private function prepareStaffDerivedBusinessLookups(Organization $organization, ?Business $business): void
    {
        if (! $business) {
            return;
        }

        $departmentMap = Department::withTrashed()
            ->where('business_id', $business->id)
            ->get()
            ->keyBy(fn (Department $department): string => $this->normalizeNodeLabel($department->name));

        $titleMap = Title::withTrashed()
            ->where('business_id', $business->id)
            ->get()
            ->keyBy(fn (Title $title): string => $this->normalizeNodeLabel($title->name));

        $assignmentLookups = StaffAssignment::query()
            ->where('organization_id', $organization->id)
            ->where(function ($query): void {
                $query->whereNotNull('staff_department')
                    ->orWhereNotNull('staff_title');
            })
            ->get(['staff_uuid', 'staff_department', 'staff_title'])
            ->keyBy('staff_uuid');

        foreach ($assignmentLookups as $assignment) {
            if (filled($assignment->staff_department)) {
                $key = $this->normalizeNodeLabel((string) $assignment->staff_department);

                if ($key !== '' && ! isset($departmentMap[$key])) {
                    $department = Department::withTrashed()
                        ->where('business_id', $business->id)
                        ->where('name', (string) $assignment->staff_department)
                        ->first();

                    if (! $department) {
                        $department = new Department([
                            'business_id' => $business->id,
                            'name' => (string) $assignment->staff_department,
                            'description' => 'Backfilled from HR staff assignment data',
                        ]);
                    } elseif ($department->trashed()) {
                        $department->restore();
                    }

                    $department->save();
                    $departmentMap->put($key, $department);
                }
            }

            if (filled($assignment->staff_title)) {
                $key = $this->normalizeNodeLabel((string) $assignment->staff_title);

                if ($key !== '' && ! isset($titleMap[$key])) {
                    $title = Title::withTrashed()
                        ->where('business_id', $business->id)
                        ->where('name', (string) $assignment->staff_title)
                        ->first();

                    if (! $title) {
                        $title = new Title([
                            'business_id' => $business->id,
                            'name' => (string) $assignment->staff_title,
                            'description' => 'Backfilled from HR staff assignment data',
                        ]);
                    } elseif ($title->trashed()) {
                        $title->restore();
                    }

                    $title->save();
                    $titleMap->put($key, $title);
                }
            }
        }

        User::query()
            ->where('business_id', $business->id)
            ->whereNotNull('staff_uuid')
            ->get(['id', 'staff_uuid', 'department_id', 'title_id'])
            ->each(function (User $user) use ($assignmentLookups, $departmentMap, $titleMap): void {
                $assignment = $assignmentLookups->get($user->staff_uuid);

                if (! $assignment) {
                    return;
                }

                $updates = [];

                if (! $user->department_id && filled($assignment->staff_department)) {
                    $department = $departmentMap->get($this->normalizeNodeLabel((string) $assignment->staff_department));

                    if ($department) {
                        $updates['department_id'] = $department->id;
                    }
                }

                if (! $user->title_id && filled($assignment->staff_title)) {
                    $title = $titleMap->get($this->normalizeNodeLabel((string) $assignment->staff_title));

                    if ($title) {
                        $updates['title_id'] = $title->id;
                    }
                }

                if (! empty($updates)) {
                    $user->forceFill($updates)->save();
                }
            });
    }

    private function buildStructure(
        Organization $organization,
        ?Business $business,
        array $levelNames,
        bool $fresh,
        bool $organizationWasCreated,
        int $existingRoutingNodeCount
    ): array {
        $summary = [
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'business_id' => $business?->id,
            'business_name' => $business?->name,
            'organization_created' => $organizationWasCreated,
            'level_names' => $levelNames,
            'fresh' => $fresh,
            'tier_levels_created' => 0,
            'tier_levels_updated' => 0,
            'routing_nodes_created' => 0,
            'routing_nodes_updated' => 0,
            'branch_nodes_created' => 0,
            'department_nodes_created' => 0,
            'section_nodes_created' => 0,
            'cadre_nodes_created' => 0,
            'role_band_nodes_created' => 0,
            'title_nodes_created' => 0,
            'routing_nodes_cleared' => 0,
            'tier_levels_cleared' => 0,
            'staff_assignments_created_from_users' => 0,
            'staff_assignments_updated_from_users' => 0,
            'staff_routed_to_generated_nodes' => 0,
            'staff_already_aligned' => 0,
            'staff_skipped_inactive' => 0,
            'staff_skipped_client_space' => 0,
            'staff_skipped_missing_target' => 0,
            'staff_assignment_mode' => '',
            'tree_preview' => [],
        ];

        if ($fresh) {
            ['routing_nodes_cleared' => $summary['routing_nodes_cleared'], 'tier_levels_cleared' => $summary['tier_levels_cleared']] = $this->clearExistingStructure($organization);
        }

        $this->prepareStaffDerivedBusinessLookups($organization, $business);

        $tierLevels = [];

        foreach ($levelNames as $index => $levelName) {
            $order = $index + 1;
            [$tierLevel, $tierLevelAction] = $this->upsertTierLevel($organization, $levelName, $order);
            $tierLevels[$order] = $tierLevel;

            if ($tierLevelAction === 'created') {
                $summary['tier_levels_created']++;
            } elseif ($tierLevelAction === 'updated') {
                $summary['tier_levels_updated']++;
            }
        }

        $rootTierLevel = $tierLevels[1] ?? null;

        if (! $rootTierLevel) {
            return $summary;
        }

        [$rootNode, $rootAction] = $this->upsertRoutingNode(
            $organization,
            $rootTierLevel,
            null,
            $rootTierLevel->name,
            'generated_organogram_root',
            "organization:{$organization->id}:root",
            1,
            ['organization_created' => $organizationWasCreated]
        );
        $this->trackRoutingNodeAction($summary, $rootAction);
        $this->appendTreePreview($summary, $rootNode->name);

        $branches = $this->branchesForBusiness($business);
        $departments = $this->departmentsForBusiness($business);
        $sections = $this->sectionsForBusiness($business);
        $contexts = $this->routingContextsForBusiness($organization, $business);

        [$branchNodesById, $branchNodesByName] = $this->buildBranchNodes(
            $organization,
            $rootNode,
            $tierLevels[2] ?? null,
            $branches,
            $summary
        );

        [$departmentNodesByIdKey, $departmentNodesByNameKey] = $this->buildDepartmentNodes(
            $organization,
            $rootNode,
            $tierLevels[3] ?? null,
            $departments,
            $contexts,
            $branchNodesById,
            $summary
        );

        [$sectionNodesByIdKey, $sectionNodesByNameKey] = $this->buildSectionNodes(
            $organization,
            $rootNode,
            $tierLevels[4] ?? null,
            $sections,
            $contexts,
            $departmentNodesByIdKey,
            $departmentNodesByNameKey,
            $branchNodesById,
            $branchNodesByName,
            $departments,
            $business,
            $summary
        );

        $cadreNodesByKey = $this->buildCadreNodes(
            $organization,
            $rootNode,
            $tierLevels[5] ?? null,
            $contexts,
            $branchNodesById,
            $branchNodesByName,
            $departmentNodesByIdKey,
            $departmentNodesByNameKey,
            $sectionNodesByIdKey,
            $sectionNodesByNameKey,
            $summary
        );

        $roleBandNodesByKey = $this->buildRoleBandNodes(
            $organization,
            $rootNode,
            $tierLevels[6] ?? null,
            $contexts,
            $branchNodesById,
            $branchNodesByName,
            $departmentNodesByIdKey,
            $departmentNodesByNameKey,
            $sectionNodesByIdKey,
            $sectionNodesByNameKey,
            $cadreNodesByKey,
            $summary
        );

        $titleNodesByKey = $this->buildTitleNodes(
            $organization,
            $rootNode,
            $tierLevels[7] ?? null,
            $contexts,
            $branchNodesById,
            $branchNodesByName,
            $departmentNodesByIdKey,
            $departmentNodesByNameKey,
            $sectionNodesByIdKey,
            $sectionNodesByNameKey,
            $cadreNodesByKey,
            $roleBandNodesByKey,
            $summary
        );

        if ($existingRoutingNodeCount === 0) {
            $assignmentSummary = $this->assignStaffToGeneratedNodes(
                $organization,
                $rootNode,
                $contexts,
                $branchNodesById,
                $branchNodesByName,
                $departmentNodesByIdKey,
                $departmentNodesByNameKey,
                $sectionNodesByIdKey,
                $sectionNodesByNameKey,
                $cadreNodesByKey,
                $roleBandNodesByKey,
                $titleNodesByKey
            );

            $summary = array_merge($summary, $assignmentSummary);
            $summary['staff_assignment_mode'] = 'first_build_bootstrap';
        } else {
            $summary['staff_assignment_mode'] = 'skipped_existing_structure';
        }

        return $summary;
    }

    private function clearExistingStructure(Organization $organization): array
    {
        $routingNodeIds = HrOrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->routingNodes()
            ->pluck('id');

        $routingNodeCount = $routingNodeIds->count();
        $tierLevelCount = HrOrganizationTierLevel::query()
            ->where('organization_id', $organization->id)
            ->count();

        if ($routingNodeIds->isNotEmpty()) {
            HrClientSpaceRoute::query()
                ->whereIn('routing_unit_id', $routingNodeIds)
                ->delete();

            HrOrganizationalUnit::query()
                ->clientSpaces()
                ->whereIn('parent_id', $routingNodeIds)
                ->update(['parent_id' => null]);

            StaffAssignment::query()
                ->whereIn('organizational_unit_id', $routingNodeIds)
                ->update([
                    'organizational_unit_id' => null,
                    'status' => 'orphaned',
                ]);

            HrOrganizationalUnit::query()
                ->whereIn('id', $routingNodeIds)
                ->delete();
        }

        HrOrganizationTierLevel::query()
            ->where('organization_id', $organization->id)
            ->delete();

        return [
            'routing_nodes_cleared' => $routingNodeCount,
            'tier_levels_cleared' => $tierLevelCount,
        ];
    }

    private function upsertTierLevel(Organization $organization, string $name, int $order): array
    {
        $tierLevel = HrOrganizationTierLevel::query()
            ->where('organization_id', $organization->id)
            ->where('name', $name)
            ->first();

        if (! $tierLevel) {
            $tierLevel = HrOrganizationTierLevel::query()
                ->where('organization_id', $organization->id)
                ->where('level_order', $order)
                ->first();
        }

        if (! $tierLevel) {
            $tierLevel = new HrOrganizationTierLevel([
                'organization_id' => $organization->id,
            ]);
        }

        $action = ! $tierLevel->exists ? 'created' : 'unchanged';

        if ($tierLevel->name !== $name || (int) $tierLevel->level_order !== $order) {
            $action = $tierLevel->exists ? 'updated' : 'created';
        }

        $tierLevel->forceFill([
            'name' => $name,
            'level_order' => $order,
        ])->save();

        return [$tierLevel, $action];
    }

    private function branchesForBusiness(?Business $business): Collection
    {
        if (! $business) {
            return collect();
        }

        return Branch::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->get(['id', 'uuid', 'business_id', 'name']);
    }

    private function departmentsForBusiness(?Business $business): Collection
    {
        if (! $business) {
            return collect();
        }

        return Department::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->get(['id', 'uuid', 'business_id', 'name']);
    }

    private function sectionsForBusiness(?Business $business): Collection
    {
        if (! $business) {
            return collect();
        }

        return Section::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->get(['id', 'uuid', 'business_id', 'name']);
    }

    private function buildBranchNodes(
        Organization $organization,
        HrOrganizationalUnit $rootNode,
        ?HrOrganizationTierLevel $branchTierLevel,
        Collection $branches,
        array &$summary
    ): array {
        $branchNodesById = [];
        $branchNodesByName = [];

        if (! $branchTierLevel) {
            return [$branchNodesById, $branchNodesByName];
        }

        foreach ($branches->values() as $index => $branch) {
            [$node, $action] = $this->upsertRoutingNode(
                $organization,
                $branchTierLevel,
                $rootNode->id,
                $branch->name,
                'generated_branch_node',
                "branch:{$branch->id}",
                $index + 1,
                [
                    'business_id' => $branch->business_id,
                    'branch_id' => $branch->id,
                    'branch_uuid' => $branch->uuid,
                ]
            );

            $branchNodesById[$branch->id] = $node;
            $branchNodesByName[$this->normalizeNodeLabel($branch->name)] = $node;
            $this->trackRoutingNodeAction($summary, $action, 'branch');
            $this->appendTreePreview($summary, "- {$node->name}");
        }

        return [$branchNodesById, $branchNodesByName];
    }

    private function buildDepartmentNodes(
        Organization $organization,
        HrOrganizationalUnit $rootNode,
        ?HrOrganizationTierLevel $departmentTierLevel,
        Collection $departments,
        Collection $contexts,
        array $branchNodesById,
        array &$summary
    ): array {
        $departmentNodesByIdKey = [];
        $departmentNodesByNameKey = [];

        if (! $departmentTierLevel || $departments->isEmpty()) {
            return [$departmentNodesByIdKey, $departmentNodesByNameKey];
        }

        $definitionsByParent = [];

        foreach ($contexts as $context) {
            $departmentName = $context['department_name'] ?? null;
            $departmentId = $context['department_id'] ?? null;

            if (! $departmentId && ! filled($departmentName)) {
                continue;
            }

            $parentNode = $this->branchNodeForContext($context, $branchNodesById, []);
            $parentNode ??= $rootNode;
            $parentId = (int) $parentNode->id;
            $definitionsByParent[$parentId] ??= [];

            $definitionKey = $departmentId
                ? 'id:'.$departmentId
                : 'name:'.$this->normalizeNodeLabel((string) $departmentName);

            $resolvedDepartment = $departmentId
                ? $departments->firstWhere('id', $departmentId)
                : null;

            $definitionsByParent[$parentId][$definitionKey] = [
                'id' => $departmentId ? (int) $departmentId : null,
                'uuid' => $resolvedDepartment?->uuid,
                'business_id' => $resolvedDepartment?->business_id,
                'name' => $resolvedDepartment?->name ?: $departmentName ?: 'Department',
            ];
        }

        if (empty($definitionsByParent)) {
            if (! empty($branchNodesById)) {
                foreach ($branchNodesById as $branchNode) {
                    $definitionsByParent[(int) $branchNode->id] = $departments
                        ->mapWithKeys(fn (Department $department): array => [
                            'id:'.$department->id => [
                                'id' => (int) $department->id,
                                'uuid' => $department->uuid,
                                'business_id' => $department->business_id,
                                'name' => $department->name,
                            ],
                        ])
                        ->all();
                }
            } else {
                $definitionsByParent[(int) $rootNode->id] = $departments
                    ->mapWithKeys(fn (Department $department): array => [
                        'id:'.$department->id => [
                            'id' => (int) $department->id,
                            'uuid' => $department->uuid,
                            'business_id' => $department->business_id,
                            'name' => $department->name,
                        ],
                    ])
                    ->all();
            }
        }

        foreach ($definitionsByParent as $parentId => $definitions) {
            foreach (array_values($definitions) as $index => $definition) {
                [$node, $action] = $this->upsertRoutingNode(
                    $organization,
                    $departmentTierLevel,
                    $parentId,
                    $definition['name'],
                    'generated_department_branch',
                    $definition['id']
                        ? "department:{$definition['id']}:parent:{$parentId}"
                        : 'department-name:'.$this->normalizeNodeLabel($definition['name']).":parent:{$parentId}",
                    $index + 1,
                    [
                        'business_id' => $definition['business_id'],
                        'department_id' => $definition['id'],
                        'department_uuid' => $definition['uuid'],
                    ]
                );

                if ($definition['id']) {
                    $departmentNodesByIdKey[$this->idKey($parentId, $definition['id'])] = $node;
                }

                $departmentNodesByNameKey[$this->labelKey($parentId, $definition['name'])] = $node;
                $this->trackRoutingNodeAction($summary, $action, 'department');
                $this->appendTreePreview($summary, "- {$node->name}");
            }
        }

        return [$departmentNodesByIdKey, $departmentNodesByNameKey];
    }

    private function buildSectionNodes(
        Organization $organization,
        HrOrganizationalUnit $rootNode,
        ?HrOrganizationTierLevel $sectionTierLevel,
        Collection $sections,
        Collection $contexts,
        array $departmentNodesByIdKey,
        array $departmentNodesByNameKey,
        array $branchNodesById,
        array $branchNodesByName,
        Collection $departments,
        ?Business $business,
        array &$summary
    ): array {
        $sectionNodesByIdKey = [];
        $sectionNodesByNameKey = [];

        if (! $sectionTierLevel || $sections->isEmpty()) {
            return [$sectionNodesByIdKey, $sectionNodesByNameKey];
        }

        $definitionsByParent = [];

        foreach ($contexts as $context) {
            $parentNode = $this->departmentNodeForContext(
                $context,
                $rootNode,
                $branchNodesById,
                $branchNodesByName,
                $departmentNodesByIdKey,
                $departmentNodesByNameKey
            );

            if (! $parentNode) {
                continue;
            }

            $sectionName = $context['section_name'] ?? null;
            $sectionId = $context['section_id'] ?? null;

            if (! $sectionId && ! filled($sectionName)) {
                continue;
            }

            $parentId = (int) $parentNode->id;
            $definitionsByParent[$parentId] ??= [];
            $definitionKey = $sectionId
                ? 'id:'.$sectionId
                : 'name:'.$this->normalizeNodeLabel((string) $sectionName);

            $resolvedSection = $sectionId
                ? $sections->firstWhere('id', $sectionId)
                : null;

            $definitionsByParent[$parentId][$definitionKey] = [
                'id' => $sectionId ? (int) $sectionId : null,
                'uuid' => $resolvedSection?->uuid,
                'business_id' => $resolvedSection?->business_id,
                'name' => $resolvedSection?->name ?: $sectionName ?: 'Section',
            ];
        }

        if (empty($definitionsByParent)) {
            $preferredDepartmentIds = $this->preferredDepartmentIdsForSections($business, $departments, $sections);

            foreach ($sections as $section) {
                $departmentId = $preferredDepartmentIds[$section->id] ?? null;

                if (! $departmentId) {
                    continue;
                }

                $departmentNode = null;

                foreach ($departmentNodesByIdKey as $key => $node) {
                    if (str_ends_with($key, ':'.$departmentId)) {
                        $departmentNode = $node;
                        break;
                    }
                }

                if (! $departmentNode) {
                    continue;
                }

                $parentId = (int) $departmentNode->id;
                $definitionsByParent[$parentId] ??= [];
                $definitionsByParent[$parentId]['id:'.$section->id] = [
                    'id' => (int) $section->id,
                    'uuid' => $section->uuid,
                    'business_id' => $section->business_id,
                    'name' => $section->name,
                ];
            }
        }

        foreach ($definitionsByParent as $parentId => $definitions) {
            foreach (array_values($definitions) as $index => $definition) {
                [$node, $action] = $this->upsertRoutingNode(
                    $organization,
                    $sectionTierLevel,
                    $parentId,
                    $definition['name'],
                    'generated_section_branch',
                    $definition['id']
                        ? "section:{$definition['id']}:parent:{$parentId}"
                        : 'section-name:'.$this->normalizeNodeLabel($definition['name']).":parent:{$parentId}",
                    $index + 1,
                    [
                        'business_id' => $definition['business_id'],
                        'section_id' => $definition['id'],
                        'section_uuid' => $definition['uuid'],
                    ]
                );

                if ($definition['id']) {
                    $sectionNodesByIdKey[$this->idKey($parentId, $definition['id'])] = $node;
                }

                $sectionNodesByNameKey[$this->labelKey($parentId, $definition['name'])] = $node;
                $this->trackRoutingNodeAction($summary, $action, 'section');
                $this->appendTreePreview($summary, "- ".$this->previewPath($node));
            }
        }

        return [$sectionNodesByIdKey, $sectionNodesByNameKey];
    }

    private function buildCadreNodes(
        Organization $organization,
        HrOrganizationalUnit $rootNode,
        ?HrOrganizationTierLevel $cadreTierLevel,
        Collection $contexts,
        array $branchNodesById,
        array $branchNodesByName,
        array $departmentNodesByIdKey,
        array $departmentNodesByNameKey,
        array $sectionNodesByIdKey,
        array $sectionNodesByNameKey,
        array &$summary
    ): array {
        $cadreNodesByKey = [];

        if (! $cadreTierLevel) {
            return $cadreNodesByKey;
        }

        $definitionsByParent = [];

        foreach ($contexts as $context) {
            $cadre = $context['staff_cadre'] ?? null;

            if (! filled($cadre)) {
                continue;
            }

            $anchorNode = $this->sectionNodeForContext(
                $context,
                $rootNode,
                $branchNodesById,
                $branchNodesByName,
                $departmentNodesByIdKey,
                $departmentNodesByNameKey,
                $sectionNodesByIdKey,
                $sectionNodesByNameKey
            );

            $anchorNode ??= $this->departmentNodeForContext(
                $context,
                $rootNode,
                $branchNodesById,
                $branchNodesByName,
                $departmentNodesByIdKey,
                $departmentNodesByNameKey
            );
            $anchorNode ??= $this->branchNodeForContext($context, $branchNodesById, $branchNodesByName);
            $anchorNode ??= $rootNode;

            $parentId = (int) $anchorNode->id;
            $definitionsByParent[$parentId] ??= [];
            $definitionsByParent[$parentId][$this->normalizeNodeLabel($cadre)] = trim((string) $cadre);
        }

        foreach ($definitionsByParent as $parentId => $labels) {
            foreach (array_values($labels) as $index => $label) {
                [$node, $action] = $this->upsertRoutingNode(
                    $organization,
                    $cadreTierLevel,
                    $parentId,
                    $label,
                    'generated_cadre_branch',
                    'cadre:'.$this->normalizedParentValueKey($parentId, $label),
                    $index + 1,
                    []
                );

                $cadreNodesByKey[$this->normalizedParentValueKey($parentId, $label)] = $node;
                $this->trackRoutingNodeAction($summary, $action, 'cadre');
                $this->appendTreePreview($summary, "- ".$this->previewPath($node));
            }
        }

        return $cadreNodesByKey;
    }

    private function buildRoleBandNodes(
        Organization $organization,
        HrOrganizationalUnit $rootNode,
        ?HrOrganizationTierLevel $roleBandTierLevel,
        Collection $contexts,
        array $branchNodesById,
        array $branchNodesByName,
        array $departmentNodesByIdKey,
        array $departmentNodesByNameKey,
        array $sectionNodesByIdKey,
        array $sectionNodesByNameKey,
        array $cadreNodesByKey,
        array &$summary
    ): array {
        $roleBandNodesByKey = [];

        if (! $roleBandTierLevel) {
            return $roleBandNodesByKey;
        }

        $definitionsByParent = [];

        foreach ($contexts as $context) {
            $roleBand = $this->deriveRoleBand($context['title_name'] ?? null, $context['staff_cadre'] ?? null);

            if (! filled($roleBand)) {
                continue;
            }

            $anchorNode = $this->cadreNodeForContext(
                $context,
                $rootNode,
                $branchNodesById,
                $branchNodesByName,
                $departmentNodesByIdKey,
                $departmentNodesByNameKey,
                $sectionNodesByIdKey,
                $sectionNodesByNameKey,
                $cadreNodesByKey
            );

            $anchorNode ??= $this->sectionNodeForContext(
                $context,
                $rootNode,
                $branchNodesById,
                $branchNodesByName,
                $departmentNodesByIdKey,
                $departmentNodesByNameKey,
                $sectionNodesByIdKey,
                $sectionNodesByNameKey
            );
            $anchorNode ??= $this->departmentNodeForContext(
                $context,
                $rootNode,
                $branchNodesById,
                $branchNodesByName,
                $departmentNodesByIdKey,
                $departmentNodesByNameKey
            );
            $anchorNode ??= $this->branchNodeForContext($context, $branchNodesById, $branchNodesByName);
            $anchorNode ??= $rootNode;

            $parentId = (int) $anchorNode->id;
            $definitionsByParent[$parentId] ??= [];
            $definitionsByParent[$parentId][$this->normalizeNodeLabel($roleBand)] = $roleBand;
        }

        foreach ($definitionsByParent as $parentId => $labels) {
            foreach (array_values($labels) as $index => $label) {
                [$node, $action] = $this->upsertRoutingNode(
                    $organization,
                    $roleBandTierLevel,
                    $parentId,
                    $label,
                    'generated_role_band_branch',
                    'role-band:'.$this->normalizedParentValueKey($parentId, $label),
                    $index + 1,
                    []
                );

                $roleBandNodesByKey[$this->normalizedParentValueKey($parentId, $label)] = $node;
                $this->trackRoutingNodeAction($summary, $action, 'role_band');
                $this->appendTreePreview($summary, "- ".$this->previewPath($node));
            }
        }

        return $roleBandNodesByKey;
    }

    private function buildTitleNodes(
        Organization $organization,
        HrOrganizationalUnit $rootNode,
        ?HrOrganizationTierLevel $titleTierLevel,
        Collection $contexts,
        array $branchNodesById,
        array $branchNodesByName,
        array $departmentNodesByIdKey,
        array $departmentNodesByNameKey,
        array $sectionNodesByIdKey,
        array $sectionNodesByNameKey,
        array $cadreNodesByKey,
        array $roleBandNodesByKey,
        array &$summary
    ): array {
        $titleNodesByKey = [];

        if (! $titleTierLevel) {
            return $titleNodesByKey;
        }

        $definitionsByParent = [];

        foreach ($contexts as $context) {
            $title = $context['title_name'] ?? null;

            if (! filled($title)) {
                continue;
            }

            $anchorNode = $this->roleBandNodeForContext(
                $context,
                $rootNode,
                $branchNodesById,
                $branchNodesByName,
                $departmentNodesByIdKey,
                $departmentNodesByNameKey,
                $sectionNodesByIdKey,
                $sectionNodesByNameKey,
                $cadreNodesByKey,
                $roleBandNodesByKey
            );

            $anchorNode ??= $this->cadreNodeForContext(
                $context,
                $rootNode,
                $branchNodesById,
                $branchNodesByName,
                $departmentNodesByIdKey,
                $departmentNodesByNameKey,
                $sectionNodesByIdKey,
                $sectionNodesByNameKey,
                $cadreNodesByKey
            );
            $anchorNode ??= $this->sectionNodeForContext(
                $context,
                $rootNode,
                $branchNodesById,
                $branchNodesByName,
                $departmentNodesByIdKey,
                $departmentNodesByNameKey,
                $sectionNodesByIdKey,
                $sectionNodesByNameKey
            );
            $anchorNode ??= $this->departmentNodeForContext(
                $context,
                $rootNode,
                $branchNodesById,
                $branchNodesByName,
                $departmentNodesByIdKey,
                $departmentNodesByNameKey
            );
            $anchorNode ??= $this->branchNodeForContext($context, $branchNodesById, $branchNodesByName);
            $anchorNode ??= $rootNode;

            $parentId = (int) $anchorNode->id;
            $definitionsByParent[$parentId] ??= [];
            $definitionsByParent[$parentId][$this->normalizeNodeLabel($title)] = trim((string) $title);
        }

        foreach ($definitionsByParent as $parentId => $labels) {
            foreach (array_values($labels) as $index => $label) {
                [$node, $action] = $this->upsertRoutingNode(
                    $organization,
                    $titleTierLevel,
                    $parentId,
                    $label,
                    'generated_title_branch',
                    'title:'.$this->normalizedParentValueKey($parentId, $label),
                    $index + 1,
                    []
                );

                $titleNodesByKey[$this->normalizedParentValueKey($parentId, $label)] = $node;
                $this->trackRoutingNodeAction($summary, $action, 'title');
                $this->appendTreePreview($summary, "- ".$this->previewPath($node));
            }
        }

        return $titleNodesByKey;
    }

    private function assignStaffToGeneratedNodes(
        Organization $organization,
        HrOrganizationalUnit $rootNode,
        Collection $contexts,
        array $branchNodesById,
        array $branchNodesByName,
        array $departmentNodesByIdKey,
        array $departmentNodesByNameKey,
        array $sectionNodesByIdKey,
        array $sectionNodesByNameKey,
        array $cadreNodesByKey,
        array $roleBandNodesByKey,
        array $titleNodesByKey
    ): array {
        $syncSummary = $this->synchronizeStaffAssignmentsFromUsers($organization, $contexts);
        $summary = [
            'staff_assignments_created_from_users' => $syncSummary['created'],
            'staff_assignments_updated_from_users' => $syncSummary['updated'],
            'staff_routed_to_generated_nodes' => 0,
            'staff_already_aligned' => 0,
            'staff_skipped_inactive' => 0,
            'staff_skipped_client_space' => 0,
            'staff_skipped_missing_target' => 0,
        ];
        $now = now();
        $assignments = StaffAssignment::query()
            ->where('organization_id', $organization->id)
            ->orderBy('staff_name')
            ->get();

        foreach ($assignments as $assignment) {
            if ($assignment->status === 'inactive') {
                $summary['staff_skipped_inactive']++;
                continue;
            }

            $currentUnit = $assignment->organizationalUnit()->withTrashed()->first();

            if ($currentUnit?->isClientSpace()) {
                $summary['staff_skipped_client_space']++;
                continue;
            }

            $context = $contexts->get($assignment->staff_uuid) ?? [
                'branch_name' => $assignment->home_branch_name,
                'department_name' => $assignment->staff_department,
                'section_name' => null,
                'staff_cadre' => $assignment->staff_cadre,
                'title_name' => $assignment->staff_title,
            ];

            $targetNode = $this->generatedTargetNodeForContext(
                $context,
                $rootNode,
                $branchNodesById,
                $branchNodesByName,
                $departmentNodesByIdKey,
                $departmentNodesByNameKey,
                $sectionNodesByIdKey,
                $sectionNodesByNameKey,
                $cadreNodesByKey,
                $roleBandNodesByKey,
                $titleNodesByKey
            );

            if (! $targetNode) {
                $summary['staff_skipped_missing_target']++;
                continue;
            }

            $fromUnitId = $assignment->organizational_unit_id;
            $fromStatus = $assignment->status;

            if ((int) $fromUnitId === (int) $targetNode->id && $fromStatus === 'pending_routing') {
                $summary['staff_already_aligned']++;
                continue;
            }

            $assignment->forceFill([
                'organizational_unit_id' => $targetNode->id,
                'status' => 'pending_routing',
                'assigned_at' => $assignment->assigned_at ?? $now,
                'routed_at' => $now,
                'routed_by_user_id' => null,
                'routed_by_staff_uuid' => null,
            ])->save();

            HrStaffRoutingEvent::create([
                'staff_assignment_id' => $assignment->id,
                'organization_id' => $assignment->organization_id,
                'from_unit_id' => $fromUnitId,
                'to_unit_id' => $targetNode->id,
                'routed_by_user_id' => null,
                'routed_by_staff_uuid' => null,
                'from_status' => $fromStatus,
                'to_status' => 'pending_routing',
                'routed_at' => $now,
            ]);

            $summary['staff_routed_to_generated_nodes']++;
        }

        return $summary;
    }

    private function synchronizeStaffAssignmentsFromUsers(Organization $organization, Collection $contexts): array
    {
        if ($contexts->isEmpty()) {
            return ['created' => 0, 'updated' => 0];
        }

        $existingAssignments = StaffAssignment::query()
            ->where('organization_id', $organization->id)
            ->get()
            ->keyBy('staff_uuid');

        $created = 0;
        $updated = 0;

        foreach ($contexts as $staffUuid => $context) {
            $assignment = $existingAssignments->get($staffUuid) ?? new StaffAssignment([
                'organization_id' => $organization->id,
                'staff_uuid' => $staffUuid,
            ]);
            $wasExisting = $assignment->exists;
            $normalizedStatus = ($context['is_active'] ?? false) ? ($assignment->status ?: 'active') : 'inactive';

            $assignment->forceFill([
                'organization_id' => $organization->id,
                'staff_uuid' => $staffUuid,
                'staff_name' => $context['name'] ?? $staffUuid,
                'staff_cadre' => $context['staff_cadre'] ?: $assignment->staff_cadre,
                'staff_department' => $context['department_name'] ?: $assignment->staff_department,
                'staff_title' => $context['title_name'] ?: $assignment->staff_title,
                'home_branch_external_id' => $context['branch_external_id'] ?: $assignment->home_branch_external_id,
                'home_branch_name' => $context['branch_name'] ?: $assignment->home_branch_name,
                'assignment_type' => $assignment->assignment_type ?: 'primary',
                'assigned_at' => $assignment->assigned_at ?? now(),
                'status' => $normalizedStatus,
            ]);

            if (! $wasExisting) {
                $assignment->save();
                $existingAssignments->put($staffUuid, $assignment);
                $created++;
                continue;
            }

            if ($assignment->isDirty()) {
                $assignment->save();
                $updated++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    private function routingContextsForBusiness(Organization $organization, ?Business $business): Collection
    {
        $userContexts = $this->userContextsForBusiness($business);
        $assignments = StaffAssignment::query()
            ->where('organization_id', $organization->id)
            ->get()
            ->keyBy('staff_uuid');

        return $userContexts->mapWithKeys(function (array $context, string $staffUuid) use ($assignments): array {
            $assignment = $assignments->get($staffUuid);

            return [
                $staffUuid => [
                    'name' => $context['name'],
                    'branch_id' => $context['branch_id'],
                    'branch_name' => $context['branch_name'] ?: $assignment?->home_branch_name,
                    'branch_external_id' => $context['branch_external_id'] ?: $assignment?->home_branch_external_id,
                    'department_id' => $context['department_id'],
                    'department_name' => $context['department_name'] ?: $assignment?->staff_department,
                    'section_id' => $context['section_id'],
                    'section_name' => $context['section_name'],
                    'staff_cadre' => $assignment?->staff_cadre,
                    'title_name' => $context['title_name'] ?: $assignment?->staff_title,
                    'role_band' => $this->deriveRoleBand($context['title_name'] ?: $assignment?->staff_title, $assignment?->staff_cadre),
                    'is_active' => $context['is_active'],
                ],
            ];
        });
    }

    private function userContextsForBusiness(?Business $business): Collection
    {
        if (! $business) {
            return collect();
        }

        return User::query()
            ->with([
                'branch:id,uuid,name',
                'department:id,name',
                'section:id,name',
                'title:id,name',
            ])
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->get([
                'id',
                'uuid',
                'staff_uuid',
                'name',
                'status',
                'deactivated_at',
                'business_id',
                'branch_id',
                'department_id',
                'section_id',
                'title_id',
            ])
            ->mapWithKeys(function (User $user): array {
                $staffUuid = $user->staff_uuid;

                if (! $staffUuid) {
                    return [];
                }

                return [
                    $staffUuid => [
                        'name' => $user->name ?: $staffUuid,
                        'branch_id' => $user->branch_id ? (int) $user->branch_id : null,
                        'branch_name' => $user->branch?->name,
                        'branch_external_id' => $user->branch?->uuid ?: (string) ($user->branch_id ?: ''),
                        'department_id' => $user->department_id ? (int) $user->department_id : null,
                        'department_name' => $user->department?->name,
                        'section_id' => $user->section_id ? (int) $user->section_id : null,
                        'section_name' => $user->section?->name,
                        'title_name' => $user->title?->name,
                        'is_active' => $this->userIsActive($user),
                    ],
                ];
            });
    }

    private function userIsActive(User $user): bool
    {
        return $user->deactivated_at === null
            && ! in_array((string) $user->status, ['inactive', 'disabled'], true);
    }

    private function preferredDepartmentIdsForSections(
        ?Business $business,
        Collection $departments,
        Collection $sections
    ): array {
        if (! $business || $sections->isEmpty()) {
            return [];
        }

        $sectionIds = $sections->pluck('id')->all();
        $departmentIds = $departments->pluck('id')->flip();
        $preferredDepartmentIds = [];

        $usageRows = User::query()
            ->where('business_id', $business->id)
            ->whereIn('section_id', $sectionIds)
            ->whereNotNull('department_id')
            ->select('section_id', 'department_id', DB::raw('COUNT(*) as aggregate_count'))
            ->groupBy('section_id', 'department_id')
            ->orderBy('section_id')
            ->orderByDesc('aggregate_count')
            ->get();

        foreach ($usageRows as $row) {
            $sectionId = (int) $row->section_id;
            $departmentId = (int) $row->department_id;

            if (! isset($preferredDepartmentIds[$sectionId]) && isset($departmentIds[$departmentId])) {
                $preferredDepartmentIds[$sectionId] = $departmentId;
            }
        }

        foreach ($sections as $section) {
            if (isset($preferredDepartmentIds[$section->id])) {
                continue;
            }

            $matchedDepartmentId = $this->matchedDepartmentIdForSectionName($section->name, $departments);

            if ($matchedDepartmentId) {
                $preferredDepartmentIds[$section->id] = $matchedDepartmentId;
            }
        }

        return $preferredDepartmentIds;
    }

    private function matchedDepartmentIdForSectionName(string $sectionName, Collection $departments): ?int
    {
        $normalizedSection = $this->normalizeNodeLabel($sectionName);

        foreach ($departments as $department) {
            $normalizedDepartment = $this->normalizeNodeLabel($department->name);

            if ($normalizedDepartment === '' || $normalizedSection === '') {
                continue;
            }

            if (
                $normalizedDepartment === $normalizedSection
                || str_contains($normalizedSection, $normalizedDepartment)
                || str_contains($normalizedDepartment, $normalizedSection)
            ) {
                return (int) $department->id;
            }
        }

        return null;
    }

    private function normalizeNodeLabel(string $value): string
    {
        $normalized = preg_replace('/[^a-z0-9]+/i', ' ', strtolower(trim($value))) ?? '';

        return trim($normalized);
    }

    private function upsertRoutingNode(
        Organization $organization,
        HrOrganizationTierLevel $tierLevel,
        ?int $parentId,
        string $name,
        string $sourceType,
        string $sourceKey,
        int $order,
        array $extraMetadata = []
    ): array {
        $name = trim($name);

        $sourceMatches = HrOrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->routingNodes()
            ->where('source_type', $sourceType)
            ->where('source_key', $sourceKey)
            ->get();

        if ($sourceMatches->count() > 1) {
            throw new RuntimeException(
                "Multiple generated routing nodes already exist for source '{$sourceKey}' in organization {$organization->id}. Re-run with --fresh to rebuild cleanly."
            );
        }

        $fallbackMatches = HrOrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->routingNodes()
            ->where('tier_level_id', $tierLevel->id)
            ->where('name', $name)
            ->when(
                $parentId,
                fn ($query) => $query->where('parent_id', $parentId),
                fn ($query) => $query->whereNull('parent_id')
            )
            ->get();

        if ($fallbackMatches->count() > 1) {
            throw new RuntimeException(
                "Multiple routing nodes already exist for '{$name}' in organization {$organization->id}. Re-run with --fresh to rebuild cleanly."
            );
        }

        $node = $sourceMatches->first()
            ?? $fallbackMatches->first()
            ?? new HrOrganizationalUnit([
                'organization_id' => $organization->id,
                'unit_kind' => HrOrganizationalUnit::KIND_ROUTING_NODE,
            ]);

        $metadata = array_merge((array) $node->metadata, $extraMetadata, [
            'generated_by' => 'hr:build-routing-structure',
            'organogram_order' => $order,
        ]);

        $action = ! $node->exists ? 'created' : 'unchanged';

        if (
            $node->name !== $name
            || (int) $node->tier_level_id !== (int) $tierLevel->id
            || (int) ($node->parent_id ?? 0) !== (int) ($parentId ?? 0)
            || $node->type !== $tierLevel->name
            || $node->source_type !== $sourceType
            || $node->source_key !== $sourceKey
            || $node->metadata !== $metadata
        ) {
            $action = $node->exists ? 'updated' : 'created';
        }

        $node->forceFill([
            'parent_id' => $parentId,
            'tier_level_id' => $tierLevel->id,
            'name' => $name,
            'type' => $tierLevel->name,
            'unit_kind' => HrOrganizationalUnit::KIND_ROUTING_NODE,
            'source_type' => $sourceType,
            'source_key' => $sourceKey,
            'metadata' => $metadata,
        ])->save();

        return [$node, $action];
    }

    private function branchNodeForContext(array $context, array $branchNodesById, array $branchNodesByName): ?HrOrganizationalUnit
    {
        $branchId = $context['branch_id'] ?? null;

        if ($branchId && isset($branchNodesById[$branchId])) {
            return $branchNodesById[$branchId];
        }

        $normalizedName = $this->normalizeNodeLabel((string) ($context['branch_name'] ?? ''));

        return $normalizedName !== '' ? ($branchNodesByName[$normalizedName] ?? null) : null;
    }

    private function departmentNodeForContext(
        array $context,
        HrOrganizationalUnit $rootNode,
        array $branchNodesById,
        array $branchNodesByName,
        array $departmentNodesByIdKey,
        array $departmentNodesByNameKey
    ): ?HrOrganizationalUnit {
        $parentNode = $this->branchNodeForContext($context, $branchNodesById, $branchNodesByName) ?? $rootNode;
        $departmentId = $context['department_id'] ?? null;

        if ($departmentId) {
            $node = $departmentNodesByIdKey[$this->idKey((int) $parentNode->id, (int) $departmentId)] ?? null;

            if ($node) {
                return $node;
            }
        }

        $departmentName = $context['department_name'] ?? null;

        if (filled($departmentName)) {
            return $departmentNodesByNameKey[$this->labelKey((int) $parentNode->id, (string) $departmentName)] ?? null;
        }

        return null;
    }

    private function sectionNodeForContext(
        array $context,
        HrOrganizationalUnit $rootNode,
        array $branchNodesById,
        array $branchNodesByName,
        array $departmentNodesByIdKey,
        array $departmentNodesByNameKey,
        array $sectionNodesByIdKey,
        array $sectionNodesByNameKey
    ): ?HrOrganizationalUnit {
        $parentNode = $this->departmentNodeForContext(
            $context,
            $rootNode,
            $branchNodesById,
            $branchNodesByName,
            $departmentNodesByIdKey,
            $departmentNodesByNameKey
        );

        if (! $parentNode) {
            return null;
        }

        $sectionId = $context['section_id'] ?? null;

        if ($sectionId) {
            $node = $sectionNodesByIdKey[$this->idKey((int) $parentNode->id, (int) $sectionId)] ?? null;

            if ($node) {
                return $node;
            }
        }

        $sectionName = $context['section_name'] ?? null;

        if (filled($sectionName)) {
            return $sectionNodesByNameKey[$this->labelKey((int) $parentNode->id, (string) $sectionName)] ?? null;
        }

        return null;
    }

    private function cadreNodeForContext(
        array $context,
        HrOrganizationalUnit $rootNode,
        array $branchNodesById,
        array $branchNodesByName,
        array $departmentNodesByIdKey,
        array $departmentNodesByNameKey,
        array $sectionNodesByIdKey,
        array $sectionNodesByNameKey,
        array $cadreNodesByKey
    ): ?HrOrganizationalUnit {
        $anchorNode = $this->sectionNodeForContext(
            $context,
            $rootNode,
            $branchNodesById,
            $branchNodesByName,
            $departmentNodesByIdKey,
            $departmentNodesByNameKey,
            $sectionNodesByIdKey,
            $sectionNodesByNameKey
        );
        $anchorNode ??= $this->departmentNodeForContext(
            $context,
            $rootNode,
            $branchNodesById,
            $branchNodesByName,
            $departmentNodesByIdKey,
            $departmentNodesByNameKey
        );
        $anchorNode ??= $this->branchNodeForContext($context, $branchNodesById, $branchNodesByName);
        $anchorNode ??= $rootNode;

        $cadre = $context['staff_cadre'] ?? null;

        if (! filled($cadre)) {
            return null;
        }

        return $cadreNodesByKey[$this->normalizedParentValueKey((int) $anchorNode->id, (string) $cadre)] ?? null;
    }

    private function roleBandNodeForContext(
        array $context,
        HrOrganizationalUnit $rootNode,
        array $branchNodesById,
        array $branchNodesByName,
        array $departmentNodesByIdKey,
        array $departmentNodesByNameKey,
        array $sectionNodesByIdKey,
        array $sectionNodesByNameKey,
        array $cadreNodesByKey,
        array $roleBandNodesByKey
    ): ?HrOrganizationalUnit {
        $anchorNode = $this->cadreNodeForContext(
            $context,
            $rootNode,
            $branchNodesById,
            $branchNodesByName,
            $departmentNodesByIdKey,
            $departmentNodesByNameKey,
            $sectionNodesByIdKey,
            $sectionNodesByNameKey,
            $cadreNodesByKey
        );
        $anchorNode ??= $this->sectionNodeForContext(
            $context,
            $rootNode,
            $branchNodesById,
            $branchNodesByName,
            $departmentNodesByIdKey,
            $departmentNodesByNameKey,
            $sectionNodesByIdKey,
            $sectionNodesByNameKey
        );
        $anchorNode ??= $this->departmentNodeForContext(
            $context,
            $rootNode,
            $branchNodesById,
            $branchNodesByName,
            $departmentNodesByIdKey,
            $departmentNodesByNameKey
        );
        $anchorNode ??= $this->branchNodeForContext($context, $branchNodesById, $branchNodesByName);
        $anchorNode ??= $rootNode;

        $roleBand = $this->deriveRoleBand($context['title_name'] ?? null, $context['staff_cadre'] ?? null);

        if (! filled($roleBand)) {
            return null;
        }

        return $roleBandNodesByKey[$this->normalizedParentValueKey((int) $anchorNode->id, $roleBand)] ?? null;
    }

    private function generatedTargetNodeForContext(
        array $context,
        HrOrganizationalUnit $rootNode,
        array $branchNodesById,
        array $branchNodesByName,
        array $departmentNodesByIdKey,
        array $departmentNodesByNameKey,
        array $sectionNodesByIdKey,
        array $sectionNodesByNameKey,
        array $cadreNodesByKey,
        array $roleBandNodesByKey,
        array $titleNodesByKey
    ): ?HrOrganizationalUnit {
        $roleBandNode = $this->roleBandNodeForContext(
            $context,
            $rootNode,
            $branchNodesById,
            $branchNodesByName,
            $departmentNodesByIdKey,
            $departmentNodesByNameKey,
            $sectionNodesByIdKey,
            $sectionNodesByNameKey,
            $cadreNodesByKey,
            $roleBandNodesByKey
        );

        $title = $context['title_name'] ?? null;

        if ($roleBandNode && filled($title)) {
            $titleNode = $titleNodesByKey[$this->normalizedParentValueKey((int) $roleBandNode->id, (string) $title)] ?? null;

            if ($titleNode) {
                return $titleNode;
            }
        }

        if ($roleBandNode) {
            return $roleBandNode;
        }

        $cadreNode = $this->cadreNodeForContext(
            $context,
            $rootNode,
            $branchNodesById,
            $branchNodesByName,
            $departmentNodesByIdKey,
            $departmentNodesByNameKey,
            $sectionNodesByIdKey,
            $sectionNodesByNameKey,
            $cadreNodesByKey
        );

        if ($cadreNode) {
            return $cadreNode;
        }

        $sectionNode = $this->sectionNodeForContext(
            $context,
            $rootNode,
            $branchNodesById,
            $branchNodesByName,
            $departmentNodesByIdKey,
            $departmentNodesByNameKey,
            $sectionNodesByIdKey,
            $sectionNodesByNameKey
        );

        if ($sectionNode) {
            return $sectionNode;
        }

        $departmentNode = $this->departmentNodeForContext(
            $context,
            $rootNode,
            $branchNodesById,
            $branchNodesByName,
            $departmentNodesByIdKey,
            $departmentNodesByNameKey
        );

        if ($departmentNode) {
            return $departmentNode;
        }

        return $this->branchNodeForContext($context, $branchNodesById, $branchNodesByName) ?? $rootNode;
    }

    private function deriveRoleBand(?string $titleName, ?string $cadre): ?string
    {
        $value = strtolower(trim((string) ($titleName ?: $cadre ?: '')));

        if ($value === '') {
            return null;
        }

        if (preg_match('/director|chief|principal|president|chair|consultant|dean|commissioner|executive/', $value)) {
            return 'Leadership';
        }

        if (preg_match('/deputy|head|administrator|manager|superintendent|matron/', $value)) {
            return 'Management';
        }

        if (preg_match('/supervisor|coordinator|lead|in charge|senior/', $value)) {
            return 'Supervision';
        }

        if (preg_match('/officer|doctor|physician|clinician|nurse|midwife|pharmacist|technologist|technician|engineer|analyst|accountant|specialist/', $value)) {
            return 'Operations';
        }

        return 'General Staff';
    }

    private function idKey(int $parentId, int $childId): string
    {
        return $parentId.':'.$childId;
    }

    private function labelKey(int $parentId, string $label): string
    {
        return $parentId.':'.$this->normalizeNodeLabel($label);
    }

    private function normalizedParentValueKey(int $parentId, string $value): string
    {
        return $parentId.':'.$this->normalizeNodeLabel($value);
    }

    private function appendTreePreview(array &$summary, string $line): void
    {
        if (count($summary['tree_preview']) >= 40) {
            return;
        }

        $summary['tree_preview'][] = $line;
    }

    private function previewPath(HrOrganizationalUnit $node): string
    {
        $segments = [];
        $current = $node;
        $depth = 0;

        while ($current && $depth < 4) {
            array_unshift($segments, $current->name);
            $current = $current->parent;
            $depth++;
        }

        return implode(' -> ', $segments);
    }

    private function trackRoutingNodeAction(array &$summary, string $action, ?string $branchType = null): void
    {
        if ($action === 'created') {
            $summary['routing_nodes_created']++;
        } elseif ($action === 'updated') {
            $summary['routing_nodes_updated']++;
        }

        if ($action !== 'created') {
            return;
        }

        if ($branchType === 'branch') {
            $summary['branch_nodes_created']++;
        } elseif ($branchType === 'department') {
            $summary['department_nodes_created']++;
        } elseif ($branchType === 'section') {
            $summary['section_nodes_created']++;
        } elseif ($branchType === 'cadre') {
            $summary['cadre_nodes_created']++;
        } elseif ($branchType === 'role_band') {
            $summary['role_band_nodes_created']++;
        } elseif ($branchType === 'title') {
            $summary['title_nodes_created']++;
        }
    }
}
