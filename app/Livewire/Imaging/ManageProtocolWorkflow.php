<?php

namespace App\Livewire\Imaging;

use App\Models\ImagingProtocol;
use App\Models\ImagingWorkflowStep;
use App\Models\ProtocolWorkflow;
use App\Models\ProtocolWorkflowStep;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * RIS Amendment v2.6, Chunk 2 — "Configure Workflow" per protocol. Not a
 * Filament table (there's exactly one active workflow to edit per
 * protocol, not a list to browse) — a standalone Filament form embedded
 * in a plain Livewire component, same idiom this app already uses for
 * business-settings-style single-record edit pages.
 *
 * Edits the active workflow's steps in place for now (create/update, no
 * automatic version bump on structural change) — a real versioning policy
 * (when to mint workflow_version 2 vs. edit version 1 in place) needs its
 * own decision and isn't guessed at here.
 */
class ManageProtocolWorkflow extends Component implements HasForms
{
    use InteractsWithForms;

    public ImagingProtocol $protocol;

    public array $data = [];

    public function mount(ImagingProtocol $protocol): void
    {
        $this->protocol = $protocol;

        $workflow = $protocol->activeWorkflow();

        $this->form->fill([
            'workflow_name' => $workflow?->workflow_name ?? 'Standard Workflow',
            'steps' => $workflow
                ? $workflow->steps->map(fn (ProtocolWorkflowStep $step) => [
                    'imaging_workflow_step_id' => $step->imaging_workflow_step_id,
                    'ris_status' => $step->ris_status,
                    'main_status' => $step->main_status,
                    'triggers_consumption' => $step->triggers_consumption,
                ])->toArray()
                : [],
        ]);
    }

    public function form(Forms\Form $form): Forms\Form
    {
        $businessId = $this->protocol->business_id ?? Auth::user()->business_id;

        return $form
            ->schema([
                Forms\Components\TextInput::make('workflow_name')
                    ->label('Workflow Name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Repeater::make('steps')
                    ->label('Steps')
                    ->schema([
                        Forms\Components\Select::make('imaging_workflow_step_id')
                            ->label('Step')
                            ->options(fn () => ImagingWorkflowStep::query()
                                ->active()
                                ->availableToBusiness($businessId)
                                ->pluck('step_name', 'id'))
                            ->searchable()
                            ->required(),

                        Forms\Components\TextInput::make('ris_status')
                            ->label('RIS Status')
                            ->placeholder('e.g. IN_PROGRESS')
                            ->required()
                            ->helperText('Sets ImagingStudy::status when this step completes.'),

                        Forms\Components\Select::make('main_status')
                            ->label('Main Module Status')
                            ->options([
                                ProtocolWorkflowStep::MAIN_STATUS_PENDING => 'Pending',
                                ProtocolWorkflowStep::MAIN_STATUS_IN_PROGRESS => 'In Progress',
                                ProtocolWorkflowStep::MAIN_STATUS_COMPLETED => 'Completed',
                            ])
                            ->required(),

                        Forms\Components\Toggle::make('triggers_consumption')
                            ->label('Triggers Consumption')
                            ->extraAttributes(['class' => 'kt-toggle']),
                    ])
                    ->columns(4)
                    ->addActionLabel('Add Step')
                    ->defaultItems(0)
                    // Same constraint/workaround as reporting_sections in
                    // ListImagingProtocols.php: no Filament Panel JS bundle
                    // loaded, so drag-and-drop silently doesn't work here —
                    // button-based reordering has no extra JS dependency.
                    ->reorderableWithDragAndDrop(false)
                    ->reorderableWithButtons()
                    ->helperText('Use the arrows to reorder. This becomes the order a study moves through this protocol.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        DB::transaction(function () use ($state) {
            $workflow = $this->protocol->activeWorkflow();

            if (! $workflow) {
                $nextVersion = ((int) $this->protocol->workflows()->max('workflow_version')) + 1;

                $workflow = ProtocolWorkflow::create([
                    'imaging_protocol_id' => $this->protocol->id,
                    'workflow_name' => $state['workflow_name'],
                    'workflow_version' => $nextVersion,
                    'is_active' => true,
                ]);
            } else {
                $workflow->update(['workflow_name' => $state['workflow_name']]);
            }

            $workflow->steps()->delete();

            foreach ($state['steps'] as $sequenceNo => $step) {
                ProtocolWorkflowStep::create([
                    'imaging_protocol_workflow_id' => $workflow->id,
                    'imaging_workflow_step_id' => $step['imaging_workflow_step_id'],
                    'sequence_no' => $sequenceNo + 1,
                    'ris_status' => $step['ris_status'],
                    'main_status' => $step['main_status'],
                    'triggers_consumption' => (bool) ($step['triggers_consumption'] ?? false),
                ]);
            }

            // RIS Amendment v2.6, Chunk 5: the delete-and-recreate above just
            // cascade-deleted every completion rule attached to the old step
            // rows — regenerate the legacy_sync ones immediately so the
            // protocol's checklist/consent requirements keep being enforced
            // without needing a separate save of the protocol itself.
            $this->protocol->syncLegacyCompletionRules();
        });

        Notification::make()
            ->title('Workflow saved successfully.')
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('livewire.imaging.manage-protocol-workflow');
    }
}
