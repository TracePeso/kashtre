<?php

namespace App\Domain\Units\Services;

use App\Domain\Units\Contracts\DecimalMath;
use App\Domain\Units\Exceptions\ConversionException;
use App\Domain\Units\Models\ConversionRule;
use App\Domain\Units\ValueObjects\ConversionContext;
use App\Models\Item;

final class NamedAlgorithmRegistry
{
    public function __construct(
        private readonly DecimalMath $math,
    ) {}

    public function execute(
        string $algorithm,
        string $value,
        ConversionContext $context,
        ConversionRule $rule,
        bool $reverse = false,
    ): string {
        return match ($algorithm) {
            'ITEM_PACKAGE_CHAIN' => $this->itemPackageChain($value, $context, $rule, $reverse),
            default => throw ConversionException::ruleMissing(
                (string) ($rule->fromUnit?->public_id ?? 'unknown'),
                (string) ($rule->toUnit?->public_id ?? 'unknown')
            ),
        };
    }

    /**
     * scale_decimal / item.suom_per_ouom = sale units per order (packaging) unit.
     * Forward (order → sale): multiply. Reverse (sale → order): divide.
     */
    protected function itemPackageChain(
        string $value,
        ConversionContext $context,
        ConversionRule $rule,
        bool $reverse,
    ): string {
        if (! $context->itemPublicId) {
            throw ConversionException::contextRequired('ITEM');
        }

        $item = Item::query()
            ->where(function ($q) use ($context) {
                $q->where('uuid', $context->itemPublicId);
                if (ctype_digit($context->itemPublicId)) {
                    $q->orWhere('id', (int) $context->itemPublicId);
                }
            })
            ->first();

        if (! $item) {
            throw ConversionException::contextRequired('ITEM');
        }

        $ctxRow = $rule->contexts->firstWhere('context_type', 'ITEM');
        $factor = $ctxRow?->parameters['factor_decimal']
            ?? ((string) ($rule->scale_decimal ?: '') !== '' ? (string) $rule->scale_decimal : null)
            ?? ((float) ($item->suom_per_ouom ?? 0) > 0 ? rtrim(rtrim(number_format((float) $item->suom_per_ouom, 4, '.', ''), '0'), '.') : null);

        if ($factor === null || $factor === '' || $this->math->compare((string) $factor, '0') <= 0) {
            throw ConversionException::ruleMissing(
                (string) ($rule->fromUnit?->public_id ?? 'unknown'),
                (string) ($rule->toUnit?->public_id ?? 'unknown')
            );
        }

        $scale = (int) $rule->calculation_scale;

        if ($reverse) {
            return $this->math->divide($value, (string) $factor, $scale);
        }

        return $this->math->multiply($value, (string) $factor, $scale);
    }
}
