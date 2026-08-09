<?php

namespace App\Support\Clinical;

/**
 * The deterministic CDSS shield's verdict on a proposed prescription.
 *
 * Locally this comes back inline from DeterministicCdssShield::evaluateMedicationSafety().
 * Over the API it arrives two different ways, and the difference matters:
 *
 *   POST /clinical/cdss/evaluate   — a dry run, returns 200 with the verdict
 *   POST /clinical/orders/medications — a real order, and a hard block is a
 *                                     422 CDSS_HARD_BLOCK *refusal*, not a
 *                                     field on a success response
 *
 * So the shape is shared but the control flow is not: the dry run produces
 * one of these, while the real order path produces a
 * ClinicalSafetyBlockException that the gateway converts into one.
 *
 * Block entries are normalised to `type` + `message`. The API's own key is
 * `detail`; keeping one name here means the Blade views do not have to care
 * which backend answered.
 */
class SafetyEvaluation
{
    /**
     * @param  array<int, array{type: string, message: string}>  $hard_blocks
     * @param  array<int, array{type: string, message: string}>  $warnings
     */
    public function __construct(
        public readonly bool $is_safe,
        public readonly array $hard_blocks = [],
        public readonly array $warnings = [],
    ) {
    }

    public static function safe(): self
    {
        return new self(true);
    }

    /**
     * @param  array{is_safe?: bool, hard_blocks?: array, warnings?: array}  $payload
     */
    public static function fromArray(array $payload): self
    {
        $hardBlocks = self::normaliseEntries($payload['hard_blocks'] ?? []);
        $warnings = self::normaliseEntries($payload['warnings'] ?? []);

        return new self(
            is_safe: $payload['is_safe'] ?? count($hardBlocks) === 0,
            hard_blocks: $hardBlocks,
            warnings: $warnings,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array{type: string, message: string}>
     */
    private static function normaliseEntries(array $entries): array
    {
        return array_values(array_map(fn (array $entry): array => [
            'type' => (string) ($entry['type'] ?? 'UNSPECIFIED'),
            'message' => (string) ($entry['message'] ?? $entry['detail'] ?? ''),
        ], $entries));
    }

    /**
     * True when the clinician must supply an override reason code to proceed.
     * Warnings alone never require one — they are advisory.
     */
    public function requiresOverride(): bool
    {
        return ! $this->is_safe;
    }
}
