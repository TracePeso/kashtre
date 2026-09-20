<?php

namespace App\Livewire\DailyVisits;

use App\Models\Client;
use App\Support\SharedTime;
use App\Models\Invoice;
use App\Models\Business;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class ListDailyVisits extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $user = Auth::user();
        $currentBranchId = optional($user->current_branch)->id;
        $allowedBranches = (array) ($user->allowed_branches ?? []);

        $query = Client::query()
            ->with(['business', 'branch'])
            ->latest('updated_at');

        // Restrict by business
        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->where('business_id', Auth::user()->business_id);
            // Default branch constraint: user's current branch, or allowed branches fallback
            if (!empty($currentBranchId)) {
                $query->where('branch_id', $currentBranchId);
            } elseif (!empty($allowedBranches)) {
                $query->whereIn('branch_id', $allowedBranches);
            }
        } else {
            // Kashtre can see all
            $query->where('business_id', '!=', 1);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Visit Creation Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('visit_expires_at')
                    ->label('Visit Expiry Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('client_id')
                    ->label('Client ID')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Client Name')
                    ->searchable(['surname', 'first_name', 'other_names'])
                    ->formatStateUsing(fn (Client $record): string => $record->full_name),

                Tables\Columns\TextColumn::make('phone_number')
                    ->label('Phone')
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('visit_id')
                    ->label('Visit ID')
                    ->searchable(),

                ...(Auth::check() && Auth::user()->business_id === 1 ? [
                    Tables\Columns\TextColumn::make('business.name')
                        ->label('Business')
                        ->sortable()
                        ->searchable(),
                    Tables\Columns\TextColumn::make('branch.name')
                        ->label('Branch')
                        ->sortable()
                        ->searchable(),
                ] : []),
            ])
            ->filters([
                ...(Auth::check() && Auth::user()->business_id === 1 ? [
                    Tables\Filters\SelectFilter::make('business_id')
                        ->label('Business')
                        ->options(Business::pluck('name', 'id')->all())
                        ->searchable(),
                ] : []),

                // Branch filter for non-Kashtre users
                ...(Auth::check() && Auth::user()->business_id !== 1 ? [
                    Tables\Filters\SelectFilter::make('branch_id')
                        ->label('Branch')
                        ->options(function () {
                            $user = Auth::user();
                            $allowed = (array) ($user->allowed_branches ?? []);
                            return \App\Models\Branch::whereIn('id', $allowed)->pluck('name', 'id')->all();
                        })
                        ->searchable(),
                ] : []),

                // Quick date presets (removable default)
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
                            ->placeholder('All dates')
                    ])
                    ->query(function ($query, array $data) {
                        if (empty($data['preset'])) return;
                        match ($data['preset']) {
                            'yesterday' => $query->whereOperationalPeriod('created_at', 'yesterday'),
                            'this_week' => $query->whereOperationalPeriod('created_at', 'this_week'),
                            'this_month' => $query->whereOperationalPeriod('created_at', 'this_month'),
                            default => $query->whereOperationalPeriod('created_at', 'today'),
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
                            ->placeholder('All dates')
                    ])
                    ->query(function ($query, array $data) {
                        if (empty($data['preset'])) return;
                        match ($data['preset']) {
                            'yesterday' => $query->whereOperationalPeriod('visit_expires_at', 'yesterday'),
                            'this_week' => $query->whereOperationalPeriod('visit_expires_at', 'this_week'),
                            'this_month' => $query->whereOperationalPeriod('visit_expires_at', 'this_month'),
                            default => $query->whereOperationalPeriod('visit_expires_at', 'today'),
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

                // Filter by visit creation date
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
                            $query->whereBetween('created_at', [\Carbon\Carbon::parse($from)->startOfDay(), \Carbon\Carbon::parse($to)->endOfDay()]);
                        } elseif ($from) {
                            $query->where('created_at', '>=', \Carbon\Carbon::parse($from)->startOfDay());
                        } elseif ($to) {
                            $query->where('created_at', '<=', \Carbon\Carbon::parse($to)->endOfDay());
                        }
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $from = $data['creation_from'] ?? null;
                        $to = $data['creation_to'] ?? null;
                        if ($from && $to) {
                            return 'Creation: ' . \Carbon\Carbon::parse($from)->format('M d, Y') . ' → ' . \Carbon\Carbon::parse($to)->format('M d, Y');
                        }
                        if ($from) return 'Creation From: ' . \Carbon\Carbon::parse($from)->format('M d, Y');
                        if ($to) return 'Creation To: ' . \Carbon\Carbon::parse($to)->format('M d, Y');
                        return null;
                    }),

                // Filter by visit expiry date
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
                            $query->whereBetween('visit_expires_at', [\Carbon\Carbon::parse($from)->startOfDay(), \Carbon\Carbon::parse($to)->endOfDay()]);
                        } elseif ($from) {
                            $query->where('visit_expires_at', '>=', \Carbon\Carbon::parse($from)->startOfDay());
                        } elseif ($to) {
                            $query->where('visit_expires_at', '<=', \Carbon\Carbon::parse($to)->endOfDay());
                        }
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $from = $data['expiry_from'] ?? null;
                        $to = $data['expiry_to'] ?? null;
                        if ($from && $to) {
                            return 'Expiry: ' . \Carbon\Carbon::parse($from)->format('M d, Y') . ' → ' . \Carbon\Carbon::parse($to)->format('M d, Y');
                        }
                        if ($from) return 'Expiry From: ' . \Carbon\Carbon::parse($from)->format('M d, Y');
                        if ($to) return 'Expiry To: ' . \Carbon\Carbon::parse($to)->format('M d, Y');
                        return null;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('details')
                    ->label('Details')
                    ->url(fn (Client $record): string => route('pos.item-selection', $record))
                    ->color('success'),
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->url(fn (Client $record): string => route('clients.show', $record))
                    ->color('primary'),
            ])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading('No visits found')
            ->emptyStateDescription('No client visits recorded for the selected date.')
            ->emptyStateIcon('heroicon-o-calendar');
    }

    public function render(): View
    {
        return view('livewire.daily-visits.list-daily-visits');
    }
}


