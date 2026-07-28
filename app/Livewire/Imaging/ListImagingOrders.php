<?php

namespace App\Livewire\Imaging;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Client;
use App\Models\ImagingOrder;
use App\Models\ImagingProtocol;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ListImagingOrders extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $query = ImagingOrder::query()->latest();

        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->where('business_id', Auth::user()->business_id);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('client_id')
                    ->label('Client')
                    ->getStateUsing(function (ImagingOrder $record) {
                        $client = $record->resolveClient();

                        return $client ? "{$client->full_name} ({$record->client_id})" : $record->client_id;
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('protocol_code')
                    ->label('Protocol')
                    ->getStateUsing(fn (ImagingOrder $record) => $record->protocol()?->name ?? $record->protocol_code)
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        ImagingOrder::STATUS_PENDING => 'warning',
                        ImagingOrder::STATUS_ACCEPTED => 'success',
                        ImagingOrder::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        ImagingOrder::PRIORITY_URGENT => 'danger',
                        ImagingOrder::PRIORITY_HIGH => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('clinical_indication')
                    ->label('Clinical Indication')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ordered At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        ImagingOrder::STATUS_PENDING => 'Pending',
                        ImagingOrder::STATUS_ACCEPTED => 'Accepted',
                        ImagingOrder::STATUS_CANCELLED => 'Cancelled',
                    ]),
                ...(Auth::check() && Auth::user()->business_id === 1 ? [
                    Tables\Filters\SelectFilter::make('business_id')
                        ->label('Filter by Business')
                        ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                        ->searchable()
                        ->multiple(),
                ] : []),
            ])
            ->actions([
                Tables\Actions\Action::make('viewStudy')
                    ->label('View Study')
                    ->icon('heroicon-o-arrow-right')
                    ->visible(fn (ImagingOrder $record) => (bool) $record->imaging_study_id)
                    ->url(fn (ImagingOrder $record) => route('imaging-studies.show', $record->imaging_study_id)),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Imaging Order')
                    ->visible(fn () => in_array('Add Imaging Orders', Auth::user()->permissions ?? []))
                    ->modalHeading('Place Imaging Order')
                    ->form(function () {
                        $businessId = Auth::user()->business_id;

                        return [
                            Forms\Components\Select::make('business_id')
                                ->label('Business')
                                ->placeholder('Select a business')
                                ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                                ->required()
                                ->default($businessId)
                                ->disabled(fn () => Auth::user()->business_id !== 1)
                                ->reactive(),

                            Forms\Components\Select::make('branch_id')
                                ->label('Branch')
                                ->placeholder('Select a branch')
                                ->options(function ($get) {
                                    $businessId = $get('business_id');

                                    return $businessId
                                        ? Branch::where('business_id', $businessId)->pluck('name', 'id')
                                        : [];
                                })
                                ->required(),

                            Forms\Components\Select::make('client_id')
                                ->label('Client')
                                ->placeholder('Search by client ID or name')
                                ->searchable()
                                ->getSearchResultsUsing(function (string $search, $get) {
                                    $businessId = $get('business_id');

                                    if (! $businessId) {
                                        return [];
                                    }

                                    return Client::query()
                                        ->where('business_id', $businessId)
                                        ->where(function ($q) use ($search) {
                                            $q->where('client_id', 'like', "%{$search}%")
                                                ->orWhere('surname', 'like', "%{$search}%")
                                                ->orWhere('first_name', 'like', "%{$search}%");
                                        })
                                        ->limit(20)
                                        ->get()
                                        ->mapWithKeys(fn (Client $client) => [
                                            $client->client_id => "{$client->full_name} ({$client->client_id})",
                                        ]);
                                })
                                ->getOptionLabelUsing(function ($value, $get) {
                                    $client = Client::where('business_id', $get('business_id'))->where('client_id', $value)->first();

                                    return $client ? "{$client->full_name} ({$client->client_id})" : $value;
                                })
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set, $get) {
                                    $client = Client::where('business_id', $get('business_id'))->where('client_id', $state)->first();
                                    $set('visit_id', $client->visit_id ?? null);
                                }),

                            Forms\Components\Hidden::make('visit_id'),

                            Forms\Components\Select::make('protocol_code')
                                ->label('Protocol')
                                ->placeholder('Select a protocol')
                                ->options(function ($get) {
                                    return ImagingProtocol::query()
                                        ->active()
                                        ->availableToBusiness($get('business_id'))
                                        ->pluck('name', 'code');
                                })
                                ->searchable()
                                ->required(),

                            Forms\Components\Textarea::make('clinical_indication')
                                ->label('Clinical Indication')
                                ->placeholder('Reason for the study')
                                ->rows(3),

                            Forms\Components\Select::make('priority')
                                ->label('Priority')
                                ->options([
                                    ImagingOrder::PRIORITY_LOW => 'Low',
                                    ImagingOrder::PRIORITY_NORMAL => 'Normal',
                                    ImagingOrder::PRIORITY_HIGH => 'High',
                                    ImagingOrder::PRIORITY_URGENT => 'Urgent',
                                ])
                                ->default(ImagingOrder::PRIORITY_NORMAL)
                                ->required(),
                        ];
                    })
                    ->mutateFormDataUsing(function (array $data) {
                        $data['ordering_user_id'] = Auth::id();

                        // The Business select is disabled (not just visually
                        // read-only) for non-super-admins, and Filament does
                        // not dehydrate disabled fields — its value never
                        // reaches here otherwise, and business_id has no DB
                        // default, so the insert fails. Force it server-side
                        // rather than trusting a client-submitted value for a
                        // disabled field either way.
                        if (Auth::user()->business_id !== 1) {
                            $data['business_id'] = Auth::user()->business_id;
                        }

                        return $data;
                    })
                    ->createAnother(false)
                    ->after(function () {
                        Notification::make()
                            ->title('Imaging order placed successfully.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function render(): View
    {
        return view('livewire.imaging.list-imaging-orders');
    }
}
