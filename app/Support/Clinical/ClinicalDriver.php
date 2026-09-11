<?php

namespace App\Support\Clinical;

/**
 * Which side owns the clinical data right now.
 *
 * Panels that still read Main's own clinical_* tables need to know, because
 * under CLINICAL_DRIVER=api those tables are not merely empty — they do not
 * exist in this database, and touching one is a 500 that takes the whole chart
 * down rather than one panel.
 *
 * This is a stopgap, not an architecture. Every use of it marks a panel that
 * has no gateway yet; the goal is for this class to end up with no callers.
 */
class ClinicalDriver
{
    public static function isApi(): bool
    {
        return self::current() === 'api';
    }

    /**
     * True when the local clinical_* tables are the system of record and can be
     * queried directly.
     */
    public static function usesLocalTables(): bool
    {
        return ! self::isApi();
    }

    private static function current(): string
    {
        $driver = (string) config('services.clinical.driver', 'local');

        // Mirrors ClinicalGatewayServiceProvider: `api` without a URL falls
        // back to local, and a panel must agree with the container about which
        // implementation is actually bound.
        if ($driver === 'api' && empty(config('services.clinical.url'))) {
            return 'local';
        }

        return $driver === 'api' ? 'api' : 'local';
    }
}
