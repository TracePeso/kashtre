<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Department;
use App\Models\HrClientSpaceRoute;
use App\Models\HrOrganizationalUnit;
use App\Models\HrOrganizationTierLevel;
use App\Models\Organization;
use App\Models\Section;
use App\Models\StaffAssignment;
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
        {--fresh : Remove existing routing levels and routing nodes before rebuilding}
        {--dry-run : Show the intended changes without saving}
        {--force : Skip the fresh rebuild confirmation prompt}';

    protected $description = 'Build a clean HR routing structure organogram from seeded business data';

    public function handle(): int
    {
        [$business, $organization, $organizationWasCreated] = $this->resolveTarget();

        if (! $organization) {
            $this->error('Choose a target with --organization-id, --business-id, or --business-uuid.');

            return self::FAILURE;
        }

        $levelNames = $this->resolveLevelNames($business);

        if (empty($levelNames)) {
            $this->error('No routing levels were resolved for this target.');

            return self::FAILURE;
        }

        if (count($levelNames) > 3) {
            $this->warn('Only the first three levels are populated from seeded business data. Extra levels will be created but left without generated nodes.');
        }

        $fresh = (bool) $this->option('fresh');
        $dryRun = (bool) $this->option('dry-run');

        if ($fresh && ! $dryRun && ! $this->option('force')) {
            $label = $organization->name ?: "Organization {$organization->id}";

            if (! $this->confirm("Rebuild the HR routing structure for {$label}? Existing routing nodes and levels will be replaced.")) {
                $this->warn('Cancelled.');

                return self::SUCCESS;
            }
        }

        $summary = null;

        try {
            DB::beginTransaction();
            $summary = $this->buildStructure($organization, $business, $levelNames, $fresh, $organizationWasCreated);

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->info($dryRun ? 'Dry run complete. No changes were saved.' : 'HR routing structure build complete.');
        $this->line("Organization: {$summary['organization_name']} (#{$summary['organization_id']})");

        if ($summary['business_name']) {
            $this->line("Business: {$summary['business_name']} (#{$summary['business_id']})");
        }

        $this->line('Levels: '.implode(' -> ', $summary['level_names']));
        $this->line("Tier levels created: {$summary['tier_levels_created']}");
        $this->line("Tier levels updated: {$summary['tier_levels_updated']}");
        $this->line("Routing nodes created: {$summary['routing_nodes_created']}");
        $this->line("Routing nodes updated: {$summary['routing_nodes_updated']}");
        $this->line("Department branches created: {$summary['department_nodes_created']}");
        $this->line("Section branches created: {$summary['section_nodes_created']}");
        $this->line("Sections attached directly to root: {$summary['sections_attached_to_root']}");

        if ($fresh) {
            $this->line("Routing nodes cleared first: {$summary['routing_nodes_cleared']}");
            $this->line("Tier levels cleared first: {$summary['tier_levels_cleared']}");
        }

        if (! empty($summary['tree_preview'])) {
            $this->line('Organogram Preview:');
            foreach ($summary['tree_preview'] as $line) {
                $this->line("  {$line}");
            }
        }

        return self::SUCCESS;
    }

    private function resolveTarget(): array
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

            return [$business, $organization, false];
        }

        $business = null;

        if ($businessId) {
            $business = Business::query()->find($businessId);
        } elseif ($businessUuid) {
            $business = Business::query()->where('uuid', $businessUuid)->first();
        }

        if (! $business) {
            return [null, null, false];
        }

        $organization = Organization::query()->firstOrCreate(
            ['external_business_uuid' => $business->uuid],
            ['name' => $business->name]
        );

        if ($organization->name !== $business->name) {
            $organization->forceFill(['name' => $business->name])->save();
        }

        return [$business, $organization, $organization->wasRecentlyCreated];
    }

    private function resolveLevelNames(?Business $business): array
    {
        $provided = $this->option('levels');

        if (filled($provided)) {
            return collect(explode(',', (string) $provided))
                ->map(fn (string $name): string => trim($name))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $levels = collect(['Directorate']);

        if ($business && $business->departments()->exists()) {
            $levels->push('Department');
        }

        if ($business && Section::query()->where('business_id', $business->id)->exists()) {
            $levels->push('Section');
        }

        return $levels->unique()->values()->all();
    }

    private function buildStructure(
        Organization $organization,
        ?Business $business,
        array $levelNames,
        bool $fresh,
        bool $organizationWasCreated
    ): array {
        $summary = [
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'business_id' => $business?->id,
            'business_name' => $business?->name,
            'organization_created' => $organizationWasCreated,
            'level_names' => $levelNames,
            'tier_levels_created' => 0,
            'tier_levels_updated' => 0,
            'routing_nodes_created' => 0,
            'routing_nodes_updated' => 0,
            'department_nodes_created' => 0,
            'section_nodes_created' => 0,
            'sections_attached_to_root' => 0,
            'routing_nodes_cleared' => 0,
            'tier_levels_cleared' => 0,
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
            ['organization_created' => $organizationWasCreated]
        );
        $this->trackRoutingNodeAction($summary, $rootAction);
        $summary['tree_preview'][] = $rootNode->name;

        $departmentTierLevel = $tierLevels[2] ?? null;
        $sectionTierLevel = $tierLevels[3] ?? null;
        $departments = $this->departmentsForBusiness($business);
        $sections = $this->sectionsForBusiness($business);
        $departmentNodesById = [];

        if ($departmentTierLevel && $departments->isNotEmpty()) {
            foreach ($departments->values() as $index => $department) {
                [$departmentNode, $departmentAction] = $this->upsertRoutingNode(
                    $organization,
                    $departmentTierLevel,
                    $rootNode->id,
                    $department->name,
                    'generated_department_branch',
                    "department:{$department->id}",
                    $index + 1,
                    [
                        'business_id' => $department->business_id,
                        'department_id' => $department->id,
                        'department_uuid' => $department->uuid,
                    ]
                );

                $departmentNodesById[$department->id] = $departmentNode;
                $this->trackRoutingNodeAction($summary, $departmentAction, 'department');
                $summary['tree_preview'][] = "- {$departmentNode->name}";
            }
        }

        $sectionNodeTier = $sectionTierLevel;

        if (! $sectionNodeTier && empty($departmentNodesById)) {
            $sectionNodeTier = $departmentTierLevel;
        }

        if ($sectionNodeTier && $sections->isNotEmpty()) {
            $preferredDepartmentIds = $this->preferredDepartmentIdsForSections($business, $departments, $sections);

            foreach ($sections->values() as $index => $section) {
                $parentNode = $rootNode;

                if (
                    isset($preferredDepartmentIds[$section->id])
                    && isset($departmentNodesById[$preferredDepartmentIds[$section->id]])
                ) {
                    $parentNode = $departmentNodesById[$preferredDepartmentIds[$section->id]];
                } else {
                    $summary['sections_attached_to_root']++;
                }

                [$sectionNode, $sectionAction] = $this->upsertRoutingNode(
                    $organization,
                    $sectionNodeTier,
                    $parentNode->id,
                    $section->name,
                    'generated_section_branch',
                    "section:{$section->id}",
                    $index + 1,
                    [
                        'business_id' => $section->business_id,
                        'section_id' => $section->id,
                        'section_uuid' => $section->uuid,
                        'attached_to_root' => $parentNode->is($rootNode),
                    ]
                );

                $this->trackRoutingNodeAction($summary, $sectionAction, 'section');
                $summary['tree_preview'][] = $parentNode->is($rootNode)
                    ? "- {$sectionNode->name}"
                    : "- {$parentNode->name} -> {$sectionNode->name}";
            }
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

    private function trackRoutingNodeAction(array &$summary, string $action, ?string $branchType = null): void
    {
        if ($action === 'created') {
            $summary['routing_nodes_created']++;
        } elseif ($action === 'updated') {
            $summary['routing_nodes_updated']++;
        }

        if ($branchType === 'department' && $action === 'created') {
            $summary['department_nodes_created']++;
        }

        if ($branchType === 'section' && $action === 'created') {
            $summary['section_nodes_created']++;
        }
    }
}
