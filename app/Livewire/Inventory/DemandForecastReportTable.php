<?php

namespace App\Livewire\Inventory;

use App\Livewire\Inventory\Concerns\InteractsWithInventoryMetrics;
use App\Models\InventoryStockLevel;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DemandForecastReportTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithInventoryMetrics;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $businessId = (int) Auth::user()->business_id;
        $config = $this->moduleConfigFor($businessId);
        $analytics = $this->metricsService();
        $periodDays = (float) ($config?->period_of_order_days ?? 30);

        return $table
            ->query($this->inventoryReportQuery($businessId))
            ->columns([
                TextColumn::make('item_name')->label('Item')->searchable()->sortable(),
                TextColumn::make('item_code')->label('Code')->searchable(),
                TextColumn::make('store_name')->label('Store')->sortable(),
                TextColumn::make('daily_avg')
                    ->label('Daily avg (V/AA)')
                    ->alignEnd()
                    ->state(fn (InventoryStockLevel $record): float => $analytics->excelDailyUsageSuom($record, $config))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 4)),
                TextColumn::make('order_qty')
                    ->label('Order qty (AF)')
                    ->alignEnd()
                    ->state(fn (InventoryStockLevel $record): float => $analytics->suggestedOrderQtyPeriod($record, $config, $periodDays))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0)),
                TextColumn::make('forecast_amount')
                    ->label('Forecast UGX (AG)')
                    ->alignEnd()
                    ->state(function (InventoryStockLevel $record) use ($analytics, $config, $periodDays): float {
                        $qty = $analytics->suggestedOrderQtyPeriod($record, $config, $periodDays);

                        return $analytics->demandForecastAmountUgx($record, $config, $qty, $record->item);
                    })
                    ->formatStateUsing(fn ($state) => 'UGX '.number_format((float) $state, 2)),
                TextColumn::make('test_amount')
                    ->label('15-day test (AH)')
                    ->alignEnd()
                    ->toggleable()
                    ->state(fn (InventoryStockLevel $record): float => $analytics->budgetTestAmountUgx($record, $config, $record->item))
                    ->formatStateUsing(fn ($state) => 'UGX '.number_format((float) $state, 2)),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'name', fn (Builder $query) => $query->where('business_id', $businessId)),
            ])
            ->defaultSort('item_name')
            ->striped()
            ->paginated([25, 50, 100]);
    }

    public function render(): View
    {
        return view('livewire.inventory.demand-forecast-report-table');
    }
}
