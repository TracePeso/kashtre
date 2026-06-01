<?php

namespace App\Livewire\Inventory;

use App\Models\Business;
use App\Models\Suom;
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

class ListSuoms extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function openCreateModal(): void
    {
        $this->mountTableAction('create');
    }

    public function table(Table $table): Table
    {
        $query = Suom::query()->where('business_id', '!=', 1)->orderBy('name');

        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->where('business_id', Auth::user()->business_id);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('SUOM')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->sortable()
                    ->visible(fn (): bool => Auth::user()->business_id === 1),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                ...(Auth::user()->business_id === 1 ? [
                    Tables\Filters\SelectFilter::make('business_id')
                        ->label('Business')
                        ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                        ->searchable(),
                ] : []),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Edit SUOM')
                    ->form($this->suomForm())
                    ->successNotificationTitle('SUOM updated successfully.'),

                DeleteAction::make()
                    ->modalHeading('Delete SUOM')
                    ->successNotificationTitle('SUOM deleted successfully.'),
            ])
            ->headerActions([
                $this->makeCreateSuomAction(),
            ])
            ->emptyStateActions([
                $this->makeCreateSuomAction()
                    ->label('Add SUOM'),
            ])
            ->defaultSort('name')
            ->striped()
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No SUOMs yet')
            ->emptyStateDescription('Add sale units of measure (e.g. tab, cap, vial, bottle) for use on goods received notes.');
    }

    public function render(): View
    {
        return view('livewire.inventory.list-suoms');
    }

    private function makeCreateSuomAction(): CreateAction
    {
        return CreateAction::make()
            ->label('Add SUOM')
            ->modalHeading('Add SUOM')
            ->form($this->suomForm())
            ->mutateFormDataUsing(function (array $data): array {
                if (Auth::user()->business_id !== 1) {
                    $data['business_id'] = Auth::user()->business_id;
                }

                return $data;
            })
            ->createAnother(false)
            ->after(function () {
                Notification::make()
                    ->title('SUOM created successfully.')
                    ->success()
                    ->send();
            });
    }

    private function suomForm(): array
    {
        return [
            Forms\Components\Select::make('business_id')
                ->label('Business')
                ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                ->required()
                ->default(Auth::user()->business_id)
                ->disabled(fn (): bool => Auth::user()->business_id !== 1)
                ->visible(fn (): bool => Auth::user()->business_id === 1),

            Forms\Components\TextInput::make('name')
                ->label('SUOM name')
                ->placeholder('e.g. tab, cap, vial, bottle')
                ->required()
                ->maxLength(50),

            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->nullable()
                ->maxLength(255),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ];
    }
}
