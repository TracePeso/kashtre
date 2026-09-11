<?php

namespace App\Domain\Units\Services;

use App\Domain\Units\Exceptions\ConversionException;
use App\Domain\Units\Models\ModuleUnitPolicy;
use App\Domain\Units\ValueObjects\ConversionContext;
use App\Domain\Units\ValueObjects\ConversionResult;
use App\Domain\Units\ValueObjects\Quantity;

/**
 * LIMS module adapter: convert quantities with method/analyte context.
 */
final class LimsUnitGateway
{
    public function __construct(
        private readonly ConversionEngine $engine,
        private readonly UnitCatalogService $catalog,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('units.enabled', false);
    }

    public function tenantKey(?int $businessId): string
    {
        return $this->catalog->tenantKeyForBusiness($businessId);
    }

    /**
     * @return array{quantity: string, source: string, result?: ConversionResult, policy?: ModuleUnitPolicy}
     */
    public function convert(
        string|float|int $value,
        string $fromUnitPublicId,
        string $toUnitPublicId,
        ?int $businessId = null,
        ?string $analytePublicId = null,
        ?string $methodPublicId = null,
    ): array {
        if (! $this->enabled()) {
            return ['quantity' => (string) $value, 'source' => 'engine_disabled'];
        }

        $tenant = $this->tenantKey($businessId);

        try {
            $result = $this->engine->convert(
                new Quantity($this->decimal($value), $fromUnitPublicId),
                $toUnitPublicId,
                new ConversionContext(
                    tenantKey: $tenant,
                    moduleCode: 'LIMS',
                    analytePublicId: $analytePublicId,
                    methodPublicId: $methodPublicId,
                )
            );

            $policy = null;
            if ($methodPublicId) {
                $policy = ModuleUnitPolicy::query()
                    ->where('tenant_key', $tenant)
                    ->where('module_code', 'LIMS')
                    ->where('domain_object_type', 'METHOD')
                    ->where('domain_object_public_id', $methodPublicId)
                    ->where('status', 'ACTIVE')
                    ->with('unit')
                    ->first();
            }

            return [
                'quantity' => $result->targetValue,
                'source' => 'unit_engine',
                'result' => $result,
                'policy' => $policy,
            ];
        } catch (ConversionException $e) {
            if (config('units.strict')) {
                throw $e;
            }

            return [
                'quantity' => (string) $value,
                'source' => 'lims_fallback_'.$e->errorCode,
            ];
        }
    }

    protected function decimal(string|float|int $value): string
    {
        if (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', trim($value))) {
            return trim($value);
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0';
    }
}
