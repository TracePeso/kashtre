<?php

namespace App\Domain\Units\Services;

use App\Domain\Units\Contracts\DecimalMath;
use App\Domain\Units\Enums\ConversionRuleType;
use App\Domain\Units\Exceptions\ConversionException;
use App\Domain\Units\Models\ConversionRule;
use App\Domain\Units\Models\CoreUnit;
use App\Domain\Units\ValueObjects\ConversionContext;
use App\Domain\Units\ValueObjects\ConversionResult;
use App\Domain\Units\ValueObjects\DimensionVector;
use App\Domain\Units\ValueObjects\Quantity;
use DateTimeImmutable;
use Illuminate\Support\Collection;

final class ConversionEngine
{
    public function __construct(
        private readonly UnitCatalogService $units,
        private readonly DecimalMath $math,
        private readonly NamedAlgorithmRegistry $algorithms,
        private readonly CompositionService $composition,
    ) {}

    public function convert(Quantity $quantity, string $targetUnitPublicId, ConversionContext $context): ConversionResult
    {
        $at = $context->effectiveAt ?? new DateTimeImmutable();
        $from = $this->units->resolvable($context->tenantKey, $quantity->unitPublicId, $at);
        $to = $this->units->resolvable($context->tenantKey, $targetUnitPublicId, $at);

        if ($from->public_id === $to->public_id) {
            return new ConversionResult(
                sourceValue: $quantity->value,
                sourceUnitPublicId: $from->public_id,
                targetValue: $quantity->value,
                targetUnitPublicId: $to->public_id,
                rulePublicId: 'IDENTITY',
                ruleVersion: 1,
                displayPrecision: 0,
                roundingMode: 'UNNECESSARY',
            );
        }

        $sameDimension = $this->composition->computeDimension($from)
            ->equals($this->composition->computeDimension($to));

        $candidates = $this->effectiveCandidates($context->tenantKey, $from, $to, $at);

        if ($candidates->isEmpty() && $sameDimension) {
            $via = $this->composition->convertViaCanonical($quantity->value, $from, $to);
            if ($via !== null) {
                return new ConversionResult(
                    sourceValue: $quantity->value,
                    sourceUnitPublicId: $from->public_id,
                    targetValue: $via,
                    targetUnitPublicId: $to->public_id,
                    rulePublicId: 'COMPOSITION_CANONICAL',
                    ruleVersion: 1,
                    displayPrecision: 4,
                    roundingMode: 'HALF_UP',
                );
            }
        }

        $rule = $this->selectRule($candidates, $context, $sameDimension, $from, $to);
        $reverse = (int) $rule->from_unit_id === (int) $to->id
            && (int) $rule->to_unit_id === (int) $from->id;

        $this->assertRange($quantity->value, $rule);

        $raw = match ($rule->rule_type) {
            ConversionRuleType::SCALE->value => $reverse
                ? $this->math->divide(
                    $quantity->value,
                    (string) $rule->scale_decimal,
                    (int) $rule->calculation_scale
                )
                : $this->math->multiply(
                    $quantity->value,
                    (string) $rule->scale_decimal,
                    (int) $rule->calculation_scale
                ),
            ConversionRuleType::AFFINE->value => $this->applyAffine($quantity->value, $rule, $reverse),
            ConversionRuleType::PRODUCT_SPECIFIC->value,
            ConversionRuleType::SUBSTANCE_SPECIFIC->value,
            ConversionRuleType::PROCEDURE_SPECIFIC->value,
            ConversionRuleType::NAMED_NONLINEAR->value => $this->algorithms->execute(
                (string) $rule->named_algorithm,
                $quantity->value,
                $context,
                $rule,
                $reverse
            ),
            default => throw ConversionException::ruleMissing($from->public_id, $to->public_id),
        };

        $result = $this->math->round($raw, (int) $rule->display_precision, (string) $rule->rounding_mode);

        return new ConversionResult(
            sourceValue: $quantity->value,
            sourceUnitPublicId: $from->public_id,
            targetValue: $result,
            targetUnitPublicId: $to->public_id,
            rulePublicId: $rule->public_id,
            ruleVersion: (int) $rule->version_no,
            displayPrecision: (int) $rule->display_precision,
            roundingMode: (string) $rule->rounding_mode,
        );
    }

