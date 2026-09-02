<?php

namespace App\Livewire\Transactions;

use App\Models\Transaction;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Transactions extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        // $query = Transaction::query()
        $query = Transaction::query()->where('business_id', '!=', 1)->latest(); // Orders by created_at DESC by default

         //get the lastest transactions

        ;

        // If not admin, limit to their business_id
        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->where('business_id', Auth::user()->business_id);
        }

        return $table
            ->query($query)
            ->columns([
                // Time column
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('M d, Y H:i:s')
                    ->sortable()
                    ->searchable(),

                // Name column
                Tables\Columns\TextColumn::make('names')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                // Client ID column (using reference as client identifier)
                Tables\Columns\TextColumn::make('reference')
                    ->label('Client ID')
                    ->searchable()
                    ->copyable()
                    ->sortable()
                    ->limit(20),

                // Description column
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(40)
                    ->searchable()
                    ->sortable(),

                // Amount column
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->sortable()
                    ->money('UGX')
                    ->searchable()
                    ->alignRight(),

                // Method column
                Tables\Columns\TextColumn::make('method')
                    ->label('Method')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'mobile_money' => 'success',
                        'card' => 'info',
                        'bank_transfer' => 'warning',
                        'crypto' => 'danger',
                        default => 'gray',
                    }),

                // Status column
                Auth::user()?->business_id == 1
                    ? Tables\Columns\SelectColumn::make('status')
                        ->options([
                            'pending' => 'Pending',
                            'completed' => 'Completed',
                            'failed' => 'Failed',
                            'cancelled' => 'Cancelled',
                            'processing' => 'Processing',
                        ])
                        ->label('Status')
                        ->sortable()
                    : Tables\Columns\TextColumn::make('status')
                        ->label('Status')
                        ->sortable()
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'completed' => 'success',
                            'pending' => 'warning',
                            'processing' => 'info',
                            'failed' => 'danger',
                            'cancelled' => 'gray',
                            default => 'gray',
                        }),

                // Additional columns for admin users
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable()
                    ->visible(Auth::user()?->business_id === 1),

                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->searchable()
                    ->sortable()
                    ->visible(Auth::user()?->business_id === 1),

                Tables\Columns\TextColumn::make('phone_number')
                    ->label('Phone')
                    ->searchable()
                    ->visible(Auth::user()?->business_id === 1),

                Tables\Columns\TextColumn::make('provider')
                    ->label('Provider')
                    ->sortable()
                    ->visible(Auth::user()?->business_id === 1),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'credit' => 'success',
                        'debit' => 'danger',
                        default => 'gray',
                    })
                    ->visible(Auth::user()?->business_id === 1),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                        'processing' => 'Processing',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'credit' => 'Credit',
                        'debit' => 'Debit',
                    ]),
                Tables\Filters\SelectFilter::make('provider')
                    ->options([
                        'mtn' => 'MTN',
                        'airtel' => 'Airtel',
                    ]),
                Tables\Filters\SelectFilter::make('transaction_for')
                    ->options([
                        'main' => 'Main',
                        'suspense' => 'Suspense',
                    ]),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('date_from'),
                        Forms\Components\DatePicker::make('date_to'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('reinitiate')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Transaction $record): bool => 
                        $record->status === 'failed' && 
                        $record->method === 'mobile_money' && 
                        $record->provider === 'yo'
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Reinitiate Payment')
                    ->modalDescription(fn (Transaction $record): string => 
                        "Are you sure you want to reinitiate this failed payment of UGX " . number_format($record->amount, 2) . "?"
                    )
                    ->modalSubmitActionLabel('Yes, Reinitiate')
                    ->action(function (Transaction $record): void {
                        try {
                            $response = \Http::withHeaders([
                                'Content-Type' => 'application/json',
                                'X-CSRF-TOKEN' => csrf_token(),
                            ])->post(url('/invoices/reinitiate-failed-transaction'), [
                                'transaction_id' => $record->id
                            ]);

                            $data = $response->json();

                            if ($data['success']) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Payment Reinitiated!')
                                    ->body($data['message'] ?? 'Payment has been reinitiated successfully.')
                                    ->success()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Error!')
                                    ->body($data['message'] ?? 'Failed to reinitiate payment.')
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Error!')
                                ->body('An error occurred while reinitiating the payment.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function render(): View
    {
        return view('livewire.transactions.transactions');
    }
}
