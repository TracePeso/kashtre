<?php

namespace App\Livewire\Inventory\Concerns;

use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

trait WarmsInventoryFilamentTable
{
    use InteractsWithTable {
        paginateTableQuery as filamentPaginateTableQuery;
    }
    use WarmsInventoryTableMetrics;

    protected function paginateTableQuery(Builder $query): Paginator
    {
        $paginator = $this->filamentPaginateTableQuery($query);

        $this->warmTablePageMetrics($this->stockLevelsFromPaginator($paginator));

        return $paginator;
    }
}
