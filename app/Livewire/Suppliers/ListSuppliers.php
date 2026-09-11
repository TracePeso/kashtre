<?php

namespace App\Livewire\Suppliers;

use App\Models\Supplier;
use App\Models\Business;
use App\Models\SupplierIndustry;
use App\Models\SupplierSubCategory;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;
use Illuminate\Contracts\View\View;


class ListSuppliers extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $query = Supplier::query()
            ->with(['linkedBusiness', 'industry', 'subCategory'])
            ->where('business_id', '!=', 1)
            ->latest();

        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->where('business_id', Auth::user()->business_id);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->description(fn (Supplier $record): ?string => $record->isKashtreEntitySupplier()
                        ? 'Kashtre entity: ' . ($record->linkedBusiness?->name ?? '—')
                        : null),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('industry.name')
                    ->label('Industry')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('subCategory.name')
                    ->label('Sub category')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable(),
                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->sortable()
                    ->searchable(),
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
                TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('supplier_industry_id')
                    ->label('Industry')
                    ->options(function (): array {
                        $query = SupplierIndustry::query()->orderBy('name');

                        if (Auth::user()->business_id !== 1) {
                            $query->where('business_id', Auth::user()->business_id);
                        }

                        return $query->pluck('name', 'id')->all();
                    })
                    ->searchable()
                    ->multiple(),
                Tables\Filters\SelectFilter::make('supplier_sub_category_id')
                    ->label('Sub category')
                    ->options(function (): array {
                        $query = SupplierSubCategory::query()->orderBy('name');

                        if (Auth::user()->business_id !== 1) {
                            $query->where('business_id', Auth::user()->business_id);
                        }

                        return $query->pluck('name', 'id')->all();
                    })
                    ->searchable()
                    ->multiple(),
                ...(Auth::check() && Auth::user()->business_id === 1 ? [
                    Tables\Filters\SelectFilter::make('business_id')
                        ->label('Filter by Business')
                        ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                        ->searchable()
                        ->multiple(),
                ] : []),
            ])
            ->actions([
                EditAction::make()
                    ->label('Edit Supplier')
                    ->visible(fn () => in_array('Edit Suppliers', Auth::user()->permissions))
                    ->modalHeading('Edit Supplier')
                    ->fillForm(fn (Supplier $record): array => array_merge($record->toArray(), [
                        'linked_business_id' => $record->linked_business_id,
                    ]))
                    ->form(fn (Supplier $record) => $this->supplierForm())
                    ->using(function (Supplier $record, array $data): Supplier {
                        $data = $this->normalizeSupplierData($data);
                        $record->update($data);

                        return $record;
                    })
                    ->successNotificationTitle('Supplier updated successfully.'),
                DeleteAction::make()
                    ->visible(fn() => in_array('Delete Suppliers', Auth::user()->permissions))
                    ->modalHeading('Delete Supplier')
                    ->successNotificationTitle('Supplier deleted (soft) successfully.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn () => in_array('Add Suppliers', Auth::user()->permissions))
                    ->label('Create Supplier')
                    ->modalHeading('Add New Supplier')
                    ->form($this->supplierForm())
                    ->using(function (array $data): Supplier {
                        $data = $this->normalizeSupplierData($data);

                        return Supplier::create($data);
                    })
                    ->createAnother(false)
                    ->after(function () {
                        Notification::make()
                            ->title('Supplier created successfully.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    /** @return array<int, Forms\Components\Component> */
    private function supplierForm(): array
    {
        return [
            Forms\Components\Select::make('business_id')
                ->label('Business')
                ->placeholder('Select a business')
                ->options(Business::kashtreEntities()->pluck('name', 'id'))
                ->required()
                ->default(Auth::user()->business_id)
                ->disabled(fn () => Auth::user()->business_id !== 1)
                ->live()
                ->afterStateUpdated(function (Forms\Set $set): void {
                    $set('supplier_industry_id', null);
                    $set('supplier_sub_category_id', null);
                }),
            Forms\Components\Select::make('supplier_industry_id')
                ->label('Industry')
                ->placeholder('Select an industry')
                ->options(fn (Get $get): array => SupplierIndustry::query()
                    ->when($get('business_id'), fn ($query, $id) => $query->where('business_id', (int) $id))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->nullable()
                ->searchable()
                ->live()
                ->disabled(fn (Get $get): bool => blank($get('business_id')))
                ->afterStateUpdated(fn (Forms\Set $set) => $set('supplier_sub_category_id', null)),
            Forms\Components\Select::make('supplier_sub_category_id')
                ->label('Sub category')
                ->placeholder('Select a sub category')
                ->options(fn (Get $get): array => SupplierSubCategory::query()
                    ->when($get('business_id'), fn ($query, $id) => $query->where('business_id', (int) $id))
                    ->when($get('supplier_industry_id'), fn ($query, $id) => $query->where('supplier_industry_id', (int) $id))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->nullable()
                ->searchable()
                ->disabled(fn (Get $get): bool => blank($get('supplier_industry_id'))),
            Forms\Components\Select::make('linked_business_id')
                ->label('Kashtre entity supplier')
                ->placeholder('Manual supplier (not linked to a Kashtre entity)')
                ->options(fn (Get $get): array => app(\App\Services\KashtreEntityService::class)
                    ->registeredSuppliersQuery()
                    ->when($get('business_id'), fn ($query, $id) => $query->where('id', '!=', (int) $id))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set): void {
                    if (! $state) {
                        return;
                    }

                    $linked = Business::query()
                        ->registeredAsSupplier()
                        ->find((int) $state);

                    if (! $linked) {
                        return;
                    }

                    $set('supplier_industry_id', $linked->supplier_industry_id);
                    $set('supplier_sub_category_id', $linked->supplier_sub_category_id);
                })
                ->helperText('Optional. Pick a Kashtre entity registered as a supplier, or leave blank for an external supplier.'),
            Forms\Components\TextInput::make('name')
                ->label('Supplier Name')
                ->required()
                ->disabled(fn (Get $get): bool => filled($get('linked_business_id'))),
            Forms\Components\TextInput::make('email')
                ->label('Email')
                ->email()
                ->nullable()
                ->disabled(fn (Get $get): bool => filled($get('linked_business_id')))
                ->helperText('Used to send RFQs and LPOs electronically.'),
            Forms\Components\TextInput::make('phone')
                ->label('Phone')
                ->tel()
                ->nullable()
                ->disabled(fn (Get $get): bool => filled($get('linked_business_id'))),
            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->nullable(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeSupplierData(array $data): array
    {
        if (empty($data['supplier_industry_id'])) {
            $data['supplier_industry_id'] = null;
            $data['supplier_sub_category_id'] = null;
        } else {
            $industry = SupplierIndustry::query()->find((int) $data['supplier_industry_id']);

            if (! $industry
                || (isset($data['business_id']) && (int) $industry->business_id !== (int) $data['business_id'])) {
                $data['supplier_industry_id'] = null;
                $data['supplier_sub_category_id'] = null;
            } elseif (empty($data['supplier_sub_category_id'])) {
                $data['supplier_sub_category_id'] = null;
            } else {
                $subCategory = SupplierSubCategory::query()->find((int) $data['supplier_sub_category_id']);

                if (! $subCategory
                    || (int) $subCategory->supplier_industry_id !== (int) $data['supplier_industry_id']
                    || (isset($data['business_id']) && (int) $subCategory->business_id !== (int) $data['business_id'])) {
                    $data['supplier_sub_category_id'] = null;
                }
            }
        }

        if (empty($data['linked_business_id'])) {
            $data['linked_business_id'] = null;

            return $data;
        }

        $linked = Business::query()->registeredAsSupplier()->find((int) $data['linked_business_id']);

        if (! $linked) {
            $data['linked_business_id'] = null;

            return $data;
        }

        $data['name'] = $linked->name;
        $data['email'] = $linked->email;
        $data['phone'] = $linked->phone;
        $data['supplier_industry_id'] = $linked->supplier_industry_id;
        $data['supplier_sub_category_id'] = $linked->supplier_sub_category_id;

        return $data;
    }

    public function render(): View
    {
        return view('livewire.suppliers.list-suppliers');
    }
}
