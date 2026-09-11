<?php

namespace App\Support;

use App\Models\Business;

/**
 * Derives a business's entity code from its name.
 *
 * The code is two things at once, which is why it cannot be left blank: the
 * prefix on LPO numbers (KCH-LPO-20260712-001), and the tenant identifier Main
 * presents to the Clinical Module as X-Tenant-Id.
 *
 * That second role is why this is generated rather than typed. A business with
 * no code falls back to a synthetic TENANT-{id}, which no other system has ever
 * heard of — so every clinical read returns an empty result set that looks like
 * "this ward has no beds" rather than "this facility was never mapped".
 *
 * Initials of the name, because that is what a person would pick: "Kashtre
 * Community Hospital" → KCH. Collisions get a numeric suffix rather than a
 * longer code, since the code is read aloud and typed into forms.
 */
class BusinessEntityCode
{
    private const MAX_LENGTH = 16;

    public static function generate(string $name, ?int $ignoreBusinessId = null): string
    {
        $base = self::base($name);

        if (! self::isTaken($base, $ignoreBusinessId)) {
            return $base;
        }

        // Leave room for the suffix rather than truncating it off the end.
        $stem = substr($base, 0, self::MAX_LENGTH - 2);

        for ($suffix = 2; $suffix < 100; $suffix++) {
            $candidate = $stem.$suffix;

            if (! self::isTaken($candidate, $ignoreBusinessId)) {
                return $candidate;
            }
        }

        // Astronomically unlikely; a unique-but-ugly code still beats null,
        // which silently unmaps the tenant.
        return substr($base, 0, self::MAX_LENGTH - 6).strtoupper(substr(md5($name.microtime()), 0, 6));
    }

    private static function base(string $name): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return 'ENTITY';
        }

        // Possessives and initials split into stray one-character fragments —
        // "St. Mary's Hospital" becomes St/Mary/s/Hospital, and counting that
        // orphaned "s" gives SMSH where a person would write SMH. Drop them,
        // unless dropping leaves nothing to work with.
        $significant = array_values(array_filter($words, fn ($word) => strlen($word) > 1));
        if (count($significant) > 1) {
            $words = $significant;
        }

        // A single word has no initials worth the name — take its opening
        // letters instead ("Kashtre" → KASH, not K).
        if (count($words) === 1) {
            return strtoupper(substr($words[0], 0, 4));
        }

        $initials = '';
        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }

        return substr($initials, 0, self::MAX_LENGTH);
    }

    private static function isTaken(string $code, ?int $ignoreBusinessId): bool
    {
        return Business::query()
            ->withTrashed()
            ->when($ignoreBusinessId, fn ($q) => $q->whereKeyNot($ignoreBusinessId))
            ->whereRaw('UPPER(entity_code) = ?', [strtoupper($code)])
            ->exists();
    }
}
