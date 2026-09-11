<?php

namespace App\Livewire\Imaging;

use App\Models\ImagingModuleConfig;
use App\Models\PeerReviewCase;
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

class ListPeerReviewCases extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $query = PeerReviewCase::query()->latest();

        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->whereHas('imagingStudy', fn ($q) => $q->where('business_id', Auth::user()->business_id));
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('imagingStudy.accession_number')
                    ->label('Accession #')
                    ->searchable(),

                Tables\Columns\TextColumn::make('original_author_user_id')
                    ->label('Original Author')
                    ->getStateUsing(fn (PeerReviewCase $record) => $record->resolveOriginalAuthor()?->name ?? '—'),

                Tables\Columns\TextColumn::make('qa_status')
                    ->label('QA Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        PeerReviewCase::QA_STATUS_AWAITING_BLIND_READ => 'warning',
                        PeerReviewCase::QA_STATUS_COMPLETED => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => (string) str($state)->replace('_', ' ')->title()),

                Tables\Columns\TextColumn::make('variation_score')
                    ->label('Variation')
                    ->placeholder('—')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        PeerReviewCase::VARIATION_CONCORDANT => 'success',
                        PeerReviewCase::VARIATION_MINOR_DISCORDANCE => 'warning',
                        PeerReviewCase::VARIATION_MAJOR_DISCORDANCE => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => $state ? (string) str($state)->replace('_', ' ')->title() : null),

                Tables\Columns\TextColumn::make('reviewer_user_id')
                    ->label('Reviewer')
                    ->getStateUsing(fn (PeerReviewCase $record) => $record->resolveReviewer()?->name ?? '—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Flagged At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('qa_status')
                    ->options([
                        PeerReviewCase::QA_STATUS_AWAITING_BLIND_READ => 'Awaiting Blind Read',
                        PeerReviewCase::QA_STATUS_COMPLETED => 'Completed',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('review')
                    ->label('Complete Blind Review')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (PeerReviewCase $record) => $record->isStatus(PeerReviewCase::QA_STATUS_AWAITING_BLIND_READ)
                        && Auth::id() !== $record->original_author_user_id
                        && in_array('Complete Peer Review Cases', Auth::user()->permissions ?? [])
                        && ImagingModuleConfig::isEligibleReviewer((int) $record->imagingStudy?->business_id, Auth::id()))
                    ->modalHeading('Blind Peer Review')
                    ->modalDescription('Record your own independent findings before anything from the original report is shown to you.')
                    ->form(function (PeerReviewCase $record) {
                        $sections = $record->imagingStudy?->protocol()?->reporting_template['sections'] ?? [];

                        $fields = [];

                        foreach ($sections as $section) {
                            $fields[] = Forms\Components\Textarea::make("sections.{$section}")
                                ->label($section)
                                ->rows(2);
                        }

                        $fields[] = Forms\Components\Select::make('variation_score')
                            ->label('Variation vs. Original Report')
                            ->options([
                                \App\Models\PeerReviewCase::VARIATION_CONCORDANT => 'Concordant',
                                \App\Models\PeerReviewCase::VARIATION_MINOR_DISCORDANCE => 'Minor Discordance',
                                \App\Models\PeerReviewCase::VARIATION_MAJOR_DISCORDANCE => 'Major Discordance',
                            ])
                            ->required();

                        $fields[] = Forms\Components\Textarea::make('reviewer_notes')
                            ->label('Notes')
                            ->rows(2);

                        return $fields;
                    })
                    ->action(function (PeerReviewCase $record, array $data) {
                        $record->markCompleted(
                            Auth::id(),
                            $data['variation_score'],
                            $data['reviewer_notes'] ?? null,
                            $data['sections'] ?? []
                        );

                        Notification::make()
                            ->title('Peer review recorded.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function render(): View
    {
        return view('livewire.imaging.list-peer-review-cases');
    }
}
