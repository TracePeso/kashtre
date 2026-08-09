<?php

namespace App\Support;

use RuntimeException;

/**
 * Generates a globally-unique DICOM StudyInstanceUID.
 *
 * Uses the ISO/IEC UUID-derived root "2.25" (DICOM PS3.5 Annex B.2): "2.25."
 * followed by the decimal form of a random 128-bit UUID. Requires no OID
 * registration. If a real org root is configured (services.orthanc.uid_root),
 * appends a unique numeric suffix under that root instead, keeping the total
 * length within the 64-char DICOM UI limit.
 */
class DicomUid
{
    public static function generate(): string
    {
        $dec = self::hexToDec(bin2hex(random_bytes(16)));
        $root = rtrim((string) config('services.orthanc.uid_root', '2.25'), '.');

        if ($root === '2.25' || $root === '') {
            return '2.25.'.$dec;
        }

        $suffix = substr($dec, 0, max(1, 63 - strlen($root)));

        return $root.'.'.(ltrim($suffix, '0') ?: '0');
    }

    private static function hexToDec(string $hex): string
    {
        if (function_exists('gmp_init')) {
            return gmp_strval(gmp_init($hex, 16), 10);
        }

        if (function_exists('bcadd')) {
            $dec = '0';
            foreach (str_split(strtolower($hex)) as $ch) {
                $dec = bcadd(bcmul($dec, '16'), (string) hexdec($ch));
            }

            return $dec;
        }

        throw new RuntimeException('Enable ext-gmp or ext-bcmath to generate DICOM UIDs.');
    }
}
