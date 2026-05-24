<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\HrClientSpaceRoute;
use App\Models\HrOrganizationalUnit;
use App\Models\HrOrganizationTierLevel;
use App\Models\HrStaffRoutingEvent;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BuildHrRoutingStructure extends Command
{
    private const LAYOUT_VERSION = 'synthetic_organogram_v2';

    protected $signature = 'hr:build-routing-structure
        {--organization-id= : Existing HR organization id}
        {--business-id= : Main business id to map into HR}
        {--business-uuid= : Main business uuid to map into HR}
        {--levels= : Comma-separated ordered routing level names}
        {--auto-discover : Build routing structures for every business in the system}
        {--if-missing : Only build targets that do not already have routing nodes}
        {--upgrade-generated : Rebuild auto-generated routing structures that still use the older generated layout}
        {--fresh : Remove existing routing levels and routing nodes before rebuilding}
        {--dry-run : Show the intended changes without saving}
        {--force : Skip the fresh rebuild confirmation prompt}';

    protected $description = 'Build a synthetic HR routing organogram and assign staff into it';

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

            $levelNames = $this->resolveLevelNames();

            if ($needsUpgrade) {
                $label = $organization->name ?: "Organization {$organization->id}";
                $this->line("Upgrading {$label}: rebuilding generated routing structure into the synthetic organogram layout.");
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
        $this->line("Directorate nodes created: {$summary['directorate_nodes_created']}");
        $this->line("Department nodes created: {$summary['department_nodes_created']}");
        $this->line("Section nodes created: {$summary['section_nodes_created']}");
        $this->line("Unit nodes created: {$summary['unit_nodes_created']}");
        $this->line("Team nodes created: {$summary['team_nodes_created']}");
        $this->line("Desk nodes created: {$summary['desk_nodes_created']}");
        $this->line("Synthetic staff departments assigned: {$summary['synthetic_departments_assigned']}");
        $this->line("Staff assignments created from names: {$summary['staff_assignments_created_from_names']}");
        $this->line("Staff assignments refreshed from names: {$summary['staff_assignments_updated_from_names']}");
        $this->line("Staff routed into generated nodes: {$summary['staff_routed_to_generated_nodes']}");
        $this->line("Staff already aligned: {$summary['staff_already_aligned']}");
        $this->line("Staff skipped because they are inactive: {$summary['staff_skipped_inactive']}");
        $this->line("Staff skipped because they are already in client spaces: {$summary['staff_skipped_client_space']}");

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
            return str_starts_with((string) $node->source_type, 'generated_')
                && (string) data_get($node->metadata, 'generated_by', '') === 'hr:build-routing-structure';
        });

        if (! $allGenerated) {
            return false;
        }

        return $routingNodes->contains(function (HrOrganizationalUnit $node): bool {
            return (string) data_get($node->metadata, 'layout_strategy', '') !== self::LAYOUT_VERSION;
        });
    }

    private function resolveLevelNames(): array
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
            'Executive Office',
            'Directorate',
            'Department',
            'Section',
            'Unit',
            'Team',
            'Desk',
        ];
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
            'directorate_nodes_created' => 0,
            'department_nodes_created' => 0,
            'section_nodes_created' => 0,
            'unit_nodes_created' => 0,
            'team_nodes_created' => 0,
            'desk_nodes_created' => 0,
            'routing_nodes_cleared' => 0,
            'tier_levels_cleared' => 0,
            'synthetic_departments_assigned' => 0,
            'staff_assignments_created_from_names' => 0,
            'staff_assignments_updated_from_names' => 0,
            'staff_routed_to_generated_nodes' => 0,
            'staff_already_aligned' => 0,
            'staff_skipped_inactive' => 0,
            'staff_skipped_client_space' => 0,
            'tree_preview' => [],
        ];

        if ($fresh) {
            ['routing_nodes_cleared' => $summary['routing_nodes_cleared'], 'tier_levels_cleared' => $summary['tier_levels_cleared']] = $this->clearExistingStructure($organization);
        }

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
            [
                'layout_strategy' => self::LAYOUT_VERSION,
                'organization_created' => $organizationWasCreated,
            ]
        );
        $this->trackRoutingNodeAction($summary, $rootAction, 1);
        $this->appendTreePreview($summary, $rootNode->name);

        $leafNodes = [];
        $blueprint = $this->organogramBlueprint();

        foreach ($blueprint as $index => $branch) {
            $this->buildBlueprintBranch(
                $organization,
                $tierLevels,
                $rootNode,
                $branch,
                2,
                null,
                [$rootNode->name],
                $index + 1,
                $summary,
                $leafNodes
            );
        }

        if ($existingRoutingNodeCount === 0 || $fresh) {
            $assignmentSummary = $this->assignStaffToGeneratedNodes(
                $organization,
                $business,
                $leafNodes
            );

            $summary = array_merge($summary, $assignmentSummary);
        }

        return $summary;
    }

    private function buildBlueprintBranch(
        Organization $organization,
        array $tierLevels,
        HrOrganizationalUnit $parentNode,
        array $branch,
        int $depth,
        ?string $departmentName,
        array $path,
        int $order,
        array &$summary,
        array &$leafNodes
    ): void {
        $tierLevel = $tierLevels[$depth] ?? null;

        if (! $tierLevel) {
            return;
        }

        $name = trim((string) ($branch['name'] ?? ''));

        if ($name === '') {
            return;
        }

        $nextDepartmentName = $depth === 3 ? $name : $departmentName;
        $path[] = $name;

        [$node, $action] = $this->upsertRoutingNode(
            $organization,
            $tierLevel,
            $parentNode->id,
            $name,
            'generated_organogram_blueprint',
            'path:'.$this->pathKey($path),
            $order,
            [
                'layout_strategy' => self::LAYOUT_VERSION,
                'department_name' => $nextDepartmentName,
                'path' => $path,
            ]
        );

        $this->trackRoutingNodeAction($summary, $action, $depth);

        $children = collect($branch['children'] ?? [])
            ->filter(fn ($child): bool => is_array($child) && filled($child['name'] ?? null))
            ->values();

        if ($children->isEmpty()) {
            $leafNodes[] = [
                'node' => $node,
                'department_name' => $nextDepartmentName ?: $parentNode->name,
                'path' => $path,
            ];
            $this->appendTreePreview($summary, '- '.implode(' -> ', array_slice($path, 1)));

            return;
        }

        foreach ($children as $childIndex => $child) {
            $this->buildBlueprintBranch(
                $organization,
                $tierLevels,
                $node,
                $child,
                $depth + 1,
                $nextDepartmentName,
                $path,
                $childIndex + 1,
                $summary,
                $leafNodes
            );
        }
    }

    private function organogramBlueprint(): array
    {
        return [
            [
                'name' => 'Corporate Services',
                'children' => [
                    [
                        'name' => 'Human Resources',
                        'children' => [
                            ['name' => 'Employee Relations'],
                            [
                                'name' => 'Talent & Development',
                                'children' => [
                                    [
                                        'name' => 'Recruitment Unit',
                                        'children' => [
                                            [
                                                'name' => 'Interview Team',
                                                'children' => [
                                                    ['name' => 'Desk A'],
                                                    ['name' => 'Desk B'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Finance & Accounts',
                        'children' => [
                            [
                                'name' => 'Treasury',
                                'children' => [
                                    ['name' => 'Revenue Unit'],
                                    [
                                        'name' => 'Payments Unit',
                                        'children' => [
                                            ['name' => 'Disbursement Team'],
                                        ],
                                    ],
                                ],
                            ],
                            ['name' => 'Audit & Compliance'],
                        ],
                    ],
                    ['name' => 'Procurement & Logistics'],
                ],
            ],
            [
                'name' => 'Clinical Services',
                'children' => [
                    [
                        'name' => 'Outpatient Services',
                        'children' => [
                            [
                                'name' => 'Reception & Triage',
                                'children' => [
                                    [
                                        'name' => 'Front Desk Unit',
                                        'children' => [
                                            [
                                                'name' => 'Queue Team',
                                                'children' => [
                                                    ['name' => 'Counter Desk 1'],
                                                    ['name' => 'Counter Desk 2'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'name' => 'Consultation',
                                'children' => [
                                    [
                                        'name' => 'General Practice Unit',
                                        'children' => [
                                            ['name' => 'Doctor Team'],
                                        ],
                                    ],
                                    ['name' => 'Special Clinics'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Inpatient Services',
                        'children' => [
                            [
                                'name' => 'Ward Operations',
                                'children' => [
                                    [
                                        'name' => 'Nursing Unit',
                                        'children' => [
                                            [
                                                'name' => 'Shift Team Alpha',
                                                'children' => [
                                                    ['name' => 'Station Desk 1'],
                                                    ['name' => 'Station Desk 2'],
                                                ],
                                            ],
                                            ['name' => 'Shift Team Beta'],
                                        ],
                                    ],
                                ],
                            ],
                            ['name' => 'Patient Support'],
                        ],
                    ],
                    [
                        'name' => 'Diagnostic Services',
                        'children' => [
                            [
                                'name' => 'Laboratory',
                                'children' => [
                                    ['name' => 'Lab Operations Unit'],
                                ],
                            ],
                            ['name' => 'Imaging'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Operations & Support',
                'children' => [
                    [
                        'name' => 'ICT & Records',
                        'children' => [
                            ['name' => 'Systems Support'],
                            [
                                'name' => 'Health Records',
                                'children' => [
                                    ['name' => 'Records Unit'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Facilities & Security',
                        'children' => [
                            [
                                'name' => 'Estate Management',
                                'children' => [
                                    [
                                        'name' => 'Maintenance Unit',
                                        'children' => [
                                            [
                                                'name' => 'Utilities Team',
                                                'children' => [
                                                    ['name' => 'Control Desk'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            ['name' => 'Security Coordination'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function assignStaffToGeneratedNodes(
        Organization $organization,
        ?Business $business,
        array $leafNodes
    ): array {
        $contexts = $this->staffContextsForTarget($organization, $business);
        $syncSummary = $this->synchronizeStaffAssignmentsFromContexts($organization, $contexts);
        $summary = [
            'synthetic_departments_assigned' => 0,
            'staff_assignments_created_from_names' => $syncSummary['created'],
            'staff_assignments_updated_from_names' => $syncSummary['updated'],
            'staff_routed_to_generated_nodes' => 0,
            'staff_already_aligned' => 0,
            'staff_skipped_inactive' => 0,
            'staff_skipped_client_space' => 0,
        ];

        if (empty($leafNodes)) {
            return $summary;
        }

        usort($leafNodes, fn (array $left, array $right): int => strcmp($this->pathKey($left['path']), $this->pathKey($right['path'])));
        $focusLeaf = $leafNodes[0];
        $focusPathNodes = $this->seedRoutePathForLeaf($focusLeaf['node']);

        if ($focusPathNodes->isEmpty()) {
            $focusPathNodes = collect([$focusLeaf['node']]);
        }

        if ($focusPathNodes->count() > 1) {
            $focusPathNodes = $focusPathNodes->slice(1)->values();
        }

        $focusLeafNode = $focusPathNodes->last();
        $focusDepartmentName = (string) ($focusLeaf['department_name'] ?: 'General Administration');
        $upstreamTargets = $focusPathNodes
            ->slice(0, max(0, $focusPathNodes->count() - 1))
            ->values();
        $assignments = StaffAssignment::query()
            ->where('organization_id', $organization->id)
            ->orderBy('staff_name')
            ->get();
        $now = now();
        $eligibleAssignments = collect();

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

            $eligibleAssignments->push($assignment);
        }

        if ($eligibleAssignments->isEmpty()) {
            return $summary;
        }

        $placementTargets = collect();

        foreach ($upstreamTargets as $node) {
            $departmentName = $this->seedDepartmentNameForNode($node, $focusDepartmentName);

            for ($slot = 0; $slot < 2; $slot++) {
                $placementTargets->push([
                    'node' => $node,
                    'department_name' => $departmentName,
                ]);
            }
        }

        while ($placementTargets->count() < $eligibleAssignments->count()) {
            $placementTargets->push([
                'node' => $focusLeafNode,
                'department_name' => $this->seedDepartmentNameForNode($focusLeafNode, $focusDepartmentName),
            ]);
        }

        foreach ($eligibleAssignments->values() as $index => $assignment) {
            $target = $placementTargets[$index] ?? [
                'node' => $focusLeafNode,
                'department_name' => $this->seedDepartmentNameForNode($focusLeafNode, $focusDepartmentName),
            ];
            $targetNode = $target['node'];
            $departmentName = (string) $target['department_name'];

            if (
                (int) $assignment->organizational_unit_id === (int) $targetNode->id
                && $assignment->status === 'pending_routing'
                && trim((string) $assignment->staff_department) === $departmentName
            ) {
                $summary['staff_already_aligned']++;
                continue;
            }

            $fromUnitId = $assignment->organizational_unit_id;
            $fromStatus = $assignment->status;

            $assignment->forceFill([
                'organizational_unit_id' => $targetNode->id,
                'staff_department' => $departmentName,
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

            $summary['synthetic_departments_assigned']++;
            $summary['staff_routed_to_generated_nodes']++;
        }

        return $summary;
    }

    private function seedRoutePathForLeaf(HrOrganizationalUnit $leafNode): Collection
    {
        $pathNodes = [];
        $cursor = $leafNode->loadMissing('parent');

        while ($cursor) {
            $pathNodes[] = $cursor;
            $cursor = $cursor->parent_id
                ? HrOrganizationalUnit::query()->with('parent')->find($cursor->parent_id)
                : null;
        }

        return collect(array_reverse($pathNodes))
            ->filter(fn ($node): bool => $node instanceof HrOrganizationalUnit)
            ->values();
    }

    private function seedDepartmentNameForNode(HrOrganizationalUnit $node, string $fallback): string
    {
        $departmentName = trim((string) data_get($node->metadata, 'department_name', ''));

        return $departmentName !== '' ? $departmentName : $fallback;
    }

    private function staffContextsForTarget(Organization $organization, ?Business $business): Collection
    {
        $contexts = collect();

        if ($business) {
            $contexts = User::query()
                ->where('business_id', $business->id)
                ->orderBy('name')
                ->get(['uuid', 'staff_uuid', 'name', 'status', 'deactivated_at'])
                ->mapWithKeys(fn (User $user): array => [
                    ($user->staff_uuid ?: $user->uuid) => [
                        'name' => $user->name ?: ($user->staff_uuid ?: $user->uuid),
                        'is_active' => $this->userIsActive($user),
                    ],
                ]);
        }

        $assignmentContexts = StaffAssignment::query()
            ->where('organization_id', $organization->id)
            ->orderBy('staff_name')
            ->get(['staff_uuid', 'staff_name', 'status'])
            ->mapWithKeys(fn (StaffAssignment $assignment): array => [
                $assignment->staff_uuid => [
                    'name' => $assignment->staff_name ?: $assignment->staff_uuid,
                    'is_active' => $assignment->status !== 'inactive',
                ],
            ]);

        return $contexts->union($assignmentContexts);
    }

    private function synchronizeStaffAssignmentsFromContexts(Organization $organization, Collection $contexts): array
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

    private function userIsActive(User $user): bool
    {
        return $user->deactivated_at === null
            && ! in_array((string) $user->status, ['inactive', 'disabled'], true);
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

        if ($tierLevel->name !== $name || (int) $tierLevel->level_order !== (int) $order) {
            $action = $tierLevel->exists ? 'updated' : 'created';
        }

        $tierLevel->forceFill([
            'name' => $name,
            'level_order' => $order,
        ])->save();

        return [$tierLevel, $action];
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
            'layout_strategy' => self::LAYOUT_VERSION,
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

    private function appendTreePreview(array &$summary, string $line): void
    {
        if (count($summary['tree_preview']) >= 40) {
            return;
        }

        $summary['tree_preview'][] = $line;
    }

    private function trackRoutingNodeAction(array &$summary, string $action, int $depth): void
    {
        if ($action === 'created') {
            $summary['routing_nodes_created']++;
        } elseif ($action === 'updated') {
            $summary['routing_nodes_updated']++;
        }

        if ($action !== 'created') {
            return;
        }

        if ($depth === 2) {
            $summary['directorate_nodes_created']++;
        } elseif ($depth === 3) {
            $summary['department_nodes_created']++;
        } elseif ($depth === 4) {
            $summary['section_nodes_created']++;
        } elseif ($depth === 5) {
            $summary['unit_nodes_created']++;
        } elseif ($depth === 6) {
            $summary['team_nodes_created']++;
        } elseif ($depth >= 7) {
            $summary['desk_nodes_created']++;
        }
    }

    private function pathKey(array $segments): string
    {
        return collect($segments)
            ->map(fn ($segment): string => $this->normalizeNodeLabel((string) $segment))
            ->filter()
            ->implode('>');
    }

    private function normalizeNodeLabel(string $value): string
    {
        $normalized = preg_replace('/[^a-z0-9]+/i', ' ', strtolower(trim($value))) ?? '';

        return trim($normalized);
    }
}
