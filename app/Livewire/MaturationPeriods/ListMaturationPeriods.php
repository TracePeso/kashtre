<?php

namespace App\Livewire\MaturationPeriods;

use App\Models\MaturationPeriod;
use App\Models\Business;
use App\Models\PaymentMethodAccount;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class ListMaturationPeriods extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    private function permissions(): array
    {
        return (array) (auth()->user()->permissions ?? []);
    }

    private function hasSettingsAdminAccess(): bool
    {
        return auth()->check() && auth()->user()->business_id === 1;
    }

    private function can(string $permission): bool
    {
        return in_array($permission, $this->permissions()) || $this->hasSettingsAdminAccess();
    }

    public function table(Table $table): Table
    {
        $query = MaturationPeriod::query()
            ->with(['business', 'paymentMethodAccount', 'createdBy', 'updatedBy'])
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
                    ->formatStateUsing(fn (string $state): string => match($state) {
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
                    ->formatStateUsing(fn (int $state): string => $state . ' day' . ($state > 1 ? 's' : ''))
                    ->sortable(),
                TextColumn::make('paymentMethodAccount.name')
                    ->label('Account')
                    ->formatStateUsing(function ($state, $record) {
                        if (!$state) return '—';
                        $account = $record->paymentMethodAccount;
                        $text = $account->name;
                        if ($account->provider) {
                            $text .= ' (' . $account->provider . ')';
                        }
                        return $text;
                    })
                    ->searchable(),
                TextColumn::make('paymentMethodAccount.balance')
                    ->label('Account Balance')
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record->paymentMethodAccount || $state == 0) return '—';
                        return number_format($state, 2) . ' ' . ($record->paymentMethodAccount->currency ?? 'UGX');
                    })
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
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
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
                    ->visible(fn() => $this->can('View Maturation Periods'))
                    ->url(fn (MaturationPeriod $record): string => route('maturation-periods.show', $record)),
                EditAction::make()
                    ->visible(fn() => $this->can('Edit Maturation Periods'))
                    ->url(fn (MaturationPeriod $record): string => route('maturation-periods.edit', $record))
                    ->color('warning'),
                Action::make('toggleStatus')
                    ->label(fn (MaturationPeriod $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (MaturationPeriod $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (MaturationPeriod $record): string => $record->is_active ? 'danger' : 'success')
                    ->visible(fn() => $this->can('Manage Maturation Periods'))
                    ->requiresConfirmation()
                    ->modalHeading(fn (MaturationPeriod $record): string => $record->is_active ? 'Deactivate Payment Method' : 'Activate Payment Method')
                    ->modalDescription(fn (MaturationPeriod $record): string => 
                        $record->is_active 
                            ? "Are you sure you want to deactivate this payment method? It will no longer be available for client registration until reactivated."
                            : "Are you sure you want to activate this payment method? It will be available for client registration."
                    )
                    ->modalSubmitActionLabel(fn (MaturationPeriod $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->action(function (MaturationPeriod $record) {
                        $record->update([
                            'is_active' => !$record->is_active,
                            'updated_by' => Auth::id(),
                        ]);
                        
                        $status = $record->is_active ? 'activated' : 'deactivated';
                        
                        Notification::make()
                            ->title("Payment method {$status} successfully")
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->visible(fn() => $this->can('Delete Maturation Periods'))
                    ->requiresConfirmation()
                    ->modalHeading('Delete Payment Method')
                    ->modalDescription('Are you sure you want to delete this payment method? This action cannot be undone and will remove it from all client registration options.')
                    ->successNotificationTitle('Payment method deleted successfully.')
                    ->action(function (MaturationPeriod $record) {
                        $record->delete();
                        Notification::make()
                            ->title('Payment method deleted successfully')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn() => $this->can('Delete Maturation Periods')),
                ]),
            ])
            ->headerActions([
                Action::make('create')
                    ->visible(fn() => $this->can('Add Maturation Periods'))
                    ->label('Create Maturation Period')
                    ->icon('heroicon-o-plus')
                    ->url(route('maturation-periods.create'))
                    ->color('success'),
            ])
            ->emptyStateActions([
                Action::make('createEmpty')
                    ->visible(fn() => $this->can('Add Maturation Periods'))
                    ->label('Create Maturation Period')
                    ->icon('heroicon-o-plus')
                    ->url(route('maturation-periods.create'))
                    ->button()
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
        return view('livewire.maturation-periods.list-maturation-periods');
    }
}
