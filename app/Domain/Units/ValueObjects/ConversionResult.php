<?php

namespace App\Domain\Units\ValueObjects;

final readonly class ConversionResult
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $sourceValue,
        public string $sourceUnitPublicId,
        public string $targetValue,
        public string $targetUnitPublicId,
        public string $rulePublicId,
        public int $ruleVersion,
        public int $displayPrecision,
        public string $roundingMode,
        public array $warnings = [],
        public string $status = 'SUCCESS',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'source' => [
                'value' => $this->sourceValue,
                'unitPublicId' => $this->sourceUnitPublicId,
            ],
            'target' => [
                'value' => $this->targetValue,
                'unitPublicId' => $this->targetUnitPublicId,
            ],
            'provenance' => [
                'rulePublicId' => $this->rulePublicId,
                'ruleVersion' => $this->ruleVersion,
                'roundingMode' => $this->roundingMode,
                'displayPrecision' => $this->displayPrecision,
            ],
            'warnings' => $this->warnings,
        ];
    }
}
