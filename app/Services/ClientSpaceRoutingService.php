<?php

namespace App\Services;

use App\Models\HrClientSpaceRoute;
use App\Models\HrOrganizationalUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientSpaceRoutingService
{
    public function syncRoutes(HrOrganizationalUnit $clientSpace, array $routingUnitIds, ?int $primaryRoutingUnitId = null): void
    {
        if (! $clientSpace->isClientSpace()) {
            throw ValidationException::withMessages([
                'selectedPlacementClientSpaceId' => 'Only client spaces can have shared routing paths.',
            ]);
        }

        $routingUnitIds = collect($routingUnitIds)
            ->prepend($primaryRoutingUnitId)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if (
            $primaryRoutingUnitId
            && ! HrOrganizationalUnit::where('organization_id', $clientSpace->organization_id)
                ->lowestRoutingNodes()
                ->whereKey((int) $primaryRoutingUnitId)
            ->exists()
        ) {
            throw ValidationException::withMessages([
                'primaryRoutingUnitId' => 'Choose the lowest possible route in the routing structure as the primary route.',
            ]);
        }

        if (
            $primaryRoutingUnitId
            && HrClientSpaceRoute::query()
                ->where('organization_id', $clientSpace->organization_id)
                ->where('routing_unit_id', (int) $primaryRoutingUnitId)
                ->where('is_primary', true)
                ->where('client_space_unit_id', '!=', $clientSpace->id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'primaryRoutingUnitId' => 'This route is already the primary route for another client space. Add it as a unit instead.',
            ]);
        }

        $routingUnits = HrOrganizationalUnit::where('organization_id', $clientSpace->organization_id)
            ->lowestRoutingNodes()
            ->whereIn('id', $routingUnitIds)
            ->pluck('id');

        if ($routingUnits->count() !== $routingUnitIds->count()) {
            throw ValidationException::withMessages([
                'linkedRoutingUnitIds' => 'Add Units can only use the lowest routing nodes from the current organization.',
            ]);
        }

        $primaryRoutingUnitId = $primaryRoutingUnitId ? (int) $primaryRoutingUnitId : null;

        if ($routingUnitIds->isNotEmpty() && ! $primaryRoutingUnitId) {
            throw ValidationException::withMessages([
                'primaryRoutingUnitId' => 'Choose which selected unit is the primary route before saving.',
            ]);
        }

        DB::transaction(function () use ($clientSpace, $routingUnitIds, $primaryRoutingUnitId): void {
            HrClientSpaceRoute::where('client_space_unit_id', $clientSpace->id)
                ->when(
                    $routingUnitIds->isNotEmpty(),
                    fn ($query) => $query->whereNotIn('routing_unit_id', $routingUnitIds),
                    fn ($query) => $query
                )
                ->delete();

            if ($routingUnitIds->isEmpty()) {
                $clientSpace->forceFill(['parent_id' => null])->save();

                return;
            }

            HrClientSpaceRoute::where('client_space_unit_id', $clientSpace->id)->update(['is_primary' => false]);

            foreach ($routingUnitIds as $routingUnitId) {
                HrClientSpaceRoute::updateOrCreate(
                    [
                        'client_space_unit_id' => $clientSpace->id,
                        'routing_unit_id' => $routingUnitId,
                    ],
                    [
                        'organization_id' => $clientSpace->organization_id,
                        'is_primary' => $routingUnitId === $primaryRoutingUnitId,
                    ]
                );
            }

            $clientSpace->forceFill(['parent_id' => $primaryRoutingUnitId])->save();
        });

        $clientSpace->unsetRelation('clientSpaceRoutes');
        $clientSpace->unsetRelation('routingParents');
        $clientSpace->unsetRelation('primaryRoutingLink');
        $clientSpace->unsetRelation('parent');
    }

    public function ensurePrimaryRouteConsistency(HrOrganizationalUnit $clientSpace): ?int
    {
        if (! $clientSpace->isClientSpace()) {
            return null;
        }

        return DB::transaction(function () use ($clientSpace): ?int {
            if (
                $clientSpace->parent_id
                && HrOrganizationalUnit::where('organization_id', $clientSpace->organization_id)
                    ->lowestRoutingNodes()
                    ->whereKey($clientSpace->parent_id)
                    ->exists()
            ) {
                HrClientSpaceRoute::updateOrCreate(
                    [
                        'client_space_unit_id' => $clientSpace->id,
                        'routing_unit_id' => $clientSpace->parent_id,
                    ],
                    [
                        'organization_id' => $clientSpace->organization_id,
                        'is_primary' => true,
                    ]
                );
            }

            HrClientSpaceRoute::where('client_space_unit_id', $clientSpace->id)
                ->whereDoesntHave('routingUnit', fn ($query) => $query
                    ->where('organization_id', $clientSpace->organization_id)
                    ->routingNodes())
                ->delete();

            $routes = HrClientSpaceRoute::where('client_space_unit_id', $clientSpace->id)
                ->whereHas('routingUnit', fn ($query) => $query
                    ->where('organization_id', $clientSpace->organization_id)
                    ->routingNodes())
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->get(['id', 'routing_unit_id', 'is_primary']);

            if ($routes->isEmpty()) {
                if ($clientSpace->parent_id !== null) {
                    $clientSpace->forceFill(['parent_id' => null])->save();
                }

                return null;
            }

            $lowestRouteIds = HrOrganizationalUnit::where('organization_id', $clientSpace->organization_id)
                ->lowestRoutingNodes()
                ->whereIn('id', $routes->pluck('routing_unit_id'))
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values();

            $preferredRoute = $routes
                ->first(fn (HrClientSpaceRoute $route): bool => (int) $route->routing_unit_id === (int) $clientSpace->parent_id
                    && $lowestRouteIds->contains((int) $route->routing_unit_id))
                ?? $routes->first(fn (HrClientSpaceRoute $route): bool => $route->is_primary && $lowestRouteIds->contains((int) $route->routing_unit_id))
                ?? $routes->first(fn (HrClientSpaceRoute $route): bool => $lowestRouteIds->contains((int) $route->routing_unit_id));

            if (! $preferredRoute) {
                if ($clientSpace->parent_id !== null) {
                    $clientSpace->forceFill(['parent_id' => null])->save();
                }

                HrClientSpaceRoute::where('client_space_unit_id', $clientSpace->id)->update(['is_primary' => false]);

                $clientSpace->unsetRelation('clientSpaceRoutes');
                $clientSpace->unsetRelation('routingParents');
                $clientSpace->unsetRelation('primaryRoutingLink');
                $clientSpace->unsetRelation('parent');

                return null;
            }

            HrClientSpaceRoute::where('client_space_unit_id', $clientSpace->id)->update(['is_primary' => false]);
            HrClientSpaceRoute::whereKey($preferredRoute->id)->update(['is_primary' => true]);

            if ((int) $clientSpace->parent_id !== (int) $preferredRoute->routing_unit_id) {
                $clientSpace->forceFill(['parent_id' => $preferredRoute->routing_unit_id])->save();
            }

            $clientSpace->unsetRelation('clientSpaceRoutes');
            $clientSpace->unsetRelation('routingParents');
            $clientSpace->unsetRelation('primaryRoutingLink');
            $clientSpace->unsetRelation('parent');

            return (int) $preferredRoute->routing_unit_id;
        });
    }

    public function promoteRemainingRoutes(Collection $clientSpaceIds): void
    {
        $clientSpaceIds
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->each(function (int $clientSpaceId): void {
                $clientSpace = HrOrganizationalUnit::find($clientSpaceId);

                if (! $clientSpace?->isClientSpace()) {
                    return;
                }

                $this->ensurePrimaryRouteConsistency($clientSpace);
            });
    }
}
