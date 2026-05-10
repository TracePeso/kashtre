<?php

namespace App\Livewire\MaturationPeriods;

use App\Models\Business;
use App\Models\ServiceChargeMaturationPeriod;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ListServiceChargeMaturationPeriods extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $query = ServiceChargeMaturationPeriod::query()
            ->with(['business', 'createdBy', 'updatedBy'])
            ->where('business_id', '!=', 1)
            ->latest();

        if (auth()->check() && auth()->user()->business_id !== 1) {
            $query->where('business_id', auth()->user()->business_id);
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('business.name')
                    ->label('Business')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('payment_method')
                    ->label('Payment Method')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'insurance' => 'Insurance',
                        'credit_arrangement' => 'Credit Arrangement',
                        'mobile_money' => 'Mobile Money',
                        'v_card' => 'V Card (Virtual Card)',
                        'p_card' => 'P Card (Physical Card)',
                        'bank_transfer' => 'Bank Transfer',
                        'cash' => 'Cash',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->sortable()
                    ->searchable(),
                TextColumn::make('maturation_days')
                    ->label('Maturation Days')
                    ->formatStateUsing(fn (int $state): string => $state.' day'.($state > 1 ? 's' : ''))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->description)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                ...((auth()->check() && auth()->user()->business_id === 1) ? [
                    SelectFilter::make('business_id')
                        ->label('Business')
                        ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                        ->searchable()
                        ->multiple(),
                ] : []),
                SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'insurance' => 'Insurance',
                        'credit_arrangement' => 'Credit Arrangement',
                        'mobile_money' => 'Mobile Money',
                        'v_card' => 'V Card (Virtual Card)',
                        'p_card' => 'P Card (Physical Card)',
                        'bank_transfer' => 'Bank Transfer',
                        'cash' => 'Cash',
                    ])
                    ->multiple(),
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        true => 'Active',
                        false => 'Inactive',
                    ]),
            ])
            ->actions([
                ViewAction::make()
                    ->visible(fn () => in_array('View Maturation Periods', auth()->user()->permissions ?? []))
                    ->url(fn (ServiceChargeMaturationPeriod $record): string => route('service-charge-maturation-periods.show', $record)),
                EditAction::make()
                    ->visible(fn () => in_array('Edit Maturation Periods', auth()->user()->permissions ?? []))
                    ->url(fn (ServiceChargeMaturationPeriod $record): string => route('service-charge-maturation-periods.edit', $record))
                    ->color('warning'),
                Action::make('toggleStatus')
                    ->label(fn (ServiceChargeMaturationPeriod $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (ServiceChargeMaturationPeriod $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (ServiceChargeMaturationPeriod $record): string => $record->is_active ? 'danger' : 'success')
                    ->visible(fn () => in_array('Manage Maturation Periods', auth()->user()->permissions ?? []))
                    ->requiresConfirmation()
                    ->action(function (ServiceChargeMaturationPeriod $record) {
                        $record->update([
                            'is_active' => ! $record->is_active,
                            'updated_by' => Auth::id(),
                        ]);
                        $status = $record->is_active ? 'activated' : 'deactivated';
                        Notification::make()
                            ->title("Service charge maturation {$status}")
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->visible(fn () => in_array('Delete Maturation Periods', auth()->user()->permissions ?? []))
                    ->requiresConfirmation()
                    ->successNotificationTitle('Deleted.')
                    ->action(function (ServiceChargeMaturationPeriod $record) {
                        $record->delete();
                        Notification::make()
                            ->title('Service charge maturation period deleted')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => in_array('Delete Maturation Periods', auth()->user()->permissions ?? [])),
                ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn () => in_array('Add Maturation Periods', auth()->user()->permissions ?? []))
                    ->label('Create service charge maturation')
                    ->url(route('service-charge-maturation-periods.create'))
                    ->color('success'),
            ])
            ->defaultSort('business_id')
            ->poll('30s');
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    public function render(): View
    {
        return view('livewire.maturation-periods.list-service-charge-maturation-periods');
    }
}
