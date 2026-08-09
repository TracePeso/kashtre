<?php

namespace App\Services\Imaging;

use App\Contracts\DicomWorklistBroker;
use Illuminate\Support\Facades\Log;

/**
 * Pillar 1.1 stub. No real MWL/DICOM endpoint exists yet, so this logs what
 * would have been broadcast instead of failing or blocking the workflow —
 * markReadyForStudy() must never fail because a scanner isn't wired up.
 * Swap the AppServiceProvider binding for an HTTP-backed implementation
 * (same idiom as CallingServiceClient — Http::baseUrl()->post(), wrapped in
 * try/catch, non-blocking) once real hardware exists.
 */
class LoggingDicomWorklistBroker implements DicomWorklistBroker
{
    public function pushRecord(array $record): ?string
    {
        Log::info('[DICOM MWL stub] No broker configured — would have pushed record.', $record);

        return null;
    }
}
