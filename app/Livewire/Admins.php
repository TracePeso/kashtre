<?php

namespace App\Livewire;

use App\Livewire\Concerns\RemovesTwoFactorAuthentication;
use App\Models\User;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Illuminate\Contracts\View\View;

class Admins extends Component implements HasForms, HasTable
{
    use RemovesTwoFactorAuthentication;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->where('business_id', 1)->latest()->with('business'))
            ->columns([
                Tables\Columns\ImageColumn::make('profile_photo_url')
                    ->label('Profile Photo')
                    ->circular()
                    ->defaultImageUrl(url('path/to/default/image.jpg')),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business Name')
                    ->searchable()
                    ->sortable()
                    ->default('N/A'),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch Name')
                    ->searchable()
                    ->sortable()
                    ->default('N/A'),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('show')
                    ->label('Show')
                    // ->visible(fn() => in_array('View Admin Users', Auth::user()->permissions))
                    ->url(fn(User $record): string => route('admins.show', $record->id))
                    ->icon('heroicon-o-eye')
                    ->color('info'),

                // Tables\Actions\Action::make('edit')
                //     ->label('Edit')
                //     ->url(fn(User $record): string => route('admins.edit', $record->id))
                //     ->icon('heroicon-o-pencil')
                //     ->color('primary'),
                Tables\Actions\Action::make('update_status')
                    ->label('Change Status')
                    ->visible(fn() => in_array('Update Status', Auth::user()->permissions))
                    ->form([
                        \Filament\Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'suspended' => 'Suspended',
                            ])
                            ->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update(['status' => $data['status']]);
                    })
                    ->icon('heroicon-o-pencil')
                    ->color('primary'),
                $this->removeTwoFactorTableAction(),
                Tables\Actions\Action::make('impersonate')
                    ->label('Impersonate')
                    ->visible(fn() => in_array('Impersonate', Auth::user()->permissions))
                    ->url(fn (User $record): string => route('impersonate', $record->id))
                    ->color('warning')
                    ->icon('heroicon-o-user')
                    ->visible(fn (User $record): bool => Auth::user()->id !== $record->id)
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('update_status_bulk')
                        ->label('Update Status')
                        ->visible(fn() => in_array('Update Status', Auth::user()->permissions))
                        ->form([
                            \Filament\Forms\Components\Select::make('status')
                                ->options([
                                    'active' => 'Active',
                                    'inactive' => 'Inactive',
                                    'suspended' => 'Suspended',
                                ])
                                ->required(),
                        ])
                        ->action(function (array $records, array $data): void {
                            User::whereIn('id', $records)->update(['status' => $data['status']]);
                        })
                        ->icon('heroicon-o-pencil')
                        ->color('primary'),
                ]),
            ]);
    }

    public function render(): View
    {
        return view('livewire.admins');
    }
}
