<?php

namespace App\Support;

class InventoryFulfillmentStrategy
{
    public const DISCRETE_IMMEDIATE = 'DISCRETE_IMMEDIATE';

    public const BATCH_AND_STAGE = 'BATCH_AND_STAGE';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::DISCRETE_IMMEDIATE => 'Outpatient (discrete / immediate)',
            self::BATCH_AND_STAGE => 'Inpatient (batch & stage)',
        ];
    }

    public static function label(?string $strategy): string
    {
        if ($strategy === null || $strategy === '') {
            return '—';
        }

        return self::options()[$strategy] ?? $strategy;
    }

    /**
     * Approved Pool is only for inpatient batch & stage.
     * Outpatient (discrete / immediate) dispenses off the ticket with no pool balance.
     */
    public static function supportsApprovedPool(?string $strategy): bool
    {
        return $strategy === self::BATCH_AND_STAGE;
    }
}
