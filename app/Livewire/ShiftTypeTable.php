<?php

namespace App\Livewire;

use App\Models\Organization;
use App\Models\ShiftType;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ShiftTypeTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $org = Organization::current();
        $query = ShiftType::query()
            ->when($org, fn($q) => $q->where('organization_id', $org->id))
            ->orderByDesc('is_system_default')
            ->latest();

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\ColorColumn::make('color')
                    ->label('')
                    ->width('40px'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Shift Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_system_default')
                    ->label('Default')
                    ->boolean(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Start')
                    ->time('H:i'),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('End')
                    ->time('H:i'),

                Tables\Columns\TextColumn::make('break_durations_minutes')
                    ->label('Breaks (min)')
                    ->formatStateUsing(fn (mixed $state, ShiftType $record): string => $record->formattedBreakDurations())
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('break_duration_minutes')
                    ->label('Total Break (min)')
                    ->state(fn (ShiftType $record): int => $record->totalBreakDurationMinutes())
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('net_duration_minutes')
                    ->label('Net (min)')
                    ->placeholder(fn (ShiftType $record): string => (string) $record->effectiveNetMinutes())
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => Auth::user()?->canEditHrSetup() ?? false)
                    ->button()
                    ->label('Edit')
                    ->color('primary')
                    ->modalHeading('Edit Shift Type')
                    ->form($this->getFormSchema(true))
                    ->mutateFormDataUsing(function (array $data, ShiftType $record): array {
                        if ($this->hasLockedShiftName($record)) {
                            $data['name'] = $record->name;
                        }

                        return $this->mutateShiftTypeFormData($data);
                    })
                    ->successNotificationTitle('Shift type updated.'),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => Auth::user()?->canEditHrSetup() ?? false)
                    ->button()
                    ->label('Delete')
                    ->color('danger')
                    ->successNotificationTitle('Shift type deleted.'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn (): bool => Auth::user()?->canAddHrSetup() ?? false)
                    ->button()
                    ->color('primary')
                    ->label('Add Shift Type')
                    ->modalHeading('Create Shift Type')
                    ->form($this->getFormSchema())
                    ->mutateFormDataUsing(fn (array $data): array => $this->mutateShiftTypeFormData($data))
                    ->createAnother(false)
                    ->successNotificationTitle('Shift type created.'),
            ])
            ->emptyStateHeading('No shift types')
            ->emptyStateDescription('Configure shift patterns for roster planning.');
    }

    protected function getFormSchema(bool $editing = false): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->label('Shift Name')
                ->required()
                ->disabled(fn (?ShiftType $record): bool => $editing && $this->hasLockedShiftName($record))
                ->placeholder('e.g. Early Shift'),
            Forms\Components\TextInput::make('code')
                ->label('Code')
                ->required()
                ->maxLength(20)
                ->placeholder('e.g. DAY'),
            Forms\Components\Grid::make(2)->schema([
                $this->makeShiftTimePicker('start_time', 'Start Time'),
                $this->makeShiftTimePicker('end_time', 'End Time'),
            ]),
            Forms\Components\Repeater::make('break_durations_minutes')
                ->label('Break Durations')
                ->schema([
                    Forms\Components\TextInput::make('duration_minutes')
                        ->label('Duration (minutes)')
                        ->integer()
                        ->required()
                        ->minValue(0)
                        ->step(15)
                        ->rule('multiple_of:15'),
                ])
                ->default([])
                ->live()
                ->afterStateHydrated(fn (Get $get, Set $set) => $this->syncDerivedDurationFields($get, $set))
                ->afterStateUpdated(fn (Get $get, Set $set) => $this->syncDerivedDurationFields($get, $set))
                ->addActionLabel('Add Break Duration')
                ->itemLabel(fn (array $state): ?string => filled($state['duration_minutes'] ?? null)
                    ? ((string) $state['duration_minutes']).' min'
                    : null)
                ->helperText('Add one or more breaks. Each duration must use 15-minute increments such as 15, 30, 45, 60, 75, or 90.'),
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('gross_duration_minutes')
                    ->label('Gross Duration (minutes)')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Auto-calculated from start and end time.'),
                Forms\Components\TextInput::make('total_break_duration_minutes')
                    ->label('Total Break Duration (minutes)')
                    ->disabled()
                    ->dehydrated(false)
                    ->default(0)
                    ->helperText('Auto-calculated from all configured break durations.'),
                Forms\Components\TextInput::make('net_duration_minutes')
                    ->label('Net Work Duration (minutes)')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Auto-calculated as gross time minus total break time.'),
            ]),
            Forms\Components\ColorPicker::make('color')
                ->label('Color'),
            Forms\Components\Toggle::make('is_rosterable')
                ->label('Rosterable')
                ->default(true),
            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ];
    }

    protected function makeShiftTimePicker(string $name, string $label): Forms\Components\TimePicker
    {
        return Forms\Components\TimePicker::make($name)
            ->label($label)
            ->required()
            ->seconds(false)
            ->minutesStep(30)
            ->live()
            ->afterStateHydrated(fn (Get $get, Set $set) => $this->syncDerivedDurationFields($get, $set))
            ->afterStateUpdated(fn (Get $get, Set $set) => $this->syncDerivedDurationFields($get, $set))
            ->rule($this->shiftTimeIncrementRule(30))
            ->helperText('Use 30-minute increments only: :00 or :30.');
    }

    protected function mutateShiftTypeFormData(array $data): array
    {
        $org = Organization::current();

        if ($org) {
            $data['organization_id'] = $org->id;
        }

        $grossMinutes = $this->calculatedGrossDurationMinutes($data['start_time'] ?? null, $data['end_time'] ?? null);
        $breakMinutes = $this->calculatedBreakDurationMinutes($data['break_durations_minutes'] ?? []);

        $data['gross_duration_minutes'] = $grossMinutes;
        $data['break_duration_minutes'] = $breakMinutes;
        $data['net_duration_minutes'] = $grossMinutes !== null
            ? max(0, $grossMinutes - $breakMinutes)
            : null;

        unset($data['total_break_duration_minutes']);

        return $data;
    }

    protected function syncDerivedDurationFields(Get $get, Set $set): void
    {
        $grossMinutes = $this->calculatedGrossDurationMinutes($get('start_time'), $get('end_time'));
        $breakMinutes = $this->calculatedBreakDurationMinutes($get('break_durations_minutes') ?? []);

        $set('gross_duration_minutes', $grossMinutes);
        $set('total_break_duration_minutes', $breakMinutes);
        $set('net_duration_minutes', $grossMinutes !== null
            ? max(0, $grossMinutes - $breakMinutes)
            : null);
    }

    protected function calculatedGrossDurationMinutes(mixed $startTime, mixed $endTime): ?int
    {
        if (! is_string($startTime) || trim($startTime) === '' || ! is_string($endTime) || trim($endTime) === '') {
            return null;
        }

        try {
            $start = CarbonImmutable::parse('2000-01-01 '.trim($startTime));
            $end = CarbonImmutable::parse('2000-01-01 '.trim($endTime));
        } catch (\Throwable) {
            return null;
        }

        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        return $start->diffInMinutes($end);
    }

    protected function calculatedBreakDurationMinutes(mixed $breakDurations): int
    {
        if (! is_array($breakDurations)) {
            return 0;
        }

        return collect($breakDurations)
            ->sum(function (mixed $entry): int {
                if (! is_array($entry)) {
                    return 0;
                }

                return max(0, (int) ($entry['duration_minutes'] ?? 0));
            });
    }

    protected function shiftTimeIncrementRule(int $minutesStep): ValidationRule
    {
        return new class($minutesStep) implements ValidationRule
        {
            public function __construct(private readonly int $minutesStep)
            {
            }

            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                if (ShiftTypeTable::timeMatchesMinuteStep($value, $this->minutesStep)) {
                    return;
                }

                $fail("The {$attribute} must use {$this->minutesStep}-minute increments.");
            }
        };
    }

    public static function timeMatchesMinuteStep(mixed $value, int $minutesStep): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return true;
        }

        if (! preg_match('/(?:^|\\s)(\\d{1,2}):(\\d{2})(?::(\\d{2}))?$/', trim($value), $matches)) {
            return false;
        }

        $minutes = (int) $matches[2];
        $seconds = isset($matches[3]) ? (int) $matches[3] : 0;

        return ($minutes % $minutesStep) === 0 && $seconds === 0;
    }

    public function render(): View
    {
        return view('livewire.shift-type-table');
    }

    protected function hasLockedShiftName(?ShiftType $record): bool
    {
        return strcasecmp((string) $record?->name, 'Regular working Hours') === 0;
    }
}

