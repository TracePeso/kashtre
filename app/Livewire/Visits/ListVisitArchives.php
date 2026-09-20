<?php

namespace App\Livewire\Visits;

use App\Models\VisitArchive;
use App\Support\SharedTime;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ListVisitArchives extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public string $recordType = 'snapshot';
    public string $selectedDate;
    public int $selectedBranchId = 0;

    public function mount(string $recordType, string $selectedDate, int $selectedBranchId): void
    {
        $this->recordType = $recordType;
        $this->selectedDate = $selectedDate;
        $this->selectedBranchId = $selectedBranchId;
    }

    public function table(Table $table): Table
    {
        $user = Auth::user();

        $businessId = (int) ($user->business_id ?? 0);
        $allowedBranches = (array) ($user->allowed_branches ?? []);

        $query = VisitArchive::query()
            ->with(['business', 'branch', 'client'])
            ->where('record_type', $this->recordType)
            ;

        // Business scoping (mimic your Daily Visits behavior)
        // For testing purposes, show all data - remove this comment in production
        if (false && $businessId === 1) { // Disabled for testing
            $query->where('business_id', '!=', 1);
        } elseif (false && $businessId !== 1) { // Disabled for testing
            $query->where('business_id', $businessId);

            // Branch filter: use the branch id passed from controller (from the request),
            // but still ensure it's within allowed branches when provided.
            if ($this->selectedBranchId) {
                $query->where('branch_id', $this->selectedBranchId);
            } elseif (!empty($allowedBranches)) {
                $query->whereIn('branch_id', $allowedBranches);
            }
        }
        // For testing: Show all records regardless of business/branch

        $columns = [
            ...(($this->recordType === 'previous') ? [
                Tables\Columns\TextColumn::make('visit_end_at')
                    ->label('Visit Expiry Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('visit_created_at')
                    ->label('Visit Creation Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(),
            ] : []),

            Tables\Columns\TextColumn::make('full_client_id')
                ->label('Client ID')
                ->getStateUsing(fn (VisitArchive $record) => $record->full_client_id ?? $record->client?->client_id ?? '-')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('client_name')
                ->label('Client Name')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('client_age')
                ->label('Age')
                ->sortable(),

            Tables\Columns\TextColumn::make('visit_id')
                ->label('Visit ID')
                ->searchable()
                ->sortable(),
        ];

        if ($businessId === 1) {
            $columns = array_merge($columns, [
                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->sortable()
                    ->searchable(),
            ]);
        }

        return $table
            ->query($query->orderBy('archived_at', 'desc'))
            ->columns($columns)
            ->defaultSort('archived_at', 'desc')
            ->filters([
                ...($this->recordType === 'previous' ? [
                    Tables\Filters\Filter::make('quick_creation')
                        ->form([
                            \Filament\Forms\Components\Select::make('preset')
                                ->label('Quick Creation Date')
                                ->options([
                                    'today' => 'Today',
                                    'yesterday' => 'Yesterday',
                                    'this_week' => 'This Week',
                                    'this_month' => 'This Month',
                                ])
                                ->placeholder('All dates'),
                        ])
                        ->query(function ($query, array $data) {
                            if (empty($data['preset'])) return;
                            match ($data['preset']) {
                                'yesterday' => $query->whereOperationalPeriod('visit_created_at', 'yesterday'),
                                'this_week' => $query->whereOperationalPeriod('visit_created_at', 'this_week'),
                                'this_month' => $query->whereOperationalPeriod('visit_created_at', 'this_month'),
                                default => $query->whereOperationalPeriod('visit_created_at', 'today'),
                            };
                        })
                        ->indicateUsing(function (array $data): ?string {
                            if (empty($data['preset'])) return null;
                            return 'Created: ' . match ($data['preset']) {
                                'yesterday' => 'Yesterday',
                                'this_week' => 'This Week',
                                'this_month' => 'This Month',
                                default => 'Today',
                            };
                        }),

                    Tables\Filters\Filter::make('quick_expiry')
                        ->form([
                            \Filament\Forms\Components\Select::make('preset')
                                ->label('Quick Expiry Date')
                                ->options([
                                    'today' => 'Today',
                                    'yesterday' => 'Yesterday',
                                    'this_week' => 'This Week',
                                    'this_month' => 'This Month',
                                ])
                                ->placeholder('All dates'),
                        ])
                        ->query(function ($query, array $data) {
                            if (empty($data['preset'])) return;
                            match ($data['preset']) {
                                'yesterday' => $query->whereOperationalPeriod('visit_end_at', 'yesterday'),
                                'this_week' => $query->whereOperationalPeriod('visit_end_at', 'this_week'),
                                'this_month' => $query->whereOperationalPeriod('visit_end_at', 'this_month'),
                                default => $query->whereOperationalPeriod('visit_end_at', 'today'),
                            };
                        })
                        ->indicateUsing(function (array $data): ?string {
                            if (empty($data['preset'])) return null;
                            return 'Expiry: ' . match ($data['preset']) {
                                'yesterday' => 'Yesterday',
                                'this_week' => 'This Week',
                                'this_month' => 'This Month',
                                default => 'Today',
                            };
                        }),

                    Tables\Filters\Filter::make('creation_date')
                        ->label('Visit Creation Date')
                        ->form([
                            \Filament\Forms\Components\DatePicker::make('creation_from')->label('Creation From'),
                            \Filament\Forms\Components\DatePicker::make('creation_to')->label('Creation To'),
                        ])
                        ->query(function ($query, array $data) {
                            $from = $data['creation_from'] ?? null;
                            $to = $data['creation_to'] ?? null;
                            if ($from && $to) {
                                $query->whereBetween('visit_created_at', [\Carbon\Carbon::parse($from)->startOfDay(), \Carbon\Carbon::parse($to)->endOfDay()]);
                            } elseif ($from) {
                                $query->where('visit_created_at', '>=', \Carbon\Carbon::parse($from)->startOfDay());
                            } elseif ($to) {
                                $query->where('visit_created_at', '<=', \Carbon\Carbon::parse($to)->endOfDay());
                            }
                        })
                        ->indicateUsing(function (array $data): ?string {
                            $from = $data['creation_from'] ?? null;
                            $to = $data['creation_to'] ?? null;
                            if ($from && $to) return 'Creation: ' . \Carbon\Carbon::parse($from)->format('M d, Y') . ' → ' . \Carbon\Carbon::parse($to)->format('M d, Y');
                            if ($from) return 'Creation From: ' . \Carbon\Carbon::parse($from)->format('M d, Y');
                            if ($to) return 'Creation To: ' . \Carbon\Carbon::parse($to)->format('M d, Y');
                            return null;
                        }),

                    Tables\Filters\Filter::make('expiry_date')
                        ->label('Visit Expiry Date')
                        ->form([
                            \Filament\Forms\Components\DatePicker::make('expiry_from')->label('Expiry From'),
                            \Filament\Forms\Components\DatePicker::make('expiry_to')->label('Expiry To'),
                        ])
                        ->query(function ($query, array $data) {
                            $from = $data['expiry_from'] ?? null;
                            $to = $data['expiry_to'] ?? null;
                            if ($from && $to) {
                                $query->whereBetween('visit_end_at', [\Carbon\Carbon::parse($from)->startOfDay(), \Carbon\Carbon::parse($to)->endOfDay()]);
                            } elseif ($from) {
                                $query->where('visit_end_at', '>=', \Carbon\Carbon::parse($from)->startOfDay());
                            } elseif ($to) {
                                $query->where('visit_end_at', '<=', \Carbon\Carbon::parse($to)->endOfDay());
                            }
                        })
                        ->indicateUsing(function (array $data): ?string {
                            $from = $data['expiry_from'] ?? null;
                            $to = $data['expiry_to'] ?? null;
                            if ($from && $to) return 'Expiry: ' . \Carbon\Carbon::parse($from)->format('M d, Y') . ' → ' . \Carbon\Carbon::parse($to)->format('M d, Y');
                            if ($from) return 'Expiry From: ' . \Carbon\Carbon::parse($from)->format('M d, Y');
                            if ($to) return 'Expiry To: ' . \Carbon\Carbon::parse($to)->format('M d, Y');
                            return null;
                        }),
                ] : [
                    Tables\Filters\Filter::make('quick_date')
                        ->form([
                            \Filament\Forms\Components\Select::make('preset')
                                ->label('Quick Date')
                                ->options([
                                    'today' => 'Today',
                                    'yesterday' => 'Yesterday',
                                    'this_week' => 'This Week',
                                    'this_month' => 'This Month',
                                ])
                                ->placeholder('All dates'),
                        ])
                        ->query(function ($query, array $data) {
                            if (empty($data['preset'])) return;
                            match ($data['preset']) {
                                'yesterday' => $query->whereOperationalPeriod('archived_at', 'yesterday'),
                                'this_week' => $query->whereOperationalPeriod('archived_at', 'this_week'),
                                'this_month' => $query->whereOperationalPeriod('archived_at', 'this_month'),
                                default => $query->whereOperationalPeriod('archived_at', 'today'),
                            };
                        })
                        ->indicateUsing(function (array $data): ?string {
                            if (empty($data['preset'])) return null;
                            return 'Date: ' . match ($data['preset']) {
                                'yesterday' => 'Yesterday',
                                'this_week' => 'This Week',
                                'this_month' => 'This Month',
                                default => 'Today',
                            };
                        }),

                    Tables\Filters\Filter::make('date_range')
                        ->label('Date Range')
                        ->form([
                            \Filament\Forms\Components\DatePicker::make('from')->label('From'),
                            \Filament\Forms\Components\DatePicker::make('to')->label('To'),
                        ])
                        ->query(function ($query, array $data) {
                            $from = $data['from'] ?? null;
                            $to = $data['to'] ?? null;
                            if ($from && $to) {
                                $query->whereBetween('archived_at', [\Carbon\Carbon::parse($from)->startOfDay(), \Carbon\Carbon::parse($to)->endOfDay()]);
                            } elseif ($from) {
                                $query->where('archived_at', '>=', \Carbon\Carbon::parse($from)->startOfDay());
                            } elseif ($to) {
                                $query->where('archived_at', '<=', \Carbon\Carbon::parse($to)->endOfDay());
                            }
                        })
                        ->indicateUsing(function (array $data): ?string {
                            $from = $data['from'] ?? null;
                            $to = $data['to'] ?? null;
                            if ($from && $to) return 'Range: ' . \Carbon\Carbon::parse($from)->format('M d, Y') . ' → ' . \Carbon\Carbon::parse($to)->format('M d, Y');
                            if ($from) return 'From: ' . \Carbon\Carbon::parse($from)->format('M d, Y');
                            if ($to) return 'To: ' . \Carbon\Carbon::parse($to)->format('M d, Y');
                            return null;
                        }),
                ]),

                // Branch filter (enabled for testing)
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->options(\App\Models\Branch::pluck('name', 'id')->all())
                    ->searchable(),
            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No records found')
            ->emptyStateDescription('No archive records found for the selected filters.');
    }

    public function render(): View
    {
        return view('livewire.visits.list-visit-archives');
    }
}

