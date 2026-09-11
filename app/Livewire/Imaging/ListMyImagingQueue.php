<?php

namespace App\Livewire\Imaging;

use App\Models\Business;
use App\Models\ImagingReport;
use App\Models\ImagingStudy;
use App\Models\ImagingStudyWorkflowExecution;
use App\Models\ImagingWorkflowClaim;
use App\Models\User;
use App\Services\Imaging\WorkflowOwnershipService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * RIS Amendment v2.6, Chunk 4: "My Queue" — supersedes ListRadiologistWorklist.
 * Where that page showed every report-pending/reported/critical/urgent/amended
 * study to anyone holding the view permission, this page is scoped to
 * whichever workflow step(s) the logged-in user is actually assigned to
 * (Chunk 1's per-step user pool) — generalizing the old radiologist-only
 * worklist into a queue any role (technologist, radiologist, ...) gets a
 * version of, driven entirely by admin-configured step assignments instead
 * of a hardcoded status list.
 */
class ListMyImagingQueue extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $userId = (int) Auth::id();

        $eligibleForStep = function ($stepQuery) use ($userId) {
            $stepQuery->where(function ($q) use ($userId) {
                $q->doesntHave('users')
                    ->orWhereHas('users', fn ($u) => $u->where('users.id', $userId));
            });
        };

        $query = ImagingStudy::query()
            ->where(function ($q) use ($eligibleForStep) {
                // Normal case: an open execution sitting at a step I'm eligible for.
                $q->whereHas('workflowExecutions', function ($exec) use ($eligibleForStep) {
                    $exec->where('status', ImagingStudyWorkflowExecution::STATUS_ACTIVE)
                        ->whereHas('currentStep.workflowStep', $eligibleForStep);
                })
                // Amendment carve-out (matches the old radiologist worklist's own
                // special case): a finished execution's terminal step, where I'm
                // eligible, but the study has a report that needs re-verification.
                ->orWhere(function ($q2) use ($eligibleForStep) {
                    $q2->whereHas('workflowExecutions', function ($exec) use ($eligibleForStep) {
                        $exec->whereHas('currentStep.workflowStep', $eligibleForStep);
                    })->whereHas('reports', fn ($r) => $r->where('status', ImagingReport::STATUS_AMENDED));
                });
            })
            ->latest();

        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->where('business_id', Auth::user()->business_id);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('accession_number')
                    ->label('Accession #')
                    ->searchable(),

                Tables\Columns\TextColumn::make('client_id')
                    ->label('Client')
                    ->getStateUsing(function (ImagingStudy $record) {
                        $client = $record->resolveClient();

                        return $client ? "{$client->full_name} ({$record->client_id})" : $record->client_id;
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('protocol_code')
                    ->label('Protocol')
                    ->getStateUsing(fn (ImagingStudy $record) => $record->protocol()?->name ?? $record->protocol_code),

                Tables\Columns\TextColumn::make('currentStep')
                    ->label('Queue')
                    ->getStateUsing(fn (ImagingStudy $record) => $this->resolveExecution($record)?->currentStep?->workflowStep?->step_name ?? '—'),

                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        ImagingStudy::PRIORITY_URGENT => 'danger',
                        ImagingStudy::PRIORITY_HIGH => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                Tables\Columns\IconColumn::make('critical')
                    ->label('Critical')
                    ->getStateUsing(fn (ImagingStudy $record) => (bool) $record->reports()->where('is_critical_finding', true)->exists())
                    ->boolean(),

                Tables\Columns\TextColumn::make('claim')
                    ->label('Claimed By')
                    ->getStateUsing(function (ImagingStudy $record) {
                        $claim = $this->resolveActiveClaim($record);

                        if (! $claim) {
                            return 'Unclaimed';
                        }

                        return ((int) $claim->assigned_user_id === (int) Auth::id())
                            ? 'You'
                            : ($claim->resolveAssignedUser()?->name ?? 'Another user');
                    })
                    ->badge()
                    ->color(function (ImagingStudy $record) {
                        $claim = $this->resolveActiveClaim($record);

                        if (! $claim) {
                            return 'gray';
                        }

                        return ((int) $claim->assigned_user_id === (int) Auth::id()) ? 'success' : 'warning';
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ordered At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('claim_state')
                    ->label('Show')
                    ->options([
                        'unclaimed' => 'Unclaimed only',
                        'mine' => 'Claimed by me',
                    ])
                    ->query(function ($query, array $data) use ($userId) {
                        return match ($data['value'] ?? null) {
                            'unclaimed' => $query->whereDoesntHave('workflowExecutions.claims', fn ($c) => $c->active()),
                            'mine' => $query->whereHas('workflowExecutions.claims', fn ($c) => $c->active()->where('assigned_user_id', $userId)),
                            default => $query,
                        };
                    }),
                Tables\Filters\TernaryFilter::make('critical')
                    ->label('Critical Cases')
                    ->queries(
                        true: fn ($query) => $query->whereHas('reports', fn ($r) => $r->where('is_critical_finding', true)),
                        false: fn ($query) => $query,
                        blank: fn ($query) => $query,
                    ),
                Tables\Filters\TernaryFilter::make('urgent')
                    ->label('Urgent Priority')
                    ->queries(
                        true: fn ($query) => $query->where('priority', ImagingStudy::PRIORITY_URGENT),
                        false: fn ($query) => $query,
                        blank: fn ($query) => $query,
                    ),
                ...(Auth::check() && Auth::user()->business_id === 1 ? [
                    Tables\Filters\SelectFilter::make('business_id')
                        ->label('Filter by Business')
                        ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                        ->searchable()
                        ->multiple(),
                ] : []),
            ])
            ->actions([
                Tables\Actions\Action::make('claim')
                    ->label('Claim')
                    ->icon('heroicon-o-hand-raised')
                    ->color('success')
                    ->visible(fn (ImagingStudy $record) => ! $this->resolveActiveClaim($record)
                        && in_array('Claim Imaging Studies', Auth::user()->permissions ?? []))
                    ->action(function (ImagingStudy $record) {
                        try {
                            app(WorkflowOwnershipService::class)->claimStudy($record, (int) Auth::id());

                            Notification::make()->title('Study claimed.')->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('release')
                    ->label('Release')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(function (ImagingStudy $record) {
                        $claim = $this->resolveActiveClaim($record);

                        return $claim
                            && (int) $claim->assigned_user_id === (int) Auth::id()
                            && in_array('Release Imaging Studies', Auth::user()->permissions ?? []);
                    })
                    ->requiresConfirmation()
                    ->action(function (ImagingStudy $record) {
                        app(WorkflowOwnershipService::class)->releaseStudy($record, (int) Auth::id());

                        Notification::make()->title('Study released.')->success()->send();
                    }),

                Tables\Actions\Action::make('transfer')
                    ->label('Transfer')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(function (ImagingStudy $record) {
                        $claim = $this->resolveActiveClaim($record);

                        return $claim
                            && (int) $claim->assigned_user_id === (int) Auth::id()
                            && in_array('Transfer Imaging Studies', Auth::user()->permissions ?? []);
                    })
                    ->form(fn (ImagingStudy $record) => [
                        Forms\Components\Select::make('to_user_id')
                            ->label('Transfer To')
                            ->placeholder('Select a user')
                            ->options(fn () => $this->eligibleTransferUsers($record)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (ImagingStudy $record, array $data) {
                        try {
                            app(WorkflowOwnershipService::class)->transferStudy($record, (int) $data['to_user_id']);

                            Notification::make()->title('Study transferred.')->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-right')
                    ->url(fn (ImagingStudy $record) => route('imaging-studies.show', $record)),
            ]);
    }

    protected function resolveExecution(ImagingStudy $record): ?ImagingStudyWorkflowExecution
    {
        return $record->activeWorkflowExecution() ?? $record->workflowExecutions()->latest()->first();
    }

    protected function resolveActiveClaim(ImagingStudy $record): ?ImagingWorkflowClaim
    {
        $execution = $this->resolveExecution($record);

        if (! $execution) {
            return null;
        }

        return ImagingWorkflowClaim::where('imaging_study_workflow_execution_id', $execution->id)->active()->first();
    }

    protected function eligibleTransferUsers(ImagingStudy $record)
    {
        $execution = $this->resolveExecution($record);
        $workflowStep = $execution?->currentStep?->workflowStep;

        if (! $workflowStep) {
            return collect();
        }

        $pool = $workflowStep->users;

        if ($pool->isNotEmpty()) {
            return $pool;
        }

        return User::where('business_id', $record->business_id)
            ->get()
            ->filter(fn (User $u) => in_array('Claim Imaging Studies', $u->permissions ?? []))
            ->values();
    }

    public function render(): View
    {
        return view('livewire.imaging.list-my-imaging-queue');
    }
}