    /**
     * @return Collection<int, ConversionRule>
     */
    protected function effectiveCandidates(
        string $tenantKey,
        CoreUnit $from,
        CoreUnit $to,
        DateTimeImmutable $at,
    ): Collection {
        $system = (string) config('units.system_tenant_key', 'SYSTEM');

        return ConversionRule::query()
            ->with('contexts')
            ->whereIn('tenant_key', [$tenantKey, $system])
            ->active()
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($direct) use ($from, $to) {
                    $direct->where('from_unit_id', $from->id)->where('to_unit_id', $to->id);
                })->orWhere(function ($reverse) use ($from, $to) {
                    $reverse->where('is_bidirectional', true)
                        ->where('from_unit_id', $to->id)
                        ->where('to_unit_id', $from->id);
                });
            })
            ->where('effective_from', '<=', $at->format('Y-m-d H:i:s'))
            ->where(function ($q) use ($at) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>', $at->format('Y-m-d H:i:s'));
            })
            ->orderByDesc('version_no')
            ->get();
    }

    /**
     * @param  Collection<int, ConversionRule>  $candidates
     */
    protected function selectRule(
        Collection $candidates,
        ConversionContext $context,
        bool $sameDimension,
        CoreUnit $from,
        CoreUnit $to,
    ): ConversionRule {
        if ($candidates->isEmpty()) {
            if (! $sameDimension) {
                throw ConversionException::dimensionMismatch(
                    DimensionVector::from($from->dimension_vector)->normalized(),
                    DimensionVector::from($to->dimension_vector)->normalized(),
                );
            }

            throw ConversionException::ruleMissing($from->public_id, $to->public_id);
        }

        $contextPairs = $context->candidates();
        $matched = $candidates->filter(function (ConversionRule $rule) use ($contextPairs) {
            $ruleContexts = $rule->contexts;

            if ($ruleContexts->isEmpty()) {
                // Physical / non-contextual rule.
                return empty($contextPairs)
                    || in_array($rule->rule_type, [
                        ConversionRuleType::SCALE->value,
                        ConversionRuleType::AFFINE->value,
                    ], true);
            }

            if (empty($contextPairs)) {
                return false;
            }

            foreach ($ruleContexts as $ctx) {
                $expected = $contextPairs[$ctx->context_type] ?? null;
                if ($expected === null || $expected !== $ctx->context_public_id) {
                    return false;
                }
            }

            return true;
        })->values();

        if ($matched->isEmpty()) {
            $needsItem = $candidates->contains(fn (ConversionRule $r) => $r->contexts->contains('context_type', 'ITEM'));
            if ($needsItem && ! $context->itemPublicId) {
                throw ConversionException::contextRequired('ITEM');
            }

            $needsAnalyte = $candidates->contains(fn (ConversionRule $r) => $r->contexts->contains('context_type', 'ANALYTE'));
            if ($needsAnalyte && ! $context->analytePublicId) {
                throw ConversionException::contextRequired('ANALYTE');
            }

            if (! $sameDimension) {
                throw ConversionException::dimensionMismatch(
                    DimensionVector::from($from->dimension_vector)->normalized(),
                    DimensionVector::from($to->dimension_vector)->normalized(),
                );
            }

            throw ConversionException::ruleMissing($from->public_id, $to->public_id);
        }

        // Prefer tenant-specific over SYSTEM, then highest version.
        $preferred = $matched->sortBy([
            fn (ConversionRule $r) => $r->tenant_key === $context->tenantKey ? 0 : 1,
            fn (ConversionRule $r) => -1 * (int) $r->version_no,
        ])->values();

        $top = $preferred->first();
        $ties = $preferred->filter(fn (ConversionRule $r) => $r->tenant_key === $top->tenant_key
            && (int) $r->version_no === (int) $top->version_no);

        if ($ties->count() > 1) {
            throw ConversionException::ambiguousRule($from->public_id, $to->public_id);
        }

        return $top;
    }

    protected function assertRange(string $value, ConversionRule $rule): void
    {
        if ($rule->min_input_decimal !== null && $this->math->compare($value, (string) $rule->min_input_decimal) < 0) {
            throw ConversionException::outOfRange($value);
        }

        if ($rule->max_input_decimal !== null && $this->math->compare($value, (string) $rule->max_input_decimal) > 0) {
            throw ConversionException::outOfRange($value);
        }
    }

    protected function applyAffine(string $value, ConversionRule $rule, bool $reverse): string
    {
        $scale = (string) $rule->scale_decimal;
        $offset = (string) ($rule->offset_decimal ?? '0');
        $calc = (int) $rule->calculation_scale;

        // Forward: y = ax + b. Reverse: x = (y - b) / a
        if ($reverse) {
            return $this->math->divide(
                $this->math->add($value, $this->math->multiply($offset, '-1', $calc), $calc),
                $scale,
                $calc
            );
        }

        return $this->math->add(
            $this->math->multiply($value, $scale, $calc),
            $offset,
            $calc
        );
    }
}
