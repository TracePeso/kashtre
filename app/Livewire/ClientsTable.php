<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\Branch;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Select;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ClientsTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use Tables\Concerns\InteractsWithTable;

    public $selectedBranchId;
    public $availableBranches;

    public function mount()
    {
        $user = Auth::user();
        $this->selectedBranchId = request()->get('branch_id', $user->current_branch->id);
        
        // Get available branches for the user
        $allowedBranches = (array) ($user->allowed_branches ?? []);
        $this->availableBranches = Branch::whereIn('id', $allowedBranches)->get();
    }

    public function table(Table $table): Table
    {
        $user = Auth::user();
        $business = $user->business;
        
        // For Kashtre (business_id == 1), show all clients from all businesses
        if ($business->id == 1) {
            $query = Client::query()
                ->where('business_id', '!=', 1)
                ->with(['business', 'branch']);
        } else {
            $query = Client::query()
                ->where('business_id', '!=', 1)
                ->where('business_id', $business->id)
                ->where('branch_id', $this->selectedBranchId);
        }
        
        return $table
            ->query($query)
            ->columns([
                TextColumn::make('client_id')
                    ->label('Client ID')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                
                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['surname', 'first_name', 'other_names'])
                    ->sortable(['surname', 'first_name'])
                    ->formatStateUsing(fn (Client $record): string => $record->full_name),
                
                TextColumn::make('phone_number')
                    ->label('Phone')
                    ->searchable()
                    ->sortable(),
                
                // Show Business column only for Kashtre users
                ...($business->id == 1 ? [
                    TextColumn::make('business.name')
                        ->label('Business')
                        ->searchable()
                        ->sortable()
                        ->badge()
                        ->color('purple'),
                ] : []),
                
                TextColumn::make('nin')
                    ->label('NIN')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A'),
                
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                
                TextColumn::make('sex')
                    ->label('Gender')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->sortable(),
                
                TextColumn::make('balance')
                    ->label('Balance')
                    ->money('UGX')
                    ->sortable()
                    ->color(fn (float $state): string => $state > 0 ? 'success' : 'gray'),
                
                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                    ]),
                
                SelectFilter::make('sex')
                    ->label('Gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                        'other' => 'Other',
                    ]),
                
                SelectFilter::make('services_category')
                    ->label('Services Category')
                    ->options([
                        'dental' => 'Dental',
                        'optical' => 'Optical',
                        'outpatient' => 'Outpatient',
                        'inpatient' => 'Inpatient',
                        'maternity' => 'Maternity',
                        'funeral' => 'Funeral',
                    ]),
                
                Filter::make('created_at')
                    ->label('Registration Date')
                    ->form([
                        Select::make('date_filter')
                            ->label('Filter by Date')
                            ->options([
                                'today' => 'Today',
                                'yesterday' => 'Yesterday',
                                'this_week' => 'This Week',
                                'this_month' => 'This Month',
                                'last_month' => 'Last Month',
                            ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['date_filter'] ?? null) {
                            'today' => $query->whereOperationalPeriod('created_at', 'today'),
                            'yesterday' => $query->whereOperationalPeriod('created_at', 'yesterday'),
                            'this_week' => $query->whereOperationalPeriod('created_at', 'this_week'),
                            'this_month' => $query->whereOperationalPeriod('created_at', 'this_month'),
                            'last_month' => $query->whereOperationalPeriod('created_at', 'last_month'),
                            default => $query,
                        };
                    })
            ])
            ->actions([
                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-arrow-right')
                    ->color('success')
                    ->url(fn (Client $record): string => route('pos.item-selection', $record)),
                
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->url(fn (Client $record): string => route('clients.show', $record)),
                
                Action::make('balance_history')
                    ->label('Balance Statement')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('info')
                    ->url(fn (Client $record): string => route('balance-statement.show', $record)),
                
                // EditAction::make()
                //     ->label('Edit')
                //     ->icon('heroicon-o-pencil')
                //     ->url(fn (Client $record): string => route('clients.edit', $record)),
                
                // DeleteAction::make()
                //     ->label('Delete')
                //     ->icon('heroicon-o-trash')
                //     ->color('danger')
                //     ->requiresConfirmation()
                //     ->modalHeading('Delete Client')
                //     ->modalDescription('Are you sure you want to delete this client? This action cannot be undone.')
                //     ->modalSubmitActionLabel('Yes, delete client')
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Delete Selected')
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Clients')
                        ->modalDescription('Are you sure you want to delete the selected clients? This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete selected clients'),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public function makeFilamentTranslatableContentDriver(): ?\Filament\Support\Contracts\TranslatableContentDriver
    {
        return null;
    }

    public function updatedSelectedBranchId($value)
    {
        $this->selectedBranchId = $value;
        $this->resetTable();
    }

    public function render()
    {
        return view('livewire.clients-table');
    }
}
