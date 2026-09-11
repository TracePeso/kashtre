<?php

namespace App\Livewire\Imaging;

use App\Models\Business;
use App\Models\ImagingStudy;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ListImagingStudies extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $query = ImagingStudy::query()
            ->whereNotIn('status', [ImagingStudy::STATUS_VERIFIED])
            ->latest();

        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->where('business_id', Auth::user()->business_id);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('accession_number')
                    ->label('Accession #')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('client_id')
                    ->label('Client')
                    ->getStateUsing(function (ImagingStudy $record) {
                        $client = $record->resolveClient();

                        return $client ? "{$client->full_name} ({$record->client_id})" : $record->client_id;
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('protocol_code')
                    ->label('Protocol')
                    ->getStateUsing(fn (ImagingStudy $record) => $record->protocol()?->name ?? $record->protocol_code),

                Tables\Columns\TextColumn::make('main_room_id')
                    ->label('Room')
                    ->getStateUsing(fn (ImagingStudy $record) => $record->resolveRoom()?->name ?? '—'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        ImagingStudy::STATUS_ORDER_RECEIVED, ImagingStudy::STATUS_PREPARATION_REQUIRED => 'gray',
                        ImagingStudy::STATUS_PREPARATION_COMPLETE, ImagingStudy::STATUS_READY_FOR_STUDY => 'warning',
                        ImagingStudy::STATUS_IN_PROGRESS => 'info',
                        ImagingStudy::STATUS_IMAGE_ACQUIRED, ImagingStudy::STATUS_REPORT_PENDING => 'primary',
                        ImagingStudy::STATUS_REPORTED => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => (string) str($state)->replace('_', ' ')->title())
                    ->sortable(),

                Tables\Columns\IconColumn::make('consent_verified')
                    ->label('Consent')
                    ->boolean(),

                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        ImagingStudy::PRIORITY_URGENT => 'danger',
                        ImagingStudy::PRIORITY_HIGH => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ordered At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ImagingStudy::STATUSES)
                        ->reject(fn ($s) => $s === ImagingStudy::STATUS_VERIFIED)
                        ->mapWithKeys(fn ($s) => [$s => (string) str($s)->replace('_', ' ')->title()])
                        ->all()),
                ...(Auth::check() && Auth::user()->business_id === 1 ? [
                    Tables\Filters\SelectFilter::make('business_id')
                        ->label('Filter by Business')
                        ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                        ->searchable()
                        ->multiple(),
                ] : []),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-right')
                    ->url(fn (ImagingStudy $record) => route('imaging-studies.show', $record)),
            ]);
    }

    public function render(): View
    {
        return view('livewire.imaging.list-imaging-studies');
    }
}
