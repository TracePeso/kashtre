<?php

namespace App\Support;

/**
 * Pulls a dose strength out of an item name.
 *
 * Kashtre's catalogue carries strength inside the display name ("Axcel 400mg",
 * "Danset 8mg/4ml ampuole") rather than in its own column, but the Clinical
 * Module's tier-two matching is *term + strength* — without a separate value it
 * cannot tell the 200mg from the 400mg from the 500mg infusion, and the choice
 * of dose falls to whoever reads the candidate list.
 *
 * Deliberately conservative: a bare trailing number ("Ciprobid 500") is NOT a
 * strength, because the unit is what makes it one. Returning null there is
 * correct — a wrong strength is worse than a missing one.
 */
class ItemStrengthParser
{
    /**
     * Units we recognise. Order matters: longer units first so "mcg" is not
     * matched as "cg", and "gm" is not truncated to "g".
     */
    private const UNIT = '(?:mcg|mgs|mg|gm|kg|ug|g|mls|ml|l|iu|units?|%)';

    /**
     * @return string|null Normalised strength ("400mg", "8mg/4ml", "1% w/v")
     */
    public static function parse(?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        // number + unit, optionally followed by "/number unit" (8mg/4ml,
        // 0.05mg/ml) and optionally a w/v or v/v qualifier (1% w/v).
        $pattern = '/(\d+(?:\.\d+)?)\s*('.self::UNIT.')'
            .'(?:\s*\/\s*(\d+(?:\.\d+)?)?\s*('.self::UNIT.'))?'
            .'(?:\s*(w\/v|v\/v))?/i';

        if (! preg_match_all($pattern, $name, $matches, PREG_SET_ORDER)) {
            return null;
        }

        foreach ($matches as $m) {
            // A bare volume is a pack size, not a dose: "relcer gel 180 ml" and
            // "Bupitroy heavy 4mls" say nothing about strength. Only accept a
            // volume when it is the denominator of a concentration ("10mg/ml").
            // Keep scanning rather than bailing out — in "Bupitroy 20mls 0.5%"
            // the real strength trails the pack volume.
            if (self::isVolume($m[2]) && empty($m[4])) {
                continue;
            }

            $strength = $m[1].strtolower($m[2]);

            if (! empty($m[4])) {
                $strength .= '/'.($m[3] ?? '').strtolower($m[4]);
            }

            if (! empty($m[5])) {
                $strength .= ' '.strtolower($m[5]);
            }

            return $strength;
        }

        return null;
    }

    private static function isVolume(string $unit): bool
    {
        return in_array(strtolower($unit), ['ml', 'mls', 'l'], true);
    }
}
