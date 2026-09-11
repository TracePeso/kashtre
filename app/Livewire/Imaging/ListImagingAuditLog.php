<?php

namespace App\Livewire\Imaging;

use App\Models\ActivityLog;
use App\Models\ContrastAdministration;
use App\Models\ImagingOrder;
use App\Models\ImagingReport;
use App\Models\ImagingStudy;
use App\Models\PeerReviewCase;
use App\Models\RadiationExposureLog;
use App\Models\RecoveryRecord;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Pillar 19: Security & Audit Trail — a read surface for the ActivityLog
 * rows ModelActivityObserver (CRUD) and ImagingAuditService (view/open/
 * export) write for every Imaging model. Reuses the shared ActivityLog
 * table rather than a separate Imaging-only log.
 */
class ListImagingAuditLog extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    const IMAGING_MODEL_TYPES = [
        ImagingOrder::class,
        ImagingStudy::class,
        ImagingReport::class,
        PeerReviewCase::class,
        ContrastAdministration::class,
        RecoveryRecord::class,
        RadiationExposureLog::class,
    ];

    public function table(Table $table): Table
    {
        $query = ActivityLog::query()
            ->whereIn('model_type', self::IMAGING_MODEL_TYPES)
            ->latest();

        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->where('business_id', Auth::user()->business_id);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->default('System'),

                Tables\Columns\TextColumn::make('action')
                    ->badge(),

                Tables\Columns\TextColumn::make('model_type')
                    ->label('Model')
                    ->formatStateUsing(fn (string $state) => class_basename($state)),

                Tables\Columns\TextColumn::make('description')
                    ->wrap()
                    ->limit(80),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options(fn () => ActivityLog::query()
                        ->whereIn('model_type', self::IMAGING_MODEL_TYPES)
                        ->distinct()
                        ->pluck('action', 'action')
                        ->all()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function render(): View
    {
        return view('livewire.imaging.list-imaging-audit-log');
    }
}
