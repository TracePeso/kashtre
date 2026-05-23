<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ShiftType extends Model
{
    use SoftDeletes;

    protected $table = 'hr_shift_types';

    protected $fillable = [
        'uuid', 'organization_id', 'name', 'code', 'start_time', 'end_time',
        'gross_duration_minutes', 'break_duration_minutes', 'break_durations_minutes', 'net_duration_minutes',
        'color', 'is_active', 'is_rosterable', 'is_system_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_rosterable' => 'boolean',
        'is_system_default' => 'boolean',
        'gross_duration_minutes' => 'integer',
        'break_duration_minutes' => 'integer',
        'net_duration_minutes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? (string) Str::uuid();

            if (! array_key_exists('is_system_default', $model->attributes)) {
                $model->attributes['is_system_default'] = self::shouldAssignSystemDefault(
                    (int) ($model->organization_id ?? 0)
                );
            }
        });

        static::saving(function (self $model): void {
            $rawBreaks = $model->attributes['break_durations_minutes'] ?? null;

            if (! array_key_exists('break_durations_minutes', $model->attributes) || $rawBreaks === null || $rawBreaks === '') {
                $normalized = self::normalizeBreakDurations(
                    null,
                    (int) ($model->attributes['break_duration_minutes'] ?? 0)
                );

                $model->attributes['break_durations_minutes'] = json_encode($normalized);
                $model->attributes['break_duration_minutes'] = self::sumBreakDurations($normalized);
            }
        });

        static::saved(function (self $model): void {
            if (! self::supportsSystemDefaultFlag() || ! $model->organization_id || ! $model->is_system_default) {
                return;
            }

            self::query()
                ->where('organization_id', $model->organization_id)
                ->whereKeyNot($model->getKey())
                ->where('is_system_default', true)
                ->update(['is_system_default' => false]);
        });

        static::deleted(function (self $model): void {
            if (! self::supportsSystemDefaultFlag() || ! $model->organization_id || ! $model->is_system_default) {
                return;
            }

            $replacementId = self::query()
                ->where('organization_id', $model->organization_id)
                ->orderBy('id')
                ->value('id');

            if (! $replacementId) {
                return;
            }

            self::query()
                ->whereKey($replacementId)
                ->update(['is_system_default' => true]);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function effectiveGrossMinutes(): int
    {
        if ($this->gross_duration_minutes !== null) {
            return max(0, (int) $this->gross_duration_minutes);
        }

        $start = CarbonImmutable::parse('2000-01-01 '.$this->start_time);
        $end = CarbonImmutable::parse('2000-01-01 '.$this->end_time);

        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        return $start->diffInMinutes($end);
    }

    public function effectiveNetMinutes(): int
    {
        if ($this->net_duration_minutes !== null) {
            return max(0, (int) $this->net_duration_minutes);
        }

        return max(0, $this->effectiveGrossMinutes() - $this->totalBreakDurationMinutes());
    }

    public function crossesMidnight(): bool
    {
        $start = CarbonImmutable::parse('2000-01-01 '.$this->start_time);
        $end = CarbonImmutable::parse('2000-01-01 '.$this->end_time);

        return $end->lessThanOrEqualTo($start);
    }

    public function getBreakDurationsMinutesAttribute($value): array
    {
        return self::normalizeBreakDurations($value, (int) ($this->attributes['break_duration_minutes'] ?? 0));
    }

    public function setBreakDurationsMinutesAttribute($value): void
    {
        $normalized = self::normalizeBreakDurations($value);

        $this->attributes['break_durations_minutes'] = json_encode($normalized);
        $this->attributes['break_duration_minutes'] = self::sumBreakDurations($normalized);
    }

    public function totalBreakDurationMinutes(): int
    {
        return self::sumBreakDurations($this->break_durations_minutes);
    }

    public function formattedBreakDurations(): string
    {
        $breaks = array_map(
            static fn (array $entry): string => (string) $entry['duration_minutes'],
            $this->break_durations_minutes
        );

        return $breaks === [] ? '0' : implode(' + ', $breaks);
    }

    private static function shouldAssignSystemDefault(int $organizationId): bool
    {
        if (! self::supportsSystemDefaultFlag() || $organizationId <= 0) {
            return false;
        }

        return ! self::query()
            ->where('organization_id', $organizationId)
            ->where('is_system_default', true)
            ->exists();
    }

    private static function supportsSystemDefaultFlag(): bool
    {
        return Schema::hasColumn('hr_shift_types', 'is_system_default');
    }

    /**
     * @return array<int, array{duration_minutes: int}>
     */
    private static function normalizeBreakDurations(mixed $value, int $fallbackMinutes = 0): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        if (! is_array($value)) {
            return $fallbackMinutes > 0
                ? [['duration_minutes' => max(0, $fallbackMinutes)]]
                : [];
        }

        $normalized = [];

        foreach ($value as $entry) {
            $minutes = match (true) {
                is_numeric($entry) => (int) $entry,
                is_array($entry) => (int) ($entry['duration_minutes'] ?? $entry['minutes'] ?? 0),
                default => 0,
            };

            if ($minutes <= 0) {
                continue;
            }

            $normalized[] = ['duration_minutes' => $minutes];
        }

        return array_values($normalized);
    }

    private static function sumBreakDurations(mixed $value, int $fallbackMinutes = 0): int
    {
        return collect(self::normalizeBreakDurations($value, $fallbackMinutes))
            ->sum(fn (array $entry): int => (int) $entry['duration_minutes']);
    }
}
