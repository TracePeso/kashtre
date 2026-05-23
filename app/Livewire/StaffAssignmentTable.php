<?php

namespace App\Livewire;

use App\Models\HrStaffRosteringProfile;
use App\Models\Organization;
use App\Models\ShiftType;
use App\Models\StaffAssignment;
use App\Services\KashApiService;
use App\Support\StaffRecordData;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class StaffAssignmentTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $org = Organization::current();
        $query = StaffAssignment::query()
            ->when($org, fn($q) => $q->where('organization_id', $org->id))
            ->with(['organizationalUnit', 'rosteringProfile.fixedShiftType'])
            ->latest();

        $staffDirectory = $this->getStaffDirectory();
        $staffOptions = collect($staffDirectory)
            ->mapWithKeys(fn (array $staff, string $uuid): array => [$uuid => $this->staffOptionLabel($staff)])
            ->toArray();

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('staff_name')
                    ->label('Staff Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('organizationalUnit.name')
                    ->label('Current Unit/Tier')
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('staff_cadre')
                    ->label('Cadre')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('staff_department')
                    ->label('Department')
                    ->placeholder('Not set')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('staff_title')
                    ->label('Title')
                    ->placeholder('Not set')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('rosteringProfile.rostering_mode')
                    ->label('Rostering')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === HrStaffRosteringProfile::MODE_FIXED ? 'Fixed' : 'Dynamic')
                    ->color(fn (?string $state): string => $state === HrStaffRosteringProfile::MODE_FIXED ? 'gray' : 'success')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('rosteringProfile.fixedShiftType.code')
                    ->label('Fixed Shift')
                    ->placeholder('Flexible')
                    ->formatStateUsing(fn ($state, StaffAssignment $record): string => $record->rosteringProfile?->fixedShiftType
                        ? $this->shiftTypeLabel($record->rosteringProfile->fixedShiftType)
                        : 'Flexible')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('home_branch_name')
                    ->label('Home Branch')
                    ->placeholder('Not set')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('assignment_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'primary' => 'info',
                        'secondary' => 'warning',
                        'temporary' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'pending_routing' => 'warning',
                        'orphaned' => 'danger',
                        'inactive' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('assigned_at')
                    ->label('Assigned On')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'pending_routing' => 'Pending Routing',
                        'orphaned' => 'Orphaned',
                        'inactive' => 'Inactive',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('route')
                    ->label('Route')
                    ->color('info')
                    ->icon('heroicon-o-arrow-right')
                    ->visible(fn (\App\Models\StaffAssignment $record): bool => $this->canRouteRecord($record))
                    ->form([
                        Forms\Components\Select::make('target_unit_id')
                            ->label('Target Unit/Space')
                            ->options(function (\App\Models\StaffAssignment $record) {
                                return app(\App\Services\CascadeRoutingService::class)
                                    ->availableTargetsForAssignment($record)
                                    ->pluck('name', 'id');
                            })
                            ->required(),
                    ])
                    ->action(function (\App\Models\StaffAssignment $record, array $data) {
                        try {
                            $targetUnit = \App\Models\HrOrganizationalUnit::findOrFail($data['target_unit_id']);
                            app(\App\Services\CascadeRoutingService::class)->routeStaff($record, $targetUnit, Auth::user());
                            Notification::make()->success()->title('Staff routed successfully.')->send();
                        } catch (\Exception $e) {
                            Notification::make()->danger()->title('Routing failed: ' . $e->getMessage())->send();
                        }
                    }),
                Tables\Actions\Action::make('rosteringProfile')
                    ->visible(fn (): bool => Auth::user()?->canEditHrStaff() ?? false)
                    ->button()
                    ->label('Rostering')
                    ->color('gray')
                    ->modalHeading('Edit Rostering Profile')
                    ->fillForm(fn (StaffAssignment $record): array => $this->rosteringProfileFormData($record))
                    ->form($this->rosteringProfileFormSchema())
                    ->action(function (StaffAssignment $record, array $data): void {
                        $this->saveRosteringProfile($record, $data);
                        Notification::make()->success()->title('Rostering profile updated.')->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => Auth::user()?->canEditHrStaff() ?? false)
                    ->button()
                    ->label('Edit')
                    ->color('primary')
                    ->modalHeading('Edit Assignment')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'pending_routing' => 'Pending Routing',
                                'orphaned' => 'Orphaned',
                                'inactive' => 'Inactive',
                            ])
                            ->required(),
                        Forms\Components\Select::make('assignment_type')
                            ->options([
                                'primary' => 'Primary',
                                'secondary' => 'Secondary',
                                'temporary' => 'Temporary',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('home_branch_name')
                            ->label('Home Branch')
                            ->readOnly(),
                        Forms\Components\Textarea::make('notes')
                            ->nullable(),
                    ])
                    ->successNotificationTitle('Assignment updated.'),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => Auth::user()?->canEditHrStaff() ?? false)
                    ->button()
                    ->label('Remove')
                    ->color('danger')
                    ->successNotificationTitle('Assignment removed.'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn (): bool => Auth::user()?->canAddHrStaff() ?? false)
                    ->button()
                    ->color('primary')
                    ->label('Assign Staff')
                    ->modalHeading('Assign Staff')
                    ->form([
                        ...(!empty($staffOptions) ? [
                            Forms\Components\Select::make('staff_uuid')
                                ->label('Staff')
                                ->options($staffOptions)
                                ->required()
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function (?string $state, Forms\Set $set) use ($staffDirectory) {
                                    $staff = $state ? ($staffDirectory[$state] ?? null) : null;

                                    $set('staff_name', $staff['name'] ?? '');
                                    $set('staff_cadre', $staff['cadre'] ?? '');
                                    $set('staff_department', $staff['department'] ?? '');
                                    $set('staff_title', $staff['title'] ?? '');
                                    $set('home_branch_external_id', $staff['home_branch_external_id'] ?? '');
                                    $set('home_branch_name', $staff['home_branch_name'] ?? '');
                                }),
                            Forms\Components\TextInput::make('staff_name')
                                ->label('Staff Name')
                                ->required()
                                ->readOnly(),
                        ] : [
                            Forms\Components\TextInput::make('staff_name')
                                ->label('Staff Name')
                                ->required()
                                ->placeholder('Enter staff name'),
                            Forms\Components\TextInput::make('staff_uuid')
                                ->label('Staff UUID (from main system)')
                                ->required()
                                ->placeholder('UUID from KashTre'),
                        ]),
                        Forms\Components\TextInput::make('staff_cadre')
                            ->label('Cadre')
                            ->placeholder('e.g. Nurse, Doctor'),
                        Forms\Components\TextInput::make('staff_department')
                            ->label('Department')
                            ->placeholder('e.g. Finance, Nursing')
                            ->readOnly(!empty($staffOptions)),
                        Forms\Components\TextInput::make('staff_title')
                            ->label('Title')
                            ->placeholder('e.g. Department Head')
                            ->readOnly(!empty($staffOptions)),
                        Forms\Components\Hidden::make('home_branch_external_id'),
                        Forms\Components\TextInput::make('home_branch_name')
                            ->label('Home Branch')
                            ->placeholder('Branch from staff record')
                            ->readOnly(!empty($staffOptions)),
                        Forms\Components\Select::make('assignment_type')
                            ->options([
                                'primary' => 'Primary',
                                'secondary' => 'Secondary',
                                'temporary' => 'Temporary',
                            ])
                            ->default('primary')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending_routing' => 'Pending Routing',
                            ])
                            ->default('pending_routing')
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->nullable(),
                    ])
                    ->mutateFormDataUsing(function (array $data) {
                        $org = Organization::current();
                        $data['organization_id'] = $org->id;
                        $data['assigned_at'] = now();
                        return $data;
                    })
                    ->createAnother(false)
                    ->successNotificationTitle('Staff assigned successfully.'),
            ])
            ->emptyStateHeading('No staff assignments')
            ->emptyStateDescription('Assign staff from the main KashTre system.')
            ->emptyStateIcon('heroicon-o-user-group');
    }

    public function render(): View
    {
        return view('livewire.staff-assignment-table');
    }

    private function getStaffDirectory(): array
    {
        try {
            $staffData = app(KashApiService::class)->getStaff(['per_page' => 100]);
        } catch (\Throwable) {
            return [];
        }

        $directory = [];

        foreach (Arr::get($staffData, 'data', []) as $staff) {
            $uuid = StaffRecordData::uuid($staff);
            $name = StaffRecordData::name($staff);

            if (!$uuid || !$name) {
                continue;
            }

            $directory[(string) $uuid] = [
                'name' => $name,
                'cadre' => StaffRecordData::cadre($staff) ?? '',
                'department' => StaffRecordData::department($staff) ?? '',
                'title' => StaffRecordData::title($staff) ?? '',
                'home_branch_external_id' => StaffRecordData::branchExternalId($staff) ?? '',
                'home_branch_name' => StaffRecordData::branchName($staff) ?? '',
            ];
        }

        return $directory;
    }

    private function staffOptionLabel(array $staff): string
    {
        $details = collect([$staff['title'] ?? null, $staff['department'] ?? null])
            ->filter()
            ->implode(' - ');

        return $details ? "{$staff['name']} ({$details})" : $staff['name'];
    }

    private function canRouteRecord(StaffAssignment $record): bool
    {
        $user = Auth::user();

        if (! $user || $record->organizationalUnit?->isClientSpace()) {
            return false;
        }

        if (! $record->organizationalUnit) {
            return $user->canAddHrStaff() || $user->canEditHrStaff();
        }

        return $user->is_hr_admin || $record->organizationalUnit->hasRoutingMember($user);
    }

    protected function rosteringProfileFormSchema(): array
    {
        return [
            Forms\Components\Select::make('rostering_mode')
                ->label('Rostering Mode')
                ->options([
                    HrStaffRosteringProfile::MODE_DYNAMIC => 'Dynamic',
                    HrStaffRosteringProfile::MODE_FIXED => 'Fixed',
                ])
                ->default(HrStaffRosteringProfile::MODE_DYNAMIC)
                ->required(),
            Forms\Components\Select::make('fixed_shift_type_id')
                ->label('Fixed Shift')
                ->options($this->shiftTypeOptions())
                ->searchable()
                ->placeholder('No fixed shift'),
            Forms\Components\CheckboxList::make('fixed_days_of_week')
                ->label('Fixed Days')
                ->options($this->dayOfWeekOptions())
                ->columns(4)
                ->helperText('Used when the staff member follows a fixed weekly pattern.'),
            Forms\Components\CheckboxList::make('preferred_shift_type_ids')
                ->label('Preferred Shifts')
                ->options($this->shiftTypeOptions())
                ->columns(2),
            Forms\Components\CheckboxList::make('excluded_shift_type_ids')
                ->label('Excluded Shifts')
                ->options($this->shiftTypeOptions())
                ->columns(2),
            Forms\Components\TextInput::make('max_night_shifts_per_cycle')
                ->label('Max Overnight Shifts Per Cycle')
                ->numeric()
                ->minValue(0)
                ->helperText('Night counts use shifts that run past midnight.'),
            Forms\Components\Textarea::make('notes')
                ->label('Rostering Notes')
                ->rows(3),
            Forms\Components\Toggle::make('is_active')
                ->label('Use This Profile')
                ->default(true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rosteringProfileFormData(StaffAssignment $record): array
    {
        $profile = $record->rosteringProfile;

        return [
            'rostering_mode' => $profile?->rostering_mode ?? HrStaffRosteringProfile::MODE_DYNAMIC,
            'fixed_shift_type_id' => $profile?->fixed_shift_type_id,
            'fixed_days_of_week' => $profile?->fixedDays() ?? [],
            'preferred_shift_type_ids' => $profile?->preferredShiftIds() ?? [],
            'excluded_shift_type_ids' => $profile?->excludedShiftIds() ?? [],
            'max_night_shifts_per_cycle' => $profile?->max_night_shifts_per_cycle,
            'notes' => $profile?->notes,
            'is_active' => $profile?->is_active ?? true,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function saveRosteringProfile(StaffAssignment $record, array $data): void
    {
        $record->rosteringProfile()->updateOrCreate(
            [],
            [
                'organization_id' => $record->organization_id,
                'rostering_mode' => $data['rostering_mode'] ?? HrStaffRosteringProfile::MODE_DYNAMIC,
                'fixed_shift_type_id' => $data['fixed_shift_type_id'] ?: null,
                'fixed_days_of_week' => array_values(array_map('intval', $data['fixed_days_of_week'] ?? [])),
                'preferred_shift_type_ids' => array_values(array_map('intval', $data['preferred_shift_type_ids'] ?? [])),
                'excluded_shift_type_ids' => array_values(array_map('intval', $data['excluded_shift_type_ids'] ?? [])),
                'max_night_shifts_per_cycle' => filled($data['max_night_shifts_per_cycle'] ?? null)
                    ? (int) $data['max_night_shifts_per_cycle']
                    : null,
                'notes' => $data['notes'] ?: null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]
        );
    }

    /**
     * @return array<int, string>
     */
    protected function shiftTypeOptions(): array
    {
        $organization = Organization::current();

        if (! $organization) {
            return [];
        }

        return ShiftType::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ShiftType $shiftType): array => [
                $shiftType->id => $this->shiftTypeLabel($shiftType),
            ])
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    protected function dayOfWeekOptions(): array
    {
        return [
            1 => 'Mon',
            2 => 'Tue',
            3 => 'Wed',
            4 => 'Thu',
            5 => 'Fri',
            6 => 'Sat',
            0 => 'Sun',
        ];
    }

    protected function shiftTypeLabel(ShiftType $shiftType): string
    {
        return $shiftType->code
            ? $shiftType->code.' - '.$shiftType->name
            : $shiftType->name;
    }
}
