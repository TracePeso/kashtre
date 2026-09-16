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
use Livewire\Component;
use Illuminate\Contracts\View\View;
use App\Models\Business;
use Illuminate\Support\Facades\Auth;

class ListUsers extends Component implements HasForms, HasTable
{
    use RemovesTwoFactorAuthentication;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $query = User::query()->where('business_id', '!=', 1)->latest()->with('business');

        // Restrict users based on authenticated user's business_id
        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->where('business_id', Auth::user()->business_id);
        }

        return $table
            ->query($query)
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
                //add branch column if needed
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
            ->filters([
                ...(Auth::check() && Auth::user()->business_id === 1 ? [
                    Tables\Filters\SelectFilter::make('business')
                        ->relationship('business', 'name')
                        ->label('Business')
                        ->preload()
                        ->searchable()
                        ->multiple(),
                ] : []),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                    ])
                    ->label('Status')
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\Action::make('show')
                    ->label('Show')
                    ->url(fn(User $record): string => route('users.show', $record->id))
                    ->icon('heroicon-o-eye')
                    ->color('info'),
                // Tables\Actions\Action::make('edit')
                //     ->label('Edit')
                //     ->url(fn(User $record): string => route('users.edit', $record->id))
                //     ->icon('heroicon-o-pencil')
                //     ->color('primary'),
                Tables\Actions\Action::make('update_status')
                    ->label('Change Status')
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
                        if (Auth::user()->business_id === 1 || $record->business_id === Auth::user()->business_id) {
                            $record->update(['status' => $data['status']]);
                        } else {
                            abort(403, 'Unauthorized action.');
                        }
                    })
                    ->icon('heroicon-o-pencil')
                    ->color('primary')
                    ->visible(fn (User $record): bool => Auth::user()->business_id === 1 || $record->business_id === Auth::user()->business_id),
                $this->removeTwoFactorTableAction(),
                // Tables\Actions\Action::make('impersonate')
                //     ->label('Impersonate')
                //     ->url(fn (User $record): string => route('impersonate', $record->id))
                //     ->color('warning')
                //     ->icon('heroicon-o-user')
                //     ->visible(fn (User $record): bool => Auth::user()->business_id === 1 && Auth::user()->id !== $record->id)
                //     ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('update_status_bulk')
                        ->label('Update Status')
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
                            $userIds = collect($records)->filter(function ($recordId) {
                                $user = User::find($recordId);
                                return Auth::user()->business_id === 1 || $user->business_id === Auth::user()->business_id;
                            })->pluck('id');

                            if ($userIds->isNotEmpty()) {
                                User::whereIn('id', $userIds)->update(['status' => $data['status']]);
                            } else {
                                abort(403, 'Unauthorized action.');
                            }
                        })
                        ->icon('heroicon-o-pencil')
                        ->color('primary'),
                ]),
            ]);
    }

    public function render(): View
    {
        return view('livewire.list-users');
    }
}