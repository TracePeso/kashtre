<?php

namespace App\Livewire\Inventory;

use App\Services\Inventory\InventoryConsumptionQueryService;
use Carbon\Carbon;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ItemConsumptionDailyTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public int $itemId;

    public int $storeId;

    public string $month;

    public function table(Table $table): Table
    {
        $businessId = (int) Auth::user()->business_id;
        $queries = app(InventoryConsumptionQueryService::class);
        [$from, $until] = $queries->monthBounds($this->month);

        return $table
            ->query(
                $queries->dailyBreakdownQuery($businessId, $this->storeId, $this->itemId, $from, $until)
            )
            ->columns([
                TextColumn::make('consumption_date')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('quantity_suom')
                    ->label('Consumed (SUOM)')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),
            ])
            ->recordUrl(fn ($record): string => route('inventory.consumption.day', [
                'item' => $this->itemId,
                'date' => Carbon::parse($record->consumption_date)->toDateString(),
                'store_id' => $this->storeId,
                'month' => $this->month,
            ]))
            ->defaultSort('consumption_date', 'desc')
            ->striped()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No consumption this month')
            ->emptyStateDescription('No daily usage was recorded for this item in the selected month.');
    }

    public function getTableRecordKey(Model $record): string
    {
        return Carbon::parse($record->consumption_date)->toDateString();
    }

    public function render(): View
    {
        return view('livewire.inventory.item-consumption-daily-table');
    }
}
