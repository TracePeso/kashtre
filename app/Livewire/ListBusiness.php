<?php

namespace App\Livewire;

use App\Models\Business;
use App\Support\BusinessBranding;
use App\Support\SupplierCategorySelection;
use App\Models\SupplierSubCategory;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;


class ListBusiness extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $query = Business::query()
            ->kashtreEntities()
            ->with(['supplierIndustry', 'supplierSubCategory'])
            ->withCount([
                'users as active_staff_count' => fn ($q) => $q->where('status', 'active'),
            ])
            ->with('inventoryModuleConfig:id,business_id,is_active')
            ->latest();

        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->where('id', Auth::user()->business_id);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                    ->label('Logo')
                    ->circular()
                    // ->defaultImageUrl(url('path/to/default/image.jpg'))
                    ,
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('address')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('account_number')
                    ->label('Kashtre ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('active_staff_count')
                    ->label('Active staff')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->description('Signals current platform use'),
                Tables\Columns\IconColumn::make('inventory_module_active')
                    ->label('Inventory')
                    ->state(fn (Business $record): bool => (bool) $record->inventoryModuleConfig?->is_active)
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('entity_code')
                    ->label('Entity code')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('registered_as_supplier')
                    ->label('Kashtre supplier')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (Business $record): string => $record->isRegisteredAsSupplier()
                        ? 'Registered as a supplier — other entities can link this organisation in procurement.'
                        : 'Not registered as a network supplier.')
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplierIndustry.name')
                    ->label('Supplier industry')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('supplierSubCategory.name')
                    ->label('Supplier sub category')
                    ->placeholder('—')
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
                Tables\Filters\TernaryFilter::make('registered_as_supplier')
                    ->label('Kashtre supplier')
                    ->placeholder('All entities')
                    ->trueLabel('Registered as supplier')
                    ->falseLabel('Not registered as supplier'),
                Tables\Filters\TernaryFilter::make('actively_utilizing')
                    ->label('Active use')
                    ->placeholder('All entities')
                    ->trueLabel('Has active staff')
                    ->falseLabel('No active staff')
                    ->queries(
                        true: fn ($query) => $query->activelyUtilizing(),
                        false: fn ($query) => $query->whereDoesntHave('users', fn ($q) => $q->where('status', 'active')),
                    ),
                ...(Auth::check() && Auth::user()->business_id === 1 ? [
                    Tables\Filters\SelectFilter::make('name')
                        ->label('Business')
                        ->options(Business::pluck('name', 'id'))
                        ->searchable()
                        ->multiple(),
                ] : []),


            ])
            ->actions([
                Tables\Actions\Action::make('supplier_registration')
                    ->label('Supplier profile')
                    ->modalHeading('Supplier registration')
                    ->modalDescription('Register this entity as a network supplier and classify it by industry and sub category.')
                    ->modalSubmitActionLabel('Save')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->visible(fn (): bool => Auth::user()->business_id === 1)
                    ->fillForm(fn (Business $record): array => [
                        'registered_as_supplier' => $record->registered_as_supplier,
                        'supplier_industry_id' => $record->supplier_industry_id,
                        'supplier_sub_category_id' => $record->supplier_sub_category_id,
                    ])
                    ->form([
                        Forms\Components\Toggle::make('registered_as_supplier')
                            ->label('Register as a supplier')
                            ->helperText('Other organisations can link this entity in procurement.')
                            ->live(),
                        Forms\Components\Select::make('supplier_industry_id')
                            ->label('Industry')
                            ->placeholder('Select industry')
                            ->options(SupplierCategorySelection::industryOptions())
                            ->searchable()
                            ->required(fn (Get $get): bool => (bool) $get('registered_as_supplier'))
                            ->visible(fn (Get $get): bool => (bool) $get('registered_as_supplier'))
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('supplier_sub_category_id', null)),
                        Forms\Components\Select::make('supplier_sub_category_id')
                            ->label('Sub category')
                            ->placeholder('Select sub category')
                            ->options(fn (Get $get): array => SupplierSubCategory::query()
                                ->when($get('supplier_industry_id'), fn ($query, $id) => $query->where('supplier_industry_id', (int) $id))
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (SupplierSubCategory $subCategory): array => [
                                    $subCategory->id => $subCategory->name . ' (' . ($subCategory->business?->name ?? 'Entity') . ')',
                                ])
                                ->all())
                            ->searchable()
                            ->required(fn (Get $get): bool => (bool) $get('registered_as_supplier'))
                            ->visible(fn (Get $get): bool => (bool) $get('registered_as_supplier'))
                            ->disabled(fn (Get $get): bool => blank($get('supplier_industry_id'))),
                    ])
                    ->action(function (Business $record, array $data): void {
                        $registered = (bool) ($data['registered_as_supplier'] ?? false);
                        $normalized = SupplierCategorySelection::normalize($registered, $data);

                        $record->update([
                            'registered_as_supplier' => $registered,
                            'supplier_industry_id' => $normalized['supplier_industry_id'],
                            'supplier_sub_category_id' => $normalized['supplier_sub_category_id'],
                        ]);

                        Notification::make()
                            ->title('Supplier profile saved')
                            ->success()
                            ->body($registered
                                ? 'This entity is registered as a network supplier.'
                                : 'Supplier registration removed for this entity.')
                            ->send();
                    }),

                Tables\Actions\Action::make('entity_code')
                    ->label('Entity code')
                    ->modalHeading('Procurement entity code')
                    ->modalDescription('Used as the prefix on LPO numbers (e.g. KCH-LPO-20260712-001).')
                    ->modalSubmitActionLabel('Save')
                    ->fillForm(fn (Business $record): array => [
                        'entity_code' => $record->entity_code,
                    ])
                    ->form([
                        \Filament\Forms\Components\TextInput::make('entity_code')
                            ->label('Entity code')
                            ->maxLength(16)
                            ->helperText('Letters and numbers only; stored uppercase.'),
                    ])
                    ->action(function (Business $record, array $data): void {
                        if (! (Auth::user()->business_id === 1 || $record->id === Auth::user()->business_id)) {
                            abort(403, 'Unauthorized action.');
                        }

                        $raw = preg_replace('/[^A-Za-z0-9]/', '', (string) ($data['entity_code'] ?? '')) ?? '';
                        $code = strtoupper($raw);
                        $record->update(['entity_code' => $code !== '' ? $code : null]);

                        Notification::make()
                            ->title('Entity code saved')
                            ->success()
                            ->body($code !== '' ? "LPO numbers will use prefix {$code}-" : 'Entity code cleared; LPO numbers use the default LPO- prefix.')
                            ->send();
                    })
                    ->icon('heroicon-o-building-office-2')
                    ->color('gray')
                    ->visible(fn (Business $record): bool => Auth::user()->business_id === 1 || $record->id === Auth::user()->business_id),

                Tables\Actions\Action::make('update_logo')
                    ->label('Update Logo')
                    ->modalHeading('Update Business Logo')
                    ->modalSubmitActionLabel('Save')
                    ->form(fn (Business $record) => [
                        FileUpload::make('logo')
                            ->label('Upload Logo')
                            ->image()
                            ->preserveFilenames()
                            ->directory(BusinessBranding::logoDirectoryFor($record))
                            ->disk('public')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'])
                            ->maxSize(2048)
                            ->required(),
                    ])
                    ->action(function (Business $record, array $data): void {
                        if (Auth::user()->business_id === 1 || $record->id === Auth::user()->business_id) {
                            if (!empty($data['logo'])) {
                                $branding = $record->branding();
                                $branding->deleteStoredLogo();
                                $record->update(['logo' => $data['logo']]);
                                Notification::make()
                                    ->title('Logo Updated')
                                    ->success()
                                    ->body('The business logo was successfully updated.')
                                    ->send();
                            } else {
                                Log::error('No logo file provided in the upload.');
                                Notification::make()
                                    ->title('Upload Failed')
                                    ->danger()
                                    ->body('No file was uploaded.')
                                    ->send();
                            }
                        } else {
                            abort(403, 'Unauthorized action.');
                        }
                    })

                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->visible(fn(Business $record): bool => Auth::user()->business_id === 1),
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([...])
            ]);
    }

    public function render(): View
    {
        return view('livewire.list-business');
    }
}
