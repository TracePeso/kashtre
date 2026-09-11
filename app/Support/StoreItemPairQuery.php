<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StoreItemPairQuery
{
    /**
     * Filter by (store_id, item_id) tuples — Laravel whereIn does not support nested arrays.
     *
     * @param  Collection<int, array{store_id: int, item_id: int}>  $pairs
     */
    public static function whereInPairs(Builder $query, Collection $pairs, string $storeColumn, string $itemColumn): Builder
    {
        if ($pairs->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        $placeholders = [];
        $bindings = [];

        foreach ($pairs as $pair) {
            $placeholders[] = '(?, ?)';
            $bindings[] = (int) $pair['store_id'];
            $bindings[] = (int) $pair['item_id'];
        }

        return $query->whereRaw(
            "({$storeColumn}, {$itemColumn}) in (".implode(', ', $placeholders).')',
            $bindings
        );
    }
}
