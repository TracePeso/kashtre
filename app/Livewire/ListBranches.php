<?php

namespace App\Livewire;

use App\Models\Branch;
use App\Models\Business;
use App\Support\SharedTime;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ListBranches extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $query = Branch::query()->with('business')->where('business_id', '!=', 1)->latest();

        // Restrict to current business unless super admin
        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->where('business_id', Auth::user()->business_id);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('uuid')
                    ->label('UUID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Branch Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),

                Tables\Columns\TextColumn::make('address')
                    ->searchable(),

                Tables\Columns\TextColumn::make('timezone')
                    ->label('Timezone')
                    ->state(function (Branch $record): string {
                        return SharedTime::describe((string) $record->business_id, (string) $record->id)['ianaId'];
                    })
                    ->description(function (Branch $record): string {
                        return SharedTime::describe((string) $record->business_id, (string) $record->id)['sourceLabel'];
                    })
                    ->badge()
                    ->color(fn (Branch $record): string => SharedTime::describe((string) $record->business_id, (string) $record->id)['inherited']
                        ? 'gray'
                        : 'warning'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                ...(Auth::check() && Auth::user()->business_id === 1 ? [
                    Tables\Filters\SelectFilter::make('business_id')
                        ->label('Filter by Business')
                        ->options(Business::pluck('name', 'id'))
                        ->searchable()
                        ->multiple(),
                ] : []),
            ])
            ->actions([
                Tables\Actions\Action::make('timezone')
                    ->label('Timezone')
                    ->icon('heroicon-o-clock')
                    ->color('primary')
                    ->fillForm(function (Branch $record): array {
                        $context = SharedTime::describe((string) $record->business_id, (string) $record->id);

                        return [
                            'timezone' => $context['inherited'] ? null : $context['ianaId'],
                        ];
                    })
                    ->form([
                        Select::make('timezone')
                            ->label('Timezone')
                            ->helperText('Leave blank to inherit the business timezone. Choose a zone to override this branch.')
                            ->placeholder('Inherit from business')
                            ->options(SharedTime::timezoneSelectOptions())
                            ->searchable()
                            ->nullable(),
                    ])
                    ->action(function (Branch $record, array $data): void {
                        SharedTime::assignBranchTimezone(
                            $record,
                            $data['timezone'] ?? null,
                            empty($data['timezone'])
                                ? 'Branch set back to inherit from business'
                                : 'Branch timezone override from Manage Branches',
                        );

                        Notification::make()
                            ->title(empty($data['timezone']) ? 'Branch now inherits the business timezone' : 'Branch timezone override saved')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public function render(): View
    {
        return view('livewire.list-branches');
    }
}
