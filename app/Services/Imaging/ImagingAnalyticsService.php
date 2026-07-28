<?php

namespace App\Services\Imaging;

use App\Models\ImagingReport;
use App\Models\ImagingStudy;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Pillar 20: Imaging Analytics. Pure query/aggregation over models that
 * already exist — no new schema needed. $businessId = null means "every
 * business" (the business_id === 1 super-admin case), matching how the
 * list components in this module already treat that account.
 */
class ImagingAnalyticsService
{
    /** @return array<string, int> modality_type => count */
    public function studiesPerModality(?int $businessId): array
    {
        return ImagingStudy::query()
            ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
            ->selectRaw('modality_type, count(*) as total')
            ->groupBy('modality_type')
            ->orderByDesc('total')
            ->pluck('total', 'modality_type')
            ->all();
    }

    /** @return array<string, int> status => count */
    public function procedureVolumes(?int $businessId): array
    {
        return ImagingStudy::query()
            ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    public function criticalFindings(?int $businessId, int $limit = 10): Collection
    {
        return ImagingReport::query()
            ->where('is_critical_finding', true)
            ->whereHas('imagingStudy', fn ($q) => $q->when($businessId, fn ($q2) => $q2->where('business_id', $businessId)))
            ->with('imagingStudy')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /** @return array<int, array{name: string, authored: int, verified: int}> */
    public function radiologistProductivity(?int $businessId): array
    {
        $scope = fn ($q) => $q->whereHas('imagingStudy', fn ($q2) => $q2->when($businessId, fn ($q3) => $q3->where('business_id', $businessId)));

        $authored = $scope(ImagingReport::query())
            ->selectRaw('author_user_id, count(*) as total')
            ->groupBy('author_user_id')
            ->pluck('total', 'author_user_id');

        $verified = $scope(ImagingReport::query())
            ->whereNotNull('verified_by_user_id')
            ->selectRaw('verified_by_user_id, count(*) as total')
            ->groupBy('verified_by_user_id')
            ->pluck('total', 'verified_by_user_id');

        $userIds = $authored->keys()->merge($verified->keys())->unique()->values();
        $names = User::whereIn('id', $userIds)->pluck('name', 'id');

        return $userIds->map(fn ($id) => [
            'name' => $names[$id] ?? "User #{$id}",
            'authored' => (int) ($authored[$id] ?? 0),
            'verified' => (int) ($verified[$id] ?? 0),
        ])->sortByDesc('authored')->values()->all();
    }

    public function averageReportTurnaroundHours(?int $businessId): ?float
    {
        $hours = ImagingReport::query()
            ->whereNotNull('reported_at')
            ->whereHas('imagingStudy', fn ($q) => $q->when($businessId, fn ($q2) => $q2->where('business_id', $businessId)))
            ->with('imagingStudy')
            ->get()
            ->map(fn (ImagingReport $r) => self::hoursBetween($r->imagingStudy?->image_acquired_at, $r->reported_at))
            ->filter(fn ($h) => $h !== null);

        return $hours->isEmpty() ? null : round($hours->avg(), 2);
    }

    public function averageVerificationDelayHours(?int $businessId): ?float
    {
        $hours = ImagingReport::query()
            ->whereNotNull('reported_at')
            ->whereNotNull('verified_at')
            ->whereHas('imagingStudy', fn ($q) => $q->when($businessId, fn ($q2) => $q2->where('business_id', $businessId)))
            ->get()
            ->map(fn (ImagingReport $r) => self::hoursBetween($r->reported_at, $r->verified_at))
            ->filter(fn ($h) => $h !== null);

        return $hours->isEmpty() ? null : round($hours->avg(), 2);
    }

    /**
     * Pure calculation, split out so it's unit-testable without the DB.
     */
    public static function hoursBetween(?\DateTimeInterface $start, ?\DateTimeInterface $end): ?float
    {
        if (! $start || ! $end) {
            return null;
        }

        return round(Carbon::parse($start)->diffInMinutes(Carbon::parse($end)) / 60, 2);
    }
}
