<?php

namespace App\Livewire;

use App\Models\PackageTracking;
use App\Models\Client;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Select;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PackageTrackingTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use Tables\Concerns\InteractsWithTable;

    public function mount()
    {
        // Initialize any required data
    }

    public function table(Table $table): Table
    {
        $user = Auth::user();
        
        $query = PackageTracking::query()
            ->where('business_id', $user->business_id)
            ->with(['client', 'invoice', 'packageItem', 'trackingItems.includedItem']);
        
        return $table
            ->query($query)
            ->columns([
                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->formatStateUsing(function ($record) {
                        return $record->client->name ?? 'Unknown';
                    }),
                
                TextColumn::make('client.phone_number')
                    ->label('Phone')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        return $record->client->phone_number ?? 'N/A';
                    }),
                
                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        return $record->invoice->invoice_number ?? 'N/A';
                    }),
                
                TextColumn::make('tracking_number')
                    ->label('Tracking #')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        return $record->tracking_number ?? "PKG-" . $record->id . "-" . $record->created_at->format("YmdHis");
                    }),
                
                TextColumn::make('packageItem.name')
                    ->label('Package')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        return $record->packageItem->name ?? 'Unknown';
                    }),
                
                
                TextColumn::make('total_quantity')
                    ->label('Total Qty')
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        return $record->total_quantity;
                    }),
                
                TextColumn::make('remaining_quantity')
                    ->label('Remaining')
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        return $record->remaining_quantity;
                    })
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger'),
                
                BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state)))
                    ->colors([
                        'success' => 'active',
                        'warning' => 'expired',
                        'danger' => 'fully_used',
                        'gray' => 'cancelled',
                    ]),
                
                TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        return $record->valid_until->format('M d, Y');
                    }),
                
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'expired' => 'Expired',
                        'fully_used' => 'Fully Used',
                        'cancelled' => 'Cancelled',
                    ]),
                
                SelectFilter::make('client_id')
                    ->label('Client')
                    ->options(function () {
                        return Client::where('business_id', Auth::user()->business_id)
                            ->pluck('name', 'id')
                            ->toArray();
                    }),
                
                Filter::make('created_at')
                    ->label('Created Date')
                    ->form([
                        Select::make('date_filter')
                            ->label('Filter by Date')
                            ->options([
                                'today' => 'Today',
                                'yesterday' => 'Yesterday',
                                'this_week' => 'This Week',
                                'this_month' => 'This Month',
                                'last_month' => 'Last Month',
                            ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['date_filter'] ?? null) {
                            'today' => $query->whereOperationalPeriod('created_at', 'today'),
                            'yesterday' => $query->whereOperationalPeriod('created_at', 'yesterday'),
                            'this_week' => $query->whereOperationalPeriod('created_at', 'this_week'),
                            'this_month' => $query->whereOperationalPeriod('created_at', 'this_month'),
                            'last_month' => $query->whereOperationalPeriod('created_at', 'last_month'),
                            default => $query,
                        };
                    })
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->url(fn (PackageTracking $record): string => route('package-tracking.show', $record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public function makeFilamentTranslatableContentDriver(): ?\Filament\Support\Contracts\TranslatableContentDriver
    {
        return null;
    }

    public function render()
    {
        return view('livewire.package-tracking-table');
    }
}
