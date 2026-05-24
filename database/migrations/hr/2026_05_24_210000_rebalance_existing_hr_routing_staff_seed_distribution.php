<?php

use App\Models\HrOrganizationalUnit;
use App\Models\HrStaffRoutingEvent;
use App\Models\Organization;
use App\Models\StaffAssignment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;

return new class extends Migration
{
    public function up(): void
    {
        Organization::query()
            ->orderBy('id')
            ->each(function (Organization $organization): void {
                $leafNodes = HrOrganizationalUnit::query()
                    ->where('organization_id', $organization->id)
                    ->routingNodes()
                    ->lowestRoutingNodes()
                    ->get()
                    ->map(function (HrOrganizationalUnit $node): array {
                        return [
                            'node' => $node,
                            'path' => $this->pathForNode($node),
                            'department_name' => (string) data_get($node->metadata, 'department_name', ''),
                        ];
                    })
                    ->sortBy(fn (array $leaf): string => $this->pathKey($leaf['path']))
                    ->values();

                if ($leafNodes->isEmpty()) {
                    return;
                }

                $focusLeaf = $leafNodes->first();
                $focusPathNodes = $this->pathNodesForLeaf($focusLeaf['node']);

                if ($focusPathNodes->isEmpty()) {
                    $focusPathNodes = collect([$focusLeaf['node']]);
                }

                /** @var HrOrganizationalUnit $focusLeafNode */
                $focusLeafNode = $focusPathNodes->last();
                $focusDepartmentName = (string) ($focusLeaf['department_name'] ?: 'General Administration');
                $upstreamTargets = $focusPathNodes
                    ->slice(0, max(0, $focusPathNodes->count() - 1))
                    ->values();

                $eligibleAssignments = StaffAssignment::query()
                    ->where('organization_id', $organization->id)
                    ->orderBy('staff_name')
                    ->with(['organizationalUnit' => fn ($query) => $query->withTrashed()])
                    ->get()
                    ->filter(function (StaffAssignment $assignment): bool {
                        if ($assignment->status === 'inactive') {
                            return false;
                        }

                        return ! $assignment->organizationalUnit?->isClientSpace();
                    })
                    ->values();

                if ($eligibleAssignments->isEmpty()) {
                    return;
                }

                $placementTargets = collect();

                foreach ($upstreamTargets as $node) {
                    $departmentName = $this->departmentNameForNode($node, $focusDepartmentName);

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
                        'department_name' => $this->departmentNameForNode($focusLeafNode, $focusDepartmentName),
                    ]);
                }

                $now = now();

                foreach ($eligibleAssignments as $index => $assignment) {
                    $target = $placementTargets[$index] ?? [
                        'node' => $focusLeafNode,
                        'department_name' => $this->departmentNameForNode($focusLeafNode, $focusDepartmentName),
                    ];
                    /** @var HrOrganizationalUnit $targetNode */
                    $targetNode = $target['node'];
                    $departmentName = (string) $target['department_name'];

                    if (
                        (int) $assignment->organizational_unit_id === (int) $targetNode->id
                        && $assignment->status === 'pending_routing'
                        && trim((string) $assignment->staff_department) === $departmentName
                    ) {
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
                }
            });
    }

    public function down(): void
    {
        // One-time data rebalance for existing seeded routing structures.
    }

    private function pathForNode(HrOrganizationalUnit $node): array
    {
        $metadataPath = data_get($node->metadata, 'path', []);

        if (is_array($metadataPath) && ! empty($metadataPath)) {
            return array_values(array_filter(array_map('strval', $metadataPath)));
        }

        return $this->pathNodesForLeaf($node)
            ->pluck('name')
            ->map(fn ($name): string => (string) $name)
            ->all();
    }

    private function pathNodesForLeaf(HrOrganizationalUnit $leafNode): Collection
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

    private function departmentNameForNode(HrOrganizationalUnit $node, string $fallback): string
    {
        $departmentName = trim((string) data_get($node->metadata, 'department_name', ''));

        return $departmentName !== '' ? $departmentName : $fallback;
    }

    private function pathKey(array $path): string
    {
        return implode(' > ', array_map(fn ($segment): string => trim((string) $segment), $path));
    }
};
