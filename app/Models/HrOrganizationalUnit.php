<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use RuntimeException;

class HrOrganizationalUnit extends Model
{
    use SoftDeletes;

    public const KIND_ROUTING_NODE = 'routing_node';
    public const KIND_CLIENT_SPACE = 'client_space';

    protected $fillable = [
        'uuid', 'organization_id', 'parent_id', 'tier_level_id', 'name', 'type', 'unit_kind',
        'head_staff_uuid', 'head_name', 'source_type', 'source_key', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
        });

        static::saving(function (self $model): void {
            $model->assertNoCycleInParentChain();
        });
    }

    private function assertNoCycleInParentChain(): void
    {
        if ($this->parent_id === null) {
            return;
        }

        if ($this->exists && (int) $this->parent_id === (int) $this->getKey()) {
            throw new RuntimeException('An organizational unit cannot be its own parent.');
        }

        $visited = [];
        $currentId = (int) $this->parent_id;
        $selfId = $this->exists ? (int) $this->getKey() : null;

        for ($depth = 0; $depth < 20; $depth++) {
            if ($selfId !== null && $currentId === $selfId) {
                throw new RuntimeException('Cycle detected in organizational unit parent chain.');
            }

            if (isset($visited[$currentId])) {
                throw new RuntimeException('Cycle detected in organizational unit parent chain.');
            }

            $visited[$currentId] = true;

            $parentRow = static::query()->whereKey($currentId)->first(['id', 'parent_id']);

            if (! $parentRow || $parentRow->parent_id === null) {
                return;
            }

            $currentId = (int) $parentRow->parent_id;
        }

        throw new RuntimeException('Organizational unit parent chain exceeds the maximum supported depth.');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function tierLevel()
    {
        return $this->belongsTo(HrOrganizationTierLevel::class, 'tier_level_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function childrenRecursive()
    {
        return $this->children()
            ->with([
                'tierLevel',
                'childrenRecursive',
                'linkedClientSpaces' => fn ($query) => $query
                    ->with('routingParents')
                    ->withCount([
                        'staffAssignments as active_staff_count' => fn ($staffQuery) => $staffQuery->where('status', 'active'),
                    ])
                    ->withCount([
                        'secondaryStaffAssignments as secondary_staff_count',
                    ])
                    ->orderBy('hr_organizational_units.name'),
            ])
            ->withCount(['staffAssignments as routing_staff_count' => fn ($query) => $query->whereNotIn('status', ['inactive', 'orphaned'])]);
    }

    public function staffAssignments()
    {
        return $this->hasMany(StaffAssignment::class, 'organizational_unit_id');
    }

    public function clientSpaceStaffAssignments()
    {
        return $this->hasMany(HrClientSpaceStaffAssignment::class, 'client_space_unit_id');
    }

    public function secondaryStaffAssignments()
    {
        return $this->hasMany(HrClientSpaceStaffAssignment::class, 'client_space_unit_id')
            ->where('assignment_type', HrClientSpaceStaffAssignment::TYPE_SECONDARY)
            ->where('status', HrClientSpaceStaffAssignment::STATUS_ACTIVE)
            ->whereHas('staffAssignment', fn (Builder $query) => $query->eligibleForSecondaryClientSpaceAssignments());
    }

    public function clientSpace()
    {
        return $this->hasOne(HrClientSpace::class, 'organizational_unit_id');
    }

    public function clientSpaceRoutes()
    {
        return $this->hasMany(HrClientSpaceRoute::class, 'client_space_unit_id');
    }

    public function primaryRoutingLink()
    {
        return $this->hasOne(HrClientSpaceRoute::class, 'client_space_unit_id')->where('is_primary', true);
    }

    public function routingParents()
    {
        return $this->belongsToMany(
            self::class,
            'hr_client_space_routes',
            'client_space_unit_id',
            'routing_unit_id'
        )
            ->withPivot('is_primary')
            ->orderByDesc('hr_client_space_routes.is_primary')
            ->orderBy('hr_organizational_units.name');
    }

    public function linkedClientSpaces()
    {
        return $this->belongsToMany(
            self::class,
            'hr_client_space_routes',
            'routing_unit_id',
            'client_space_unit_id'
        )
            ->withPivot('is_primary')
            ->orderByDesc('hr_client_space_routes.is_primary')
            ->orderBy('hr_organizational_units.name');
    }

    public function linkedPrimaryClientSpaces()
    {
        return $this->belongsToMany(
            self::class,
            'hr_client_space_routes',
            'routing_unit_id',
            'client_space_unit_id'
        )
            ->withPivot('is_primary')
            ->wherePivot('is_primary', true)
            ->orderBy('hr_organizational_units.name');
    }

    public function rosters()
    {
        return $this->hasMany(HrDutyRoster::class, 'organizational_unit_id');
    }

    public function openShifts()
    {
        return $this->hasMany(HrOpenShift::class, 'client_space_unit_id');
    }

    public function hasRoutingMember(?User $user): bool
    {
        if (! $user?->staff_uuid || ! $this->isRoutingNode()) {
            return false;
        }

        return $this->staffAssignments()
            ->where('staff_uuid', $user->staff_uuid)
            ->whereNotIn('status', ['inactive', 'orphaned'])
            ->exists();
    }

    public function isClientSpace(): bool
    {
        return $this->unit_kind === self::KIND_CLIENT_SPACE;
    }

    public function isRoutingNode(): bool
    {
        return $this->unit_kind === self::KIND_ROUTING_NODE;
    }

    public function isLowestRoutingNode(): bool
    {
        if (! $this->isRoutingNode()) {
            return false;
        }

        if ($this->relationLoaded('children')) {
            return ! $this->children->contains(fn (self $child): bool => $child->isRoutingNode());
        }

        return ! $this->children()->routingNodes()->exists();
    }

    public function hasClientSpacePlacement(): bool
    {
        if (! $this->isClientSpace()) {
            return false;
        }

        if ($this->parent_id !== null) {
            if ($this->relationLoaded('parent')) {
                if ($this->parent) {
                    return true;
                }
            } elseif ($this->parent()->exists()) {
                return true;
            }
        }

        if ($this->relationLoaded('routingParents')) {
            return $this->routingParents->isNotEmpty();
        }

        if ($this->relationLoaded('clientSpaceRoutes')) {
            return $this->clientSpaceRoutes->isNotEmpty();
        }

        return $this->clientSpaceRoutes()->exists();
    }

    public function isLinkedToRoutingNode(self $routingUnit): bool
    {
        if (! $this->isClientSpace() || ! $routingUnit->isRoutingNode()) {
            return false;
        }

        if ((int) $this->parent_id === (int) $routingUnit->id) {
            return true;
        }

        if ($this->relationLoaded('clientSpaceRoutes')) {
            return $this->clientSpaceRoutes->contains(
                fn (HrClientSpaceRoute $route): bool => (int) $route->routing_unit_id === (int) $routingUnit->id
            );
        }

        return $this->clientSpaceRoutes()
            ->where('routing_unit_id', $routingUnit->id)
            ->exists();
    }

    public function scopeClientSpaces(Builder $query): Builder
    {
        return $query->where('unit_kind', self::KIND_CLIENT_SPACE);
    }

    public function scopeUnattachedClientSpaces(Builder $query): Builder
    {
        return $query->clientSpaces()
            ->whereNull('parent_id')
            ->whereDoesntHave('clientSpaceRoutes');
    }

    public function scopeRoutingNodes(Builder $query): Builder
    {
        return $query->where('unit_kind', self::KIND_ROUTING_NODE);
    }

    public function scopeLowestRoutingNodes(Builder $query): Builder
    {
        return $query->routingNodes()
            ->whereDoesntHave('children', fn (Builder $childQuery) => $childQuery->routingNodes());
    }
}
