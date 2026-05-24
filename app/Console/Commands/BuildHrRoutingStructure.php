<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\HrClientSpaceRoute;
use App\Models\HrOrganizationalUnit;
use App\Models\HrOrganizationTierLevel;
use App\Models\Organization;
use App\Models\Section;
use App\Models\StaffAssignment;
use Illuminate\Console\Command;
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

        if ($fresh) {
            $this->line("Routing nodes cleared first: {$summary['routing_nodes_cleared']}");
            $this->line("Tier levels cleared first: {$summary['tier_levels_cleared']}");
        }

        if (! empty($summary['node_chain'])) {
            $this->line('Organogram: '.implode(' -> ', $summary['node_chain']));
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
            $levels->push('Heads of Departments');
        }

        if ($business && Section::query()->where('business_id', $business->id)->exists()) {
            $levels->push('Sectional Heads');
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
            'routing_nodes_cleared' => 0,
            'tier_levels_cleared' => 0,
            'node_chain' => [],
        ];

        if ($fresh) {
            ['routing_nodes_cleared' => $summary['routing_nodes_cleared'], 'tier_levels_cleared' => $summary['tier_levels_cleared']] = $this->clearExistingStructure($organization);
        }

        $parentId = null;

        foreach ($levelNames as $index => $levelName) {
            $order = $index + 1;
            [$tierLevel, $tierLevelAction] = $this->upsertTierLevel($organization, $levelName, $order);

            if ($tierLevelAction === 'created') {
                $summary['tier_levels_created']++;
            } elseif ($tierLevelAction === 'updated') {
                $summary['tier_levels_updated']++;
            }

            [$node, $nodeAction] = $this->upsertRoutingNode($organization, $tierLevel, $parentId, $order);

            if ($nodeAction === 'created') {
                $summary['routing_nodes_created']++;
            } elseif ($nodeAction === 'updated') {
                $summary['routing_nodes_updated']++;
            }

            $summary['node_chain'][] = $node->name;
            $parentId = $node->id;
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

    private function upsertRoutingNode(
        Organization $organization,
        HrOrganizationTierLevel $tierLevel,
        ?int $parentId,
        int $order
    ): array {
        $matches = HrOrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->routingNodes()
            ->where('tier_level_id', $tierLevel->id)
            ->where('name', $tierLevel->name)
            ->get();

        if ($matches->count() > 1) {
            throw new RuntimeException(
                "Multiple routing nodes already exist for level '{$tierLevel->name}' in organization {$organization->id}. Re-run with --fresh to rebuild cleanly."
            );
        }

        $node = $matches->first() ?? new HrOrganizationalUnit([
            'organization_id' => $organization->id,
            'unit_kind' => HrOrganizationalUnit::KIND_ROUTING_NODE,
        ]);

        $metadata = array_merge((array) $node->metadata, [
            'generated_by' => 'hr:build-routing-structure',
            'organogram_order' => $order,
        ]);

        $action = ! $node->exists ? 'created' : 'unchanged';

        if (
            $node->name !== $tierLevel->name
            || (int) $node->tier_level_id !== (int) $tierLevel->id
            || (int) ($node->parent_id ?? 0) !== (int) ($parentId ?? 0)
            || $node->type !== $tierLevel->name
            || $node->source_type !== 'generated_organogram'
            || $node->source_key !== "tier_level:{$tierLevel->id}"
            || $node->metadata !== $metadata
        ) {
            $action = $node->exists ? 'updated' : 'created';
        }

        $node->forceFill([
            'parent_id' => $parentId,
            'tier_level_id' => $tierLevel->id,
            'name' => $tierLevel->name,
            'type' => $tierLevel->name,
            'unit_kind' => HrOrganizationalUnit::KIND_ROUTING_NODE,
            'source_type' => 'generated_organogram',
            'source_key' => "tier_level:{$tierLevel->id}",
            'metadata' => $metadata,
        ])->save();

        return [$node, $action];
    }
}
