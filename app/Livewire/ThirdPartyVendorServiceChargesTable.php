<?php

namespace App\Livewire;

use App\Models\ThirdPartyVendorServiceCharge;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ThirdPartyVendorServiceChargesTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use Tables\Concerns\InteractsWithTable;

    public function table(Table $table): Table
    {
        $user = Auth::user();

        $query = ThirdPartyVendorServiceCharge::query()
            ->where('business_id', '!=', 1)
            ->with(['business', 'createdBy', 'insuranceCompany']);

        if ((int) ($user->business_id ?? 0) !== 1) {
            $query->where('business_id', (int) $user->business_id);
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('business.name')
                    ->label('Business')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('insuranceCompany.name')
                    ->label('Applies to')
                    ->formatStateUsing(function (?string $state, ThirdPartyVendorServiceCharge $record): string {
                        if ($record->insurance_company_id === null) {
                            return 'All third-party vendors';
                        }

                        return $state ? (string) $state : 'Vendor #'.$record->insurance_company_id;
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('lower_bound')
                    ->label('Lower bound')
                    ->formatStateUsing(fn (ThirdPartyVendorServiceCharge $record): string => 'UGX '.number_format((float) $record->lower_bound, 2))
                    ->sortable(),

                TextColumn::make('upper_bound')
                    ->label('Upper bound')
                    ->formatStateUsing(function (ThirdPartyVendorServiceCharge $record): string {
                        return $record->upper_bound !== null
                            ? 'UGX '.number_format((float) $record->upper_bound, 2)
                            : 'No limit';
                    })
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Charge')
                    ->formatStateUsing(fn (ThirdPartyVendorServiceCharge $record): string => $record->formatted_amount)
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'percentage' => 'success',
                        'fixed' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),

                TextColumn::make('createdBy.name')
                    ->label('Created by')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Action::make('edit_schedule')
                    ->label('Edit schedule')
                    ->icon('heroicon-o-pencil')
                    ->visible(fn () => (int) (Auth::user()->business_id ?? 0) === 1
                        || in_array('Manage Service Charges', Auth::user()->permissions ?? []))
                    ->url(function (ThirdPartyVendorServiceCharge $record): string {
                        $url = route('third-party-vendor-service-charges.edit', $record->business);
                        if ($record->insurance_company_id !== null) {
                            $url .= '?insurance_company_id='.$record->insurance_company_id;
                        }

                        return $url;
                    }),

                DeleteAction::make()
                    ->label('Delete tier')
                    ->visible(fn () => (int) (Auth::user()->business_id ?? 0) === 1
                        || in_array('Manage Service Charges', Auth::user()->permissions ?? []))
                    ->requiresConfirmation(),
            ])
            ->defaultSort('business_id')
            ->paginated([10, 25, 50]);
    }

    public function makeFilamentTranslatableContentDriver(): ?\Filament\Support\Contracts\TranslatableContentDriver
    {
        return null;
    }

    public function render()
    {
        return view('livewire.third-party-vendor-service-charges-table');
    }
}
