<?php

namespace App\Support\Clinical;

use Illuminate\Database\Eloquent\Model;

/**
 * A ward and its bed occupancy — SRD §5.2's Real-Time Ward Census.
 *
 * The counts are carried rather than derived from `beds` because the Clinical
 * Module returns them alongside a grid it may page or elide; recomputing from
 * a partial grid would silently under-report occupancy on a busy ward.
 */
class WardCensus
{
    /**
     * @param  array<int, WardBed>  $beds
     */
    public function __construct(
        public readonly string $ward_code,
        public readonly ?string $ward_name = null,
        public readonly ?string $building_wing = null,
        public readonly int $total = 0,
        public readonly int $occupied = 0,
        public readonly int $reserved = 0,
        public readonly int $available = 0,
        public readonly int $overflow = 0,
        public readonly array $beds = [],
        public readonly ?int $id = null,
        public readonly ?float $occupancy_rate = null,
    ) {
    }

    /**
     * The client_space to add an overflow bed to. Adding a bed is keyed by
     * numeric space id while everything else in this group is keyed by ward
     * code, and neither the picker nor the census header carries the id — it
     * only appears on the beds themselves.
     *
     * A ward may span several spaces (`space_count`); the first bed's space is
     * the pragmatic choice, and an empty ward has none to offer at all.
     */
    public function spaceIdForOverflow(): ?int
    {
        foreach ($this->beds as $bed) {
            if ($bed->space_id !== null) {
                return $bed->space_id;
            }
        }

        return $this->id;
    }

    /** Whether the "clear surge beds" action has anything to act on. */
    public function hasVacantOverflowBeds(): bool
    {
        foreach ($this->beds as $bed) {
            if ($bed->is_overflow && ! $bed->isOccupied() && ! $bed->isReserved()) {
                return true;
            }
        }

        return false;
    }

    public static function fromModel(Model $ward): self
    {
        $beds = $ward->beds ?? collect();

        return new self(
            ward_code: (string) $ward->ward_code,
            ward_name: $ward->ward_name ? (string) $ward->ward_name : null,
            building_wing: $ward->building_wing ? (string) $ward->building_wing : null,
            total: $beds->count(),
            occupied: $beds->where('operational_state', 'OCCUPIED')->count(),
            reserved: $beds->where('operational_state', 'RESERVED')->count(),
            available: $beds->where('operational_state', 'AVAILABLE')->count(),
            overflow: $beds->where('is_overflow', true)->count(),
            beds: $beds->map(fn ($bed) => WardBed::fromModel($bed))->values()->all(),
            id: (int) $ward->id,
        );
    }

    /**
     * @param  array<string, mixed>  $payload  the `data` block of
     *                                         GET /clinical/wards/{code}/census
     */
    public static function fromApi(array $payload): self
    {
        // A picker row (GET /clinical/wards) carries counts but no grid at all,
        // so this has to survive both keys being absent.
        $raw = $payload['beds_grid'] ?? $payload['beds'] ?? [];
        $grid = array_values(array_filter(is_array($raw) ? $raw : [], 'is_array'));

        // The census header has no building_wing; it rides on each bed instead.
        $wing = $payload['building_wing'] ?? ($grid[0]['building_wing'] ?? null);

        return new self(
            ward_code: (string) ($payload['ward_code'] ?? ''),
            ward_name: isset($payload['ward_name']) && $payload['ward_name'] !== null
                ? (string) $payload['ward_name']
                : null,
            building_wing: $wing !== null ? (string) $wing : null,
            total: (int) ($payload['total_beds'] ?? $payload['total'] ?? 0),
            occupied: (int) ($payload['occupied'] ?? 0),
            reserved: (int) ($payload['reserved'] ?? 0),
            available: (int) ($payload['available'] ?? 0),
            overflow: (int) ($payload['overflow_beds'] ?? $payload['overflow'] ?? 0),
            beds: array_map(fn (array $bed) => WardBed::fromApi($bed), $grid),
            id: null,
            occupancy_rate: isset($payload['occupancy_rate']) ? (float) $payload['occupancy_rate'] : null,
        );
    }
}
