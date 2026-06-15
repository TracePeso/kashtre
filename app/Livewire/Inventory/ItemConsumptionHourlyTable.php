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

class ItemConsumptionHourlyTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public int $itemId;

    public int $storeId;

    public string $date;

    public function table(Table $table): Table
    {
        $businessId = (int) Auth::user()->business_id;

        return $table
            ->query(
                app(InventoryConsumptionQueryService::class)
                    ->hourlyBreakdownQuery($businessId, $this->storeId, $this->itemId, $this->date)
            )
            ->columns([
                TextColumn::make('hour')
                    ->label('Hour')
                    ->sortable()
                    ->formatStateUsing(function ($state): string {
                        $hour = (int) $state;
                        $start = Carbon::parse($this->date)->setTime($hour, 0);

                        return $start->format('g:i A').' – '.$start->copy()->addHour()->subMinute()->format('g:i A');
                    }),

                TextColumn::make('quantity_suom')
                    ->label('Consumed (SUOM)')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),
            ])
            ->defaultSort('hour')
            ->striped()
            ->paginated(false)
            ->emptyStateHeading('No hourly breakdown')
            ->emptyStateDescription('No consumption was recorded for this item on the selected day.');
    }

    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->hour;
    }

    public function render(): View
    {
        return view('livewire.inventory.item-consumption-hourly-table');
    }
}
