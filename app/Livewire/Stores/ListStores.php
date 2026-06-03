<?php

namespace App\Livewire\Stores;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Store;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ListStores extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $query = Store::query()
            ->with(['business', 'branch', 'parent'])
            ->withCount('children')
            ->where('business_id', '!=', 1)
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name');

        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->where('business_id', Auth::user()->business_id);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Store')
                    ->searchable()
                    ->description(fn (Store $record): ?string => $record->isChild() && $record->parent
                        ? 'Child of ' . $record->parent->name
                        : ($record->children_count > 0 ? $record->children_count . ' child store(s)' : null)),
                Tables\Columns\TextColumn::make('hierarchy')
                    ->label('Type')
                    ->badge()
                    ->state(fn (Store $record): string => $record->isChild() ? 'Child' : 'Parent')
                    ->color(fn (Store $record): string => $record->isChild() ? 'info' : 'success'),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent store')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
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
                Tables\Filters\SelectFilter::make('hierarchy')
                    ->label('Store type')
                    ->options([
                        'parent' => 'Parent stores',
                        'child' => 'Child stores',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'parent' => $query->whereNull('parent_id'),
                            'child' => $query->whereNotNull('parent_id'),
                            default => $query,
                        };
                    }),
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
                    ->label('Edit')
                    ->visible(fn () => in_array('Edit Stores', Auth::user()->permissions ?? []))
                    ->modalHeading(fn (Store $record) => $record->isChild() ? 'Edit Child Store' : 'Edit Parent Store')
                    ->form(fn (Store $record) => $this->storeForm($record))
                    ->successNotificationTitle('Store updated successfully.'),
                DeleteAction::make()
                    ->visible(fn () => in_array('Delete Stores', Auth::user()->permissions ?? []))
                    ->before(function (Store $record) {
                        if ($record->children()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot delete parent store')
                                ->body('Reassign or delete child stores first.')
                                ->send();

                            $this->halt();
                        }
                    })
                    ->successNotificationTitle('Store deleted successfully.'),
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
                    ->visible(fn () => in_array('Add Stores', Auth::user()->permissions ?? []))
                    ->label('Create Parent Store')
                    ->modalHeading('Add Parent Store')
                    ->modalDescription('Top-level store. You can add child stores under it afterwards.')
                    ->form($this->parentStoreForm())
                    ->mutateFormDataUsing(fn (array $data) => array_merge($data, ['parent_id' => null]))
                    ->createAnother(false)
                    ->after(fn () => $this->notifyCreated()),
                CreateAction::make('createChildStore')
                    ->visible(fn () => in_array('Add Stores', Auth::user()->permissions ?? []))
                    ->label('Create Child Store')
                    ->modalHeading('Add Child Store')
                    ->modalDescription('Links to a parent store and inherits its business and branch.')
                    ->form($this->childStoreForm())
                    ->createAnother(false)
                    ->after(fn () => $this->notifyCreated()),
            ]);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function parentStoreForm(): array
    {
        return [
            Forms\Components\Select::make('business_id')
                ->label('Business')
                ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                ->required()
                ->default(Auth::user()->business_id)
                ->disabled(fn () => Auth::user()->business_id !== 1)
                ->live(),
            Forms\Components\Select::make('branch_id')
                ->label('Branch')
                ->options(fn (Get $get) => $this->branchOptions($get('business_id')))
                ->required()
                ->searchable(),
            Forms\Components\TextInput::make('name')
                ->label('Store name')
                ->required()
                ->maxLength(255),
            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->nullable()
                ->rows(2),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function childStoreForm(): array
    {
        return [
            Forms\Components\Select::make('parent_id')
                ->label('Parent store')
                ->options(fn () => $this->parentStoreOptions())
                ->required()
                ->searchable()
                ->helperText('Child stores share the parent’s business and branch.'),
            Forms\Components\TextInput::make('name')
                ->label('Child store name')
                ->required()
                ->maxLength(255),
            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->nullable()
                ->rows(2),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function storeForm(Store $record): array
    {
        if ($record->isChild()) {
            return [
                Forms\Components\Select::make('parent_id')
                    ->label('Parent store')
                    ->options(fn () => $this->parentStoreOptions($record->id))
                    ->required()
                    ->searchable()
                    ->disabled($record->children()->exists()),
                Forms\Components\TextInput::make('name')
                    ->label('Store name')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->nullable(),
            ];
        }

        return [
            Forms\Components\Select::make('business_id')
                ->label('Business')
                ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                ->required()
                ->disabled(fn () => Auth::user()->business_id !== 1)
                ->live(),
            Forms\Components\Select::make('branch_id')
                ->label('Branch')
                ->options(fn (Get $get) => $this->branchOptions($get('business_id') ?: $record->business_id))
                ->required()
                ->searchable(),
            Forms\Components\TextInput::make('name')
                ->label('Store name')
                ->required(),
            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->nullable(),
            Forms\Components\Placeholder::make('children_note')
                ->label('Child stores')
                ->content(fn () => $record->children()->count() > 0
                    ? $record->children()->count() . ' child store(s) linked to this parent.'
                    : 'No child stores yet. Use “Create Child Store” to add one.'),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected function parentStoreOptions(?int $excludeId = null): array
    {
        $query = Store::query()
            ->roots()
            ->where('business_id', '!=', 1)
            ->orderBy('name');

        if (Auth::user()->business_id !== 1) {
            $query->where('business_id', Auth::user()->business_id);
        }

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->pluck('name', 'id')->all();
    }

    /**
     * @return array<int|string, string>
     */
    protected function branchOptions(?int $businessId): array
    {
        if (! $businessId) {
            return [];
        }

        return Branch::where('business_id', $businessId)->orderBy('name')->pluck('name', 'id')->all();
    }

    protected function notifyCreated(): void
    {
        Notification::make()
            ->title('Store created successfully.')
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('livewire.stores.list-stores');
    }
}
