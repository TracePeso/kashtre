<?php

namespace App\Domain\Units\ValueObjects;

final readonly class DimensionVector
{
    /** @var list<string> */
    private const KEYS = ['M', 'L', 'T', 'TEMP', 'N', 'I', 'J', 'COUNT'];

    /** @var array<string, int> */
    public array $powers;

    /**
     * @param  array<string, int>  $powers
     */
    public function __construct(array $powers = [])
    {
        $normalized = [];
        foreach (self::KEYS as $key) {
            $normalized[$key] = (int) ($powers[$key] ?? 0);
        }
        $this->powers = $normalized;
    }

    /**
     * @param  array<string, int>|null  $powers
     */
    public static function from(?array $powers): self
    {
        return new self(is_array($powers) ? $powers : []);
    }

    public function multiply(self $other): self
    {
        $result = [];
        foreach (self::KEYS as $key) {
            $result[$key] = $this->powers[$key] + $other->powers[$key];
        }

        return new self($result);
    }

    public function divide(self $other): self
    {
        $result = [];
        foreach (self::KEYS as $key) {
            $result[$key] = $this->powers[$key] - $other->powers[$key];
        }

        return new self($result);
    }

    public function pow(int $n): self
    {
        $result = [];
        foreach (self::KEYS as $key) {
            $result[$key] = $this->powers[$key] * $n;
        }

        return new self($result);
    }

    public function equals(self $other): bool
    {
        return $this->powers === $other->powers;
    }

    /**
     * @return array<string, int>
     */
    public function normalized(): array
    {
        return $this->powers;
    }
}
