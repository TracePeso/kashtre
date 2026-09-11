<?php

namespace App\Domain\Time\Services;

use App\Domain\Time\Models\ClockHealth;
use App\Domain\Time\Models\TimeAudit;
use Illuminate\Support\Facades\Cache;

/**
 * Detect mixed TZDB releases across nodes (TIME-TZ-010).
 */
final class TzdbHealthService
{
    public function __construct(private readonly TimeZoneCatalogueService $catalogue) {}

    public function localRelease(): string
    {
        return $this->catalogue->tzdbRelease();
    }

    public function recordNodeRelease(string $nodeKey): void
    {
        $release = $this->localRelease();
        Cache::put($this->cacheKey($nodeKey), $release, now()->addDays(7));

        $row = ClockHealth::query()->where('node_key', $nodeKey)->first();
        if ($row) {
            $meta = $row->metadata ?? [];
            $meta['tzdb_release'] = $release;
            $row->update(['metadata' => $meta]);
        }
    }

    /**
     * @return array{mismatch: bool, releases: array<string, string>}
     */
    public function scan(): array
    {
        $releases = [];
        foreach (ClockHealth::query()->get() as $row) {
            $rel = $row->metadata['tzdb_release'] ?? null;
            if ($rel) {
                $releases[$row->node_key] = $rel;
            }
        }
        $releases[gethostname() ?: 'app'] = $this->localRelease();
        $unique = array_unique(array_values($releases));
        $mismatch = count($unique) > 1;

        if ($mismatch) {
            TimeAudit::query()->create([
                'tenant_key' => 'SYSTEM',
                'action' => 'TZDB_MISMATCH',
                'object_type' => 'tzdb',
                'object_public_id' => 'CLUSTER',
                'after' => ['releases' => $releases],
                'reason' => 'Mixed TZDB releases among nodes',
            ]);
        }

        return ['mismatch' => $mismatch, 'releases' => $releases];
    }

    private function cacheKey(string $nodeKey): string
    {
        return 'time:tzdb:'.$nodeKey;
    }
}
