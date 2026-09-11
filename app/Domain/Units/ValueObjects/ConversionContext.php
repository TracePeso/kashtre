<?php

namespace App\Domain\Units\ValueObjects;

use DateTimeImmutable;

final readonly class ConversionContext
{
    public function __construct(
        public string $tenantKey,
        public ?string $moduleCode = null,
        public ?string $analytePublicId = null,
        public ?string $itemPublicId = null,
        public ?string $methodPublicId = null,
        public ?string $procedurePublicId = null,
        public ?DateTimeImmutable $effectiveAt = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function candidates(): array
    {
        return array_filter([
            'ANALYTE' => $this->analytePublicId,
            'ITEM' => $this->itemPublicId,
            'METHOD' => $this->methodPublicId,
            'PROCEDURE' => $this->procedurePublicId,
        ]);
    }
}
