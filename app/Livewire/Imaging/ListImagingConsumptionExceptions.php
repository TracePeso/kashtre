<?php

namespace App\Livewire\Imaging;

use App\Models\Business;
use App\Models\ImagingConsumptionException;
use App\Services\Imaging\ConsumptionAttributionService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * RIS Amendment v2.6, Chunk 6: Imaging > Consumption Exceptions —
 * operational follow-up list (not Settings, same placement rule as
 * Contrast Vials) for consumption attempts RadiologyRecipeEngine couldn't
 * actually deplete (no store resolved, or the resolved store had no
 * stock), so they don't just sit silent in the log.
 */
class ListImagingConsumptionExceptions extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $businessId = Auth::user()->business_id;

        $query = ImagingConsumptionException::query()->with(['study', 'protocolWorkflowStep.workflowStep'])->latest();

        if ($businessId !== 1) {
            $query->whereHas('study', fn ($q) => $q->where('business_id', $businessId));
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('study.accession_number')
                    ->label('Accession #')
                    ->searchable(),

                Tables\Columns\TextColumn::make('client')
                    ->label('Client')
                    ->getStateUsing(function (ImagingConsumptionException $record) {
                        $client = $record->study?->resolveClient();

                        return $client ? "{$client->full_name} ({$record->study->client_id})" : $record->study?->client_id;
                    }),

                Tables\Columns\TextColumn::make('step')
                    ->label('Workflow Step')
                    ->getStateUsing(fn (ImagingConsumptionException $record) => $record->protocolWorkflowStep?->workflowStep?->step_name ?? '—'),

                Tables\Columns\TextColumn::make('exception_reason')
                    ->label('Reason')
                    ->wrap()
                    ->limit(80),

                Tables\Columns\TextColumn::make('resolved')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Resolved' : 'Unresolved')
                    ->color(fn (bool $state) => $state ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('resolvedBy')
                    ->label('Resolved By')
                    ->getStateUsing(fn (ImagingConsumptionException $record) => $record->resolveResolvedByUser()?->name ?? '—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Occurred At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('resolved')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Resolved')
                    ->falseLabel('Unresolved'),
                ...($businessId === 1 ? [
                    Tables\Filters\SelectFilter::make('business_id')
                        ->label('Filter by Business')
                        ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                        ->searchable()
                        ->multiple()
                        ->query(function ($query, array $data) {
                            $businessIds = $data['values'] ?? [];

                            if (empty($businessIds)) {
                                return $query;
                            }

                            return $query->whereHas('study', fn ($q) => $q->whereIn('business_id', $businessIds));
                        }),
                ] : []),
            ])
            ->actions([
                Tables\Actions\Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Mark this consumption exception as resolved — confirm the stock situation has actually been addressed (restocked, store reconfigured, etc.) before doing this.')
                    ->visible(fn (ImagingConsumptionException $record) => ! $record->resolved
                        && in_array('Resolve Consumption Exceptions', Auth::user()->permissions ?? []))
                    ->action(function (ImagingConsumptionException $record) {
                        app(ConsumptionAttributionService::class)->resolveException($record, (int) Auth::id());

                        Notification::make()
                            ->title('Consumption exception resolved.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('viewStudy')
                    ->label('View Study')
                    ->icon('heroicon-o-arrow-right')
                    ->visible(fn (ImagingConsumptionException $record) => (bool) $record->imaging_study_id)
                    ->url(fn (ImagingConsumptionException $record) => route('imaging-studies.show', $record->imaging_study_id)),
            ]);
    }

    public function render(): View
    {
        return view('livewire.imaging.list-imaging-consumption-exceptions');
    }
}
