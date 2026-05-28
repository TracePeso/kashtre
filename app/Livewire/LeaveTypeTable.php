<?php

namespace App\Livewire;

use App\Models\LeaveType;
use App\Models\Organization;
use App\Services\HrDefaultLeaveTypeService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class LeaveTypeTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $org = Organization::current();

        if ($org) {
            app(HrDefaultLeaveTypeService::class)->seedMissingDefaults($org);
        }

        $query = LeaveType::query()
            ->when($org, fn($q) => $q->where('organization_id', $org->id))
            ->orderBy('code')
            ->latest('id');

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Leave Type')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('session_type')
                    ->label('Session')
                    ->formatStateUsing(fn (string $state): string => LeaveType::sessionOptions()[$state] ?? 'Custom')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('days_deducted_per_workday')
                    ->label('Deduct / Workday')
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 2, '.', ''), '0'), '.'))
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('advance_notice_days')
                    ->label('Notice Rule')
                    ->formatStateUsing(fn (LeaveType $record): string => $record->advanceNoticeSummary())
                    ->wrap(),
                Tables\Columns\TextColumn::make('max_days_per_year')
                    ->label('Entitled Days/Year')
                    ->alignCenter()
                    ->placeholder('Unlimited'),
                Tables\Columns\IconColumn::make('tracks_balance')
                    ->label('Tracks Balance')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_paid')
                    ->label('Paid')
                    ->boolean(),
                Tables\Columns\IconColumn::make('requires_approval')
                    ->label('Needs Approval')
                    ->boolean(),
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
                    ->modalHeading('Edit Leave Type')
                    ->form($this->getFormSchema())
                    ->successNotificationTitle('Leave type updated.'),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => Auth::user()?->canEditHrSetup() ?? false)
                    ->button()
                    ->label('Delete')
                    ->color('danger')
                    ->successNotificationTitle('Leave type deleted.'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn (): bool => Auth::user()?->canAddHrSetup() ?? false)
                    ->button()
                    ->color('primary')
                    ->label('Add Leave Type')
                    ->modalHeading('Create Leave Type')
                    ->form($this->getFormSchema())
                    ->mutateFormDataUsing(function (array $data) {
                        $org = Organization::current();
                        $data['organization_id'] = $org->id;
                        return $data;
                    })
                    ->createAnother(false)
                    ->successNotificationTitle('Leave type created.'),
            ])
            ->emptyStateHeading('No leave types')
            ->emptyStateDescription('Configure leave types for your organization.');
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->label('Leave Type Name')
                ->required()
                ->placeholder('e.g. Annual Leave'),
            Forms\Components\TextInput::make('code')
                ->label('Code')
                ->required()
                ->maxLength(20)
                ->placeholder('e.g. L or L1'),
            Forms\Components\Select::make('session_type')
                ->label('Leave Session')
                ->options(LeaveType::sessionOptions())
                ->required()
                ->live()
                ->afterStateUpdated(function (callable $set, ?string $state): void {
                    $set(
                        'days_deducted_per_workday',
                        in_array($state, [LeaveType::SESSION_MORNING_ABSENT, LeaveType::SESSION_AFTERNOON_ABSENT], true) ? 0.5 : 1
                    );
                })
                ->default(LeaveType::SESSION_FULL_DAY),
            Forms\Components\TextInput::make('days_deducted_per_workday')
                ->label('Days Deducted Per Working Day')
                ->numeric()
                ->required()
                ->minValue(0.25)
                ->step('0.25')
                ->default(1)
                ->helperText('Use 1 for full-day leave and 0.5 for morning or afternoon absence types.'),
            Forms\Components\Select::make('advance_notice_timing')
                ->label('Notice Timing')
                ->options(LeaveType::noticeTimingOptions())
                ->required()
                ->default(LeaveType::NOTICE_PRE),
            Forms\Components\TextInput::make('advance_notice_days')
                ->label('Required Notice Days')
                ->numeric()
                ->required()
                ->minValue(0)
                ->step(1)
                ->default(0)
                ->helperText('Set to 0 for no notice rule. Use Notice Timing to decide whether the leave must be reported before or after it starts.'),
            Forms\Components\TextInput::make('max_days_per_year')
                ->label('Entitled Days Per Year')
                ->numeric()
                ->nullable()
                ->placeholder('Leave empty for no yearly cap'),
            Forms\Components\Toggle::make('tracks_balance')
                ->label('Tracks Leave Balance')
                ->default(true),
            Forms\Components\Toggle::make('is_paid')
                ->label('Paid Leave')
                ->default(true),
            Forms\Components\Toggle::make('requires_approval')
                ->label('Requires Approval')
                ->default(true),
            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ];
    }

    public function render(): View
    {
        return view('livewire.leave-type-table');
    }
}
