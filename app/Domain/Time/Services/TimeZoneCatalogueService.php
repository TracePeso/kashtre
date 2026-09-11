<?php

namespace App\Domain\Time\Services;

use App\Domain\Time\Exceptions\TimeEngineException;
use App\Domain\Time\Models\CoreTimeZone;
use App\Domain\Time\Models\TimeAudit;
use App\Domain\Time\ValueObjects\IanaTimezoneId;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

final class TimeZoneCatalogueService
{
    public function ensureKnown(string $ianaId): CoreTimeZone
    {
        $vo = IanaTimezoneId::tryOf($ianaId);
        if (! $vo) {
            throw TimeEngineException::invalidTimezone($ianaId);
        }

        $existing = CoreTimeZone::query()->where('iana_id', $vo->value())->first();
        if ($existing) {
            return $existing;
        }

        return CoreTimeZone::query()->create([
            'iana_id' => $vo->value(),
            'display_name' => str_replace('_', ' ', $vo->value()),
            'region_code' => str_contains($vo->value(), '/') ? explode('/', $vo->value(), 2)[0] : null,
            'canonical_iana_id' => $vo->value(),
            'status' => 'ACTIVE',
            'tzdb_release' => $this->tzdbRelease(),
            'is_fixed_offset' => str_starts_with($vo->value(), 'Etc/GMT') || $vo->value() === 'UTC',
        ]);
    }

    public function find(string $ianaId): ?CoreTimeZone
    {
        return CoreTimeZone::query()->where('iana_id', $ianaId)->first();
    }

    public function resolveCanonical(string $ianaId): IanaTimezoneId
    {
        $row = $this->find($ianaId);
        if ($row) {
            return IanaTimezoneId::of($row->canonicalId());
        }

        $vo = IanaTimezoneId::tryOf($ianaId);
        if (! $vo) {
            throw TimeEngineException::invalidTimezone($ianaId);
        }

        return $vo;
    }

    /**
     * @return Collection<int, CoreTimeZone>
     */
    public function search(?string $query = null, int $limit = 50): Collection
    {
        $q = CoreTimeZone::query()->where('status', 'ACTIVE')->orderBy('iana_id');
        if ($query) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $query).'%';
            $q->where(function ($inner) use ($like) {
                $inner->where('iana_id', 'like', $like)
                    ->orWhere('display_name', 'like', $like)
                    ->orWhere('region_code', 'like', $like);
            });
        }

        return $q->limit($limit)->get();
    }

    public function tzdbRelease(): string
    {
        // PHP does not expose TZDB version portably; record runtime marker.
        return 'php-'.PHP_VERSION.'-'.date('Y');
    }

    public function deprecate(string $ianaId, string $replacementIanaId, ?string $reason = null): CoreTimeZone
    {
        $row = $this->ensureKnown($ianaId);
        $this->ensureKnown($replacementIanaId);
        $before = $row->toArray();
        $row->update([
            'status' => 'DEPRECATED',
            'canonical_iana_id' => $replacementIanaId,
        ]);

        TimeAudit::query()->create([
            'tenant_key' => 'SYSTEM',
            'actor_user_id' => Auth::id(),
            'action' => 'TIMEZONE_DEPRECATE',
            'object_type' => 'core_time_zones',
            'object_public_id' => $row->public_id,
            'before' => $before,
            'after' => $row->fresh()->toArray(),
            'reason' => $reason,
        ]);

        return $row->fresh();
    }
}
