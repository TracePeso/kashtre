<?php

namespace App\Services\Imaging;

use App\Contracts\PacsClient;
use App\Models\ImagingStudy;
use Illuminate\Support\Facades\Log;

/**
 * Pillars 7 & 8 stub. No real PACS exists yet — every method logs and
 * returns the "not available" value (null/false) rather than erroring, so
 * callers (ImagingStudyController) can render a clean "not configured"
 * message instead of crashing. Swap the AppServiceProvider binding for a
 * real client once a PACS endpoint exists; no caller needs to change.
 */
class StubPacsClient implements PacsClient
{
    public function viewerUrl(ImagingStudy $study): ?string
    {
        $baseUrl = config('services.pacs.viewer_url');

        if (! $baseUrl) {
            Log::info("[PACS stub] viewerUrl() requested for study {$study->accession_number} — no viewer configured.");

            return null;
        }

        $studyUid = $study->reports()->latest()->value('pacs_study_uid') ?? $study->accession_number;

        return rtrim($baseUrl, '/')."/studies/{$studyUid}";
    }

    public function archive(ImagingStudy $study): bool
    {
        Log::info("[PACS stub] archive() called for study {$study->accession_number} — no-op.");

        return false;
    }

    public function retrieve(ImagingStudy $study): bool
    {
        Log::info("[PACS stub] retrieve() called for study {$study->accession_number} — no-op.");

        return false;
    }
}
