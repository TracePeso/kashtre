<?php

namespace App\Support;

use App\Models\Item;
use Illuminate\Support\Collection;

class ConsumptionItemMatcher
{
    /** @var array<string, Item> */
    private array $exact = [];

    /** @var Collection<int, Item> */
    private Collection $items;

    public function __construct(Collection $items)
    {
        $this->items = $items;

        foreach ($items as $item) {
            $this->exact[$this->normalize($item->name)] = $item;
        }
    }

    public function match(string $spreadsheetName): ?Item
    {
        $key = $this->normalize($spreadsheetName);

        if (isset($this->exact[$key])) {
            return $this->exact[$key];
        }

        $best = null;
        $bestScore = PHP_INT_MAX;

        foreach ($this->items as $item) {
            $candidate = $this->normalize($item->name);

            if ($candidate === $key) {
                return $item;
            }

            if (str_contains($candidate, $key) || str_contains($key, $candidate)) {
                $score = levenshtein($key, $candidate);

                if ($score < $bestScore) {
                    $bestScore = $score;
                    $best = $item;
                }
            }
        }

        if ($best !== null && $bestScore <= max(8, (int) (strlen($key) * 0.35))) {
            return $best;
        }

        foreach ($this->items as $item) {
            $candidate = $this->normalize($item->name);
            $score = levenshtein($key, $candidate);

            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        return $best !== null && $bestScore <= 4 ? $best : null;
    }

    private function normalize(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9]+/', '', $name) ?? '';

        return $name;
    }
}
