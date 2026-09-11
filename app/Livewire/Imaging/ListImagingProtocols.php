<?php

namespace App\Livewire\Imaging;

use App\Models\Business;
use App\Models\ImagingModality;
use App\Models\ImagingProtocol;
use App\Models\ImagingReadinessCheckType;
use App\Models\Item;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Settings > Manage Imaging Protocols — admin configuration for the Protocol
 * Engine, replacing the seeder-only workflow. Consumables and the default
 * contrast agent reference real Items from the existing catalog (resolved
 * the same way RadiologyRecipeEngine already resolves consumables_recipe by
 * Item code); Preparation Requirements / Readiness Checks pick from the
 * ImagingReadinessCheckType master list instead of free-typed tags.
 */
class ListImagingProtocols extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    /**
     * code => name for every active modality (business-scoped when relevant),
     * plus each code's own is_ionizing flag — replaces the previous hardcoded
     * MODALITY_OPTIONS const so a new modality can be added via Settings >
     * Imaging Module Settings > Manage Imaging Modalities with no code change.
     */
    protected function modalityOptions(?int $businessId): array
    {
        return ImagingModality::query()
            ->active()
            ->availableToBusiness($businessId)
            ->pluck('name', 'code')
            ->toArray();
    }

    protected function modalityIonizingFlags(): array
    {
        return ImagingModality::query()
            ->active()
            ->pluck('is_ionizing', 'code')
            ->toArray();
    }

    /**
     * Scoped to the given business when one is known (a regular business's
     * own catalog, or a super-admin editing a business-specific protocol).
     * With no business_id — a super-admin editing a system-wide protocol —
     * search searches the whole catalog, since items.code is globally
     * unique and there's no single "right" business to scope to.
     */
    protected function itemOptions(?string $search, ?int $businessId): array
    {
        return Item::query()
            ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
            ->where('type', 'good')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            }))
            ->limit(20)
            ->pluck('name', 'code')
            ->toArray();
    }

    protected function resolveItemByCode($businessId, ?string $code): ?Item
    {
        return Item::query()
            ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
            ->where('code', $code)
            ->first();
    }

    /**
     * Same scoping as itemOptions(), but keyed by id — for
     * default_contrast_item_id, which stores Item.id (a plain indexed
     * reference), not the code string consumables_recipe stores.
     */
    protected function itemIdOptions(?string $search, ?int $businessId): array
    {
        return Item::query()
            ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
            ->where('type', 'good')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            }))
            ->limit(20)
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function formFields(): array
    {
        $businessId = Auth::user()->business_id;

        return [
            Forms\Components\Select::make('business_id')
                ->label('Business')
                ->placeholder('Select a business')
                ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->default($businessId !== 1 ? $businessId : null)
                ->disabled(fn () => $businessId !== 1)
                ->reactive(),

            Forms\Components\TextInput::make('code')
                ->label('Protocol Code')
                ->placeholder('e.g. CT-CHEST-CONTRAST')
                ->required()
                ->maxLength(255)
                ->unique(table: ImagingProtocol::class, column: 'code', ignoreRecord: true),

            Forms\Components\TextInput::make('name')
                ->label('Name')
                ->placeholder('e.g. CT Chest (Contrast)')
                ->required()
                ->maxLength(255),

            Forms\Components\Select::make('modality_type')
                ->label('Modality')
                ->options(fn ($get) => $this->modalityOptions($get('business_id')))
                ->searchable()
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    // Prefill (not lock) the ionizing toggle from the newly
                    // picked modality's own flag — still freely overridable,
                    // same prefill-but-editable philosophy as the contrast/kVp
                    // defaults elsewhere in this form.
                    $set('involves_ionizing_radiation', (bool) ($this->modalityIonizingFlags()[$state] ?? false));
                }),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->extraAttributes(['class' => 'kt-toggle']),

            Forms\Components\Toggle::make('requires_consent')
                ->label('Requires Consent')
                ->extraAttributes(['class' => 'kt-toggle']),

            Forms\Components\Toggle::make('is_contrast_enhanced')
                ->label('Contrast Enhanced')
                ->reactive()
                ->extraAttributes(['class' => 'kt-toggle']),

            Forms\Components\Toggle::make('requires_recovery')
                ->label('Requires Recovery / Post-Procedure Monitoring')
                ->extraAttributes(['class' => 'kt-toggle']),

            Forms\Components\Toggle::make('involves_ionizing_radiation')
                ->label('Involves Ionizing Radiation')
                ->helperText('Turns on radiation-dose logging (kVp, dose area product, exposure time) on the study page for this protocol.')
                ->reactive()
                ->extraAttributes(['class' => 'kt-toggle']),

            Forms\Components\Select::make('preparation_requirements')
                ->label('Preparation Requirements')
                ->multiple()
                ->searchable()
                ->options(fn ($get) => ImagingReadinessCheckType::query()
                    ->active()
                    ->category(ImagingReadinessCheckType::CATEGORY_PREPARATION)
                    ->availableToBusiness($get('business_id'))
                    ->pluck('label', 'code'))
                ->helperText('Managed under Settings > Manage Imaging Readiness Checks.'),

            Forms\Components\Select::make('readiness_checks')
                ->label('Readiness Checks')
                ->multiple()
                ->searchable()
                ->options(fn ($get) => ImagingReadinessCheckType::query()
                    ->active()
                    ->category(ImagingReadinessCheckType::CATEGORY_READINESS)
                    ->availableToBusiness($get('business_id'))
                    ->pluck('label', 'code'))
                ->helperText('Managed under Settings > Manage Imaging Readiness Checks.'),

            // A plain-scalar ("simple") Repeater rather than a TagsInput —
            // functionally the same flat array of section-name strings
            // (mutateFormData/mutateRecordData need no changes), but adds
            // drag-and-drop reordering (on by default for Repeater), which
            // TagsInput has no way to do. Defaults to the two sections
            // every radiology report format uses, still freely editable —
            // add, remove, rename, or reorder as needed per protocol.
            Forms\Components\Repeater::make('reporting_sections')
                ->label('Reporting Template Sections')
                ->simple(
                    Forms\Components\TextInput::make('name')
                        ->placeholder('e.g. Impression')
                        ->required()
                )
                ->default(['Findings', 'Impression'])
                ->addActionLabel('Add Section')
                // Drag-and-drop reordering needs Filament's own JS (Sortable.js,
                // shipped as part of the core Filament asset bundle) — this app's
                // layout doesn't load that bundle (Forms/Tables used standalone,
                // no Filament Panel to register it), so drag-and-drop silently
                // doesn't work here. Up/down buttons are plain Livewire clicks —
                // no extra JS dependency, so they actually function.
                ->reorderableWithDragAndDrop(false)
                ->reorderableWithButtons()
                ->helperText('Use the arrows to reorder. These become the report sections radiologists fill in.'),

            // Some protocols don't follow the standard 9-step status flow —
            // this section lets a facility skip the Preparation phase for a
            // specific protocol. Everything else in the flow (Ready For
            // Study's PACS worklist broadcast, the Report Pending recovery/
            // discharge gate, In Progress, Image Acquired, Reported,
            // Verified) always stays mandatory regardless of this setting —
            // only Preparation is safe to make optional, since the others
            // either trigger real integrations or are structurally required
            // by the report/consumption lifecycle.
            Forms\Components\Section::make('Status Flow')
                ->description("Configure which status steps this protocol needs — for protocols that don't follow the standard flow.")
                ->schema([
                    Forms\Components\Toggle::make('requires_preparation')
                        ->label('Requires Preparation Phase')
                        ->default(true)
                        ->extraAttributes(['class' => 'kt-toggle'])
                        ->helperText('When off, studies for this protocol skip Start Preparation / Preparation Complete entirely and jump from Order Received straight to Ready For Study. Readiness checks and consent still apply.'),
                ]),

            Forms\Components\Repeater::make('consumables_recipe')
                ->label('Consumables Recipe')
                ->schema([
                    Forms\Components\Select::make('sku')
                        ->label('Item')
                        ->placeholder('Search the item catalog')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search, $get) => $this->itemOptions($search, $get('../../business_id')))
                        ->getOptionLabelUsing(function ($value, $get) {
                            $item = $this->resolveItemByCode($get('../../business_id'), $value);

                            return $item ? "{$item->name} ({$item->code})" : $value;
                        })
                        ->required(),

                    Forms\Components\TextInput::make('qty')
                        ->label('Quantity')
                        ->numeric()
                        ->minValue(0.01)
                        ->default(1)
                        ->required(),
                ])
                ->columns(2)
                ->defaultItems(0)
                ->addActionLabel('Add Consumable')
                ->helperText('Consumed automatically from real Inventory stock at the trigger point configured below.'),

            Forms\Components\Select::make('consumption_trigger_status')
                ->label('Deduct Consumables At')
                ->options([
                    ImagingProtocol::TRIGGER_PREPARATION_COMPLETE => 'Preparation Complete',
                    ImagingProtocol::TRIGGER_IN_PROGRESS => 'In Progress',
                    ImagingProtocol::TRIGGER_IMAGE_ACQUIRED => 'Image Acquired',
                    ImagingProtocol::TRIGGER_RECOVERY_COMPLETE => 'Recovery Complete',
                ])
                ->searchable()
                ->default(ImagingProtocol::TRIGGER_IMAGE_ACQUIRED)
                ->required()
                ->rule(function ($get) {
                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                        // If Preparation is skipped for this protocol, markPreparationComplete()
                        // never runs, so consumption configured to fire there would
                        // silently never deduct — reject the combination outright
                        // rather than letting inventory quietly go unaccounted for.
                        if (! $get('requires_preparation') && $value === ImagingProtocol::TRIGGER_PREPARATION_COMPLETE) {
                            $fail("Can't deduct consumables at Preparation Complete when this protocol skips the Preparation phase (see Status Flow above).");
                        }
                    };
                })
                ->helperText('Most protocols use Image Acquired; interventional/sedation protocols with a recovery phase often use Recovery Complete instead.'),

            Forms\Components\Select::make('default_contrast_item_id')
                ->label('Default Contrast Agent')
                ->placeholder('Search the item catalog')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search, $get) => $this->itemIdOptions($search, $get('business_id')))
                ->getOptionLabelUsing(function ($value, $get) {
                    $item = Item::query()
                        ->when($get('business_id'), fn ($q) => $q->where('business_id', $get('business_id')))
                        ->find($value);

                    return $item?->name ?? $value;
                })
                ->visible(fn ($get) => (bool) $get('is_contrast_enhanced'))
                ->helperText('Prefills the Contrast Agent Name field on the study page — technicians can still override it.'),

            Forms\Components\TextInput::make('default_contrast_volume_ml')
                ->label('Default Contrast Volume (mL)')
                ->numeric()
                ->minValue(0.01)
                ->visible(fn ($get) => (bool) $get('is_contrast_enhanced')),

            Forms\Components\TextInput::make('default_kvp_metrics')
                ->label('Default kVp')
                ->placeholder('e.g. 120 kVp')
                ->visible(fn ($get) => (bool) $get('involves_ionizing_radiation')),
        ];
    }

    protected function mutateFormData(array $data): array
    {
        $businessId = Auth::user()->business_id;

        // The Business select is disabled (not just visually read-only) for
        // non-super-admins, and Filament does not dehydrate disabled fields —
        // force it server-side rather than trusting a client-submitted value.
        if ($businessId !== 1) {
            $data['business_id'] = $businessId;
        }

        $data['reporting_template'] = ['sections' => $data['reporting_sections'] ?? []];
        unset($data['reporting_sections']);

        return $data;
    }

    protected function mutateRecordData(array $data): array
    {
        $data['reporting_sections'] = $data['reporting_template']['sections'] ?? [];

        return $data;
    }

    public function table(Table $table): Table
    {
        $businessId = Auth::user()->business_id;

        $query = ImagingProtocol::query()->latest();

        if ($businessId !== 1) {
            $query->availableToBusiness($businessId);
        }

        // Resolved once per table render (not per row) — includes inactive
        // modalities too, so an existing protocol referencing one that's
        // since been deactivated still displays its name correctly.
        $modalityNames = ImagingModality::pluck('name', 'code')->toArray();

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('modality_type')
                    ->label('Modality')
                    ->formatStateUsing(fn (string $state) => $modalityNames[$state] ?? $state)
                    ->badge(),

                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->default('System-wide'),

                Tables\Columns\IconColumn::make('is_contrast_enhanced')
                    ->label('Contrast')
                    ->boolean(),

                Tables\Columns\IconColumn::make('requires_consent')
                    ->label('Consent')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('modality_type')
                    ->label('Modality')
                    ->options($this->modalityOptions($businessId !== 1 ? $businessId : null)),
                ...($businessId === 1 ? [
                    Tables\Filters\SelectFilter::make('business_id')
                        ->label('Filter by Business')
                        ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                        ->searchable()
                        ->multiple(),
                ] : []),
            ])
            ->actions([
                Tables\Actions\Action::make('configureWorkflow')
                    ->label('Configure Workflow')
                    ->icon('heroicon-o-arrows-right-left')
                    ->visible(fn () => in_array('Manage Imaging Protocols', Auth::user()->permissions ?? []))
                    ->url(fn (ImagingProtocol $record) => route('imaging-protocols.workflow', $record)),

                EditAction::make()
                    ->visible(fn () => in_array('Manage Imaging Protocols', Auth::user()->permissions ?? []))
                    ->modalHeading('Edit Imaging Protocol')
                    ->form(fn () => $this->formFields())
                    ->mutateRecordDataUsing(fn (array $data) => $this->mutateRecordData($data))
                    ->mutateFormDataUsing(fn (array $data) => $this->mutateFormData($data))
                    ->successNotificationTitle('Imaging protocol updated successfully.'),

                DeleteAction::make()
                    ->visible(fn () => in_array('Manage Imaging Protocols', Auth::user()->permissions ?? []))
                    ->modalHeading('Delete Imaging Protocol')
                    ->successNotificationTitle('Imaging protocol deleted successfully.'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Imaging Protocol')
                    ->visible(fn () => in_array('Manage Imaging Protocols', Auth::user()->permissions ?? []))
                    ->modalHeading('Add Imaging Protocol')
                    ->form(fn () => $this->formFields())
                    ->mutateFormDataUsing(fn (array $data) => $this->mutateFormData($data))
                    ->createAnother(false)
                    ->after(function () {
                        Notification::make()
                            ->title('Imaging protocol created successfully.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function render(): View
    {
        return view('livewire.imaging.list-imaging-protocols');
    }
}
