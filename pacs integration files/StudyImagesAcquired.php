<?php

// app/Events/StudyImagesAcquired.php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once a study's images have settled in the PACS and the RIS has advanced to
 * IMAGE_ACQUIRED. Keep the webhook thin — attach listeners for:
 *   - reading the Radiation Dose SR from Orthanc into the longitudinal dose record
 *   - running RadiologyRecipeEngine to deplete inventory for IMAGE_ACQUIRED-attributed protocols
 *   - any downstream notifications
 *
 * Carries only the study id so listeners re-load fresh state (safe under queueing).
 */
class StudyImagesAcquired
{
    use Dispatchable, SerializesModels;

    public function __construct(public int $studyId)
    {
    }
}
