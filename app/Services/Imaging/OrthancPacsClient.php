<?php

namespace App\Services\Imaging;

use App\Contracts\PacsClient;
use App\Models\ImagingStudy;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pillars 7 & 8 real implementation, swapped in for StubPacsClient once
 * Orthanc is configured (AppServiceProvider::register()).
 *
 * Orthanc doesn't expose separate "viewer"/"archive"/"retrieve" actions the
 * way a classic PACS does — it auto-persists on C-STORE and its own
 * Explorer 2 UI serves as the viewer. Every method here maps honestly onto
 * what Orthanc actually offers rather than faking classic-PACS semantics.
 */
class OrthancPacsClient implements PacsClient
{
    public function __construct(private readonly OrthancClient $orthanc) {}

    public function viewerUrl(ImagingStudy $study): ?string
    {
        if (blank($study->orthanc_study_id)) {
            Log::info("[Orthanc PACS] viewerUrl() requested for study {$study->accession_number} — not yet matched into Orthanc.");

            return null;
        }

        $baseUrl = rtrim((string) config('services.orthanc.url'), '/');

        return "{$baseUrl}/ui/app/#/studies/{$study->orthanc_study_id}";
    }

    /**
     * Orthanc auto-archives on C-STORE — there's no separate archive action
     * to trigger. "Archived" just means Orthanc has the study.
     */
    public function archive(ImagingStudy $study): bool
    {
        return $this->existsInOrthanc($study);
    }

    /**
     * A local Orthanc instance already holds whatever it has — "retrieve"
     * here means "confirm it's actually there," not a C-MOVE.
     */
    public function retrieve(ImagingStudy $study): bool
    {
        return $this->existsInOrthanc($study);
    }

    private function existsInOrthanc(ImagingStudy $study): bool
    {
        if (blank($study->orthanc_study_id)) {
            return false;
        }

        try {
            return $this->orthanc->studyExists($study->orthanc_study_id);
        } catch (Throwable $e) {
            Log::warning("[Orthanc PACS] existence check failed for study {$study->accession_number}: {$e->getMessage()}");

            return false;
        }
    }
}
