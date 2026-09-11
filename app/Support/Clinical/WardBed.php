<?php

namespace App\Support\Clinical;

use Illuminate\Database\Eloquent\Model;

/**
 * One bed in a ward's census grid (SRD §5.2).
 *
 * `id` is the bed identifier the per-bed actions address
 * (POST /clinical/beds/{bedId}/reserve|assign|release, DELETE /clinical/beds/{bedId}).
 * Both drivers populate it — Clinical returns it in `beds_grid`, and locally it
 * is the clinical_beds primary key.
 *
 * `space_id` is the client_space this bed sits in, and is carried because
 * adding an overflow bed is the one action keyed by numeric space id rather
 * than by ward code.
 */
class WardBed
{
    public function __construct(
        public readonly int $id,
        public readonly string $bed_code,
        public readonly string $operational_state,
        public readonly ?string $current_client_id = null,
        public readonly ?string $current_visit_id = null,
        public readonly bool $is_overflow = false,
        public readonly ?int $space_id = null,
        public readonly ?string $room_number = null,
    ) {
    }

    public function isOccupied(): bool
    {
        return $this->operational_state === 'OCCUPIED';
    }

    public function isReserved(): bool
    {
        return $this->operational_state === 'RESERVED';
    }

    public function isAvailable(): bool
    {
        return $this->operational_state === 'AVAILABLE';
    }

    public static function fromModel(Model $model): self
    {
        return new self(
            id: (int) $model->id,
            bed_code: (string) $model->bed_code,
            operational_state: strtoupper((string) $model->operational_state),
            current_client_id: $model->current_client_id ? (string) $model->current_client_id : null,
            current_visit_id: $model->current_visit_id ? (string) $model->current_visit_id : null,
            is_overflow: (bool) $model->is_overflow,
            space_id: $model->ward_id ? (int) $model->ward_id : null,
            room_number: null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload  a `beds_grid` entry, or the bed
     *                                         model returned by reserve/assign/release
     */
    public static function fromApi(array $payload): self
    {
        return new self(
            id: (int) ($payload['id'] ?? $payload['bed_id'] ?? 0),
            bed_code: (string) ($payload['bed_code'] ?? $payload['code'] ?? ''),
            operational_state: strtoupper((string) ($payload['operational_state'] ?? $payload['state'] ?? 'AVAILABLE')),
            // Clinical says current_patient_id; the older shape said patient_id.
            current_client_id: self::firstFilled($payload, ['current_patient_id', 'current_client_id', 'patient_id']),
            current_visit_id: self::firstFilled($payload, ['current_visit_id', 'visit_id']),
            is_overflow: (bool) ($payload['is_overflow'] ?? false),
            space_id: isset($payload['space_id']) ? (int) $payload['space_id'] : null,
            room_number: self::firstFilled($payload, ['room_number']),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     */
    private static function firstFilled(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && $payload[$key] !== '') {
                return (string) $payload[$key];
            }
        }

        return null;
    }
}
