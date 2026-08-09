<?php

namespace App\Livewire\Imaging;

use App\Models\Business;
use App\Models\ImagingWorkflowStep;
use App\Models\User;
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
 * RIS Amendment v2.6, Chunk 1 — Settings > Imaging > Manage Workflow Steps.
 * The reusable step registry Chunk 2 will compose into versioned
 * per-protocol workflows. A step's "Assign Users" action manages its user
 * pool (imaging_workflow_step_users) — what makes the step a queue.
 */
class ListImagingWorkflowSteps extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected function formFields(): array
    {
        $businessId = Auth::user()->business_id;

        return [
            Forms\Components\Select::make('business_id')
                ->label('Business')
                ->placeholder('System-wide (all businesses)')
                ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                ->default($businessId !== 1 ? $businessId : null)
                ->disabled(fn () => $businessId !== 1)
                ->helperText('Leave blank to make this step available to every business.'),

            Forms\Components\TextInput::make('step_code')
                ->label('Step Code')
                ->placeholder('e.g. CONTRAST_ADMINISTRATION')
                ->required()
                ->maxLength(255)
                ->unique(table: ImagingWorkflowStep::class, column: 'step_code', ignoreRecord: true),

            Forms\Components\TextInput::make('step_name')
                ->label('Step Name')
                ->placeholder('e.g. Contrast Administration')
                ->required()
                ->maxLength(255),

            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->rows(2),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->extraAttributes(['class' => 'kt-toggle']),
        ];
    }

    protected function mutateFormData(array $data): array
    {
        $businessId = Auth::user()->business_id;

        if ($businessId !== 1) {
            $data['business_id'] = $businessId;
        }

        return $data;
    }

    public function table(Table $table): Table
    {
        $businessId = Auth::user()->business_id;

        $query = ImagingWorkflowStep::query()->latest();

        if ($businessId !== 1) {
            $query->availableToBusiness($businessId);
        }

        return $table
            ->query($query)
            ->emptyStateHeading('No workflow steps')
            ->emptyStateDescription('Create a workflow step to get started.')
            ->columns([
                Tables\Columns\TextColumn::make('step_name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('step_code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->default('System-wide'),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Assigned Users')
                    ->counts('users')
                    ->formatStateUsing(fn (int $state) => $state === 1 ? '1 user' : "{$state} users"),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('assignUsers')
                    ->label('Assign Users')
                    ->icon('heroicon-o-user-group')
                    ->visible(fn () => in_array('Manage Imaging Workflow Steps', Auth::user()->permissions ?? []))
                    ->modalHeading('Assign Users to Workflow Step')
                    ->form(fn (ImagingWorkflowStep $record) => [
                        Forms\Components\Select::make('user_ids')
                            ->label('Assigned Users')
                            ->multiple()
                            ->searchable()
                            ->options(fn () => User::query()
                                ->when($record->business_id, fn ($q) => $q->where('business_id', $record->business_id))
                                ->where('status', 'active')
                                ->pluck('name', 'id'))
                            ->default(fn () => $record->users()->pluck('users.id')->toArray())
                            ->helperText('These users see this step\'s pending work as their queue.'),
                    ])
                    ->action(function (ImagingWorkflowStep $record, array $data) {
                        $record->users()->sync($data['user_ids'] ?? []);

                        Notification::make()
                            ->title('Assigned users updated.')
                            ->success()
                            ->send();
                    }),

                EditAction::make()
                    ->visible(fn () => in_array('Manage Imaging Workflow Steps', Auth::user()->permissions ?? []))
                    ->modalHeading('Edit Workflow Step')
                    ->form(fn () => $this->formFields())
                    ->mutateFormDataUsing(fn (array $data) => $this->mutateFormData($data))
                    ->successNotificationTitle('Workflow step updated successfully.'),

                DeleteAction::make()
                    ->visible(fn () => in_array('Manage Imaging Workflow Steps', Auth::user()->permissions ?? [])),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Workflow Step')
                    ->visible(fn () => in_array('Manage Imaging Workflow Steps', Auth::user()->permissions ?? []))
                    ->modalHeading('Add Workflow Step')
                    ->form(fn () => $this->formFields())
                    ->mutateFormDataUsing(fn (array $data) => $this->mutateFormData($data))
                    ->createAnother(false)
                    ->after(function () {
                        Notification::make()
                            ->title('Workflow step created successfully.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function render(): View
    {
        return view('livewire.imaging.list-imaging-workflow-steps');
    }
}
