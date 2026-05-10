<?php

namespace App\Livewire;

use App\Models\MoneyTransfer;
use App\Models\MoneyAccount;
use App\Models\Business;
use App\Models\Client;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class SuspenseAccountsTable extends Component implements HasTable, HasForms
{
    use InteractsWithTable, InteractsWithForms;

    public string $activeTab = 'package';

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
        
        // Force refresh the table data
        $this->resetTable();
    }

    public function getSuspenseAccountData()
    {
        $businessId = Auth::user()->business_id;
        
        // Get the account type based on active tab
        $accountType = match($this->activeTab) {
            'package' => 'package_suspense_account',
            'general' => 'general_suspense_account',
            'kashtre' => 'kashtre_suspense_account',
            'withdrawal' => 'withdrawal_suspense_account',
            default => 'package_suspense_account'
        };

        // Get the specific suspense account for this business and type
        $suspenseAccount = MoneyAccount::where('business_id', $businessId)
            ->where('type', $accountType)
            ->first();

        if (!$suspenseAccount) {
            return [
                'balance' => 0,
                'transfers_count' => 0,
                'account_name' => ucfirst(str_replace('_', ' ', $accountType)),
                'account_type' => $accountType
            ];
        }

        // Get transfer count for this specific account
        $transfersCount = MoneyTransfer::where('to_account_id', $suspenseAccount->id)->count();

        return [
            'balance' => $suspenseAccount->balance,
            'transfers_count' => $transfersCount,
            'account_name' => $suspenseAccount->name,
            'account_type' => $accountType
        ];
    }

    public function table(Table $table): Table
    {
        $businessId = Auth::user()->business_id;

        // Determine the account type based on active tab
        $accountType = match($this->activeTab) {
            'package' => 'package_suspense_account',
            'general' => 'general_suspense_account',
            'kashtre' => 'kashtre_suspense_account',
            'withdrawal' => 'withdrawal_suspense_account',
            default => 'package_suspense_account'
        };

        // All suspense accounts use MoneyTransfer for consistency (same table format)
        // For withdrawal suspense accounts, only show withdrawal-related transfers
        if ($accountType === 'withdrawal_suspense_account') {
            // For withdrawal suspense, only show withdrawal-related transfers
            if ($businessId == 1) {
                $query = MoneyTransfer::query()
                    ->where(function($query) use ($accountType) {
                        $query->whereHas('toAccount', function($q) use ($accountType) {
                            $q->where('type', $accountType);
                        })->orWhereHas('fromAccount', function($q) use ($accountType) {
                            $q->where('type', $accountType);
                        });
                    })
                    ->where(function($query) {
                        $query->where('transfer_type', 'withdrawal_hold')
                              ->orWhere('transfer_type', 'withdrawal_processed')
                              ->orWhere('transfer_type', 'withdrawal_rejected')
                              ->orWhere('description', 'like', '%Withdrawal%')
                              ->orWhere('description', 'like', '%Bank Schedule%');
                    })
                    ->with(['fromAccount.client', 'fromAccount.business', 'toAccount.client', 'toAccount.business', 'invoice.client.insuranceCompany', 'client', 'business'])
                    ->orderBy('created_at', 'desc');
            } else {
                // For regular businesses, only show their own withdrawal transfers
                $query = MoneyTransfer::query()
                    ->where(function($query) use ($businessId, $accountType) {
                        $query->whereHas('toAccount', function($q) use ($businessId, $accountType) {
                            $q->where('business_id', $businessId)
                              ->where('type', $accountType);
                        })->orWhereHas('fromAccount', function($q) use ($businessId, $accountType) {
                            $q->where('business_id', $businessId)
                              ->where('type', $accountType);
                        });
                    })
                    ->where(function($query) {
                        $query->where('transfer_type', 'withdrawal_hold')
                              ->orWhere('transfer_type', 'withdrawal_processed')
                              ->orWhere('transfer_type', 'withdrawal_rejected')
                              ->orWhere('description', 'like', '%Withdrawal%')
                              ->orWhere('description', 'like', '%Bank Schedule%');
                    })
                    ->with(['fromAccount.client', 'fromAccount.business', 'toAccount.client', 'toAccount.business', 'invoice.client.insuranceCompany', 'client', 'business'])
                    ->orderBy('created_at', 'desc');
            }
        } else {
            // For other suspense accounts, use the normal query
            if ($businessId == 1) {
                $query = MoneyTransfer::query()
                    ->where(function($query) use ($accountType) {
                        $query->whereHas('toAccount', function($q) use ($accountType) {
                            $q->where('type', $accountType);
                        })->orWhereHas('fromAccount', function($q) use ($accountType) {
                            $q->where('type', $accountType);
                        });
                    })
                    ->with(['fromAccount.client', 'fromAccount.business', 'toAccount.client', 'toAccount.business', 'invoice.client.insuranceCompany', 'client', 'business'])
                    ->orderBy('created_at', 'desc');
            } else {
                // For regular businesses, only show their own transfers
                $query = MoneyTransfer::query()
                    ->where(function($query) use ($businessId, $accountType) {
                        $query->whereHas('toAccount', function($q) use ($businessId, $accountType) {
                            $q->where('business_id', $businessId)
                              ->where('type', $accountType);
                        })->orWhereHas('fromAccount', function($q) use ($businessId, $accountType) {
                            $q->where('business_id', $businessId)
                              ->where('type', $accountType);
                        });
                    })
                    ->with(['fromAccount.client', 'fromAccount.business', 'toAccount.client', 'toAccount.business', 'invoice.client.insuranceCompany', 'client', 'business'])
                    ->orderBy('created_at', 'desc');
            }
        }

        // All suspense accounts use the same columns (same table format)
        $columns = [
                TextColumn::make('movement_type')
                    ->label('Type')
                    ->badge()
                    ->state(function (MoneyTransfer $record) use ($accountType, $businessId) {
                        $matchesDestination = $record->toAccount
                            && $record->toAccount->type === $accountType
                            && ($businessId == 1 || $record->toAccount->business_id == $businessId);

                        $matchesSource = $record->fromAccount
                            && $record->fromAccount->type === $accountType
                            && ($businessId == 1 || $record->fromAccount->business_id == $businessId);

                        if ($matchesSource && ! $matchesDestination) {
                            return 'debit';
                        }

                        if ($matchesDestination && ! $matchesSource) {
                            return 'credit';
                        }

                        // Fall back to original transfer type if both sides match (rare) or neither
                        return $record->type === 'debit' ? 'debit' : 'credit';
                    })
                    ->color(fn ($state) => $state === 'debit' ? 'danger' : 'success'),
                
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                
                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->visible(fn () => $this->activeTab !== 'withdrawal'),
                
                TextColumn::make('business.name')
                    ->label('Business')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ?? 'N/A'),
                
                TextColumn::make('package_tracking_number')
                    ->label('Package Tracking')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ?? 'No tracking number')
                    ->visible(fn () => $this->activeTab === 'package'),
                
                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                
                TextColumn::make('source_destination')
                    ->label('Source / Destination')
                    ->state(fn (MoneyTransfer $record): string => $record->resolvedInsuranceSourceDestinationLabel())
                    ->limit(50),
                
                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('UGX')
                    ->sortable(),
        ];

        return $table
            ->query($query)
            ->columns($columns)
            ->filters([
                // Filters removed for cleaner interface
            ])
            ->actions([
                // Add any actions if needed
            ])
            ->bulkActions([
                // Add any bulk actions if needed
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100]);
    }

    public function render()
    {
        return view('livewire.suspense-accounts-table');
    }

    public function makeFilamentTranslatableContentDriver(): ?\Filament\Support\Contracts\TranslatableContentDriver
    {
        return null;
    }

    public function hasCachedForm(string $name): bool
    {
        return false;
    }

    public function hasCachedAction(string $name): bool
    {
        return false;
    }

    public function hasCachedBulkAction(string $name): bool
    {
        return false;
    }

    public function makeForm(string $name = 'default', array $schema = []): \Filament\Forms\Form
    {
        return \Filament\Forms\Form::make($this, $name)
            ->schema($schema);
    }
}
