<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once a study's images have settled in Orthanc and
 * OrthancWebhookController has advanced it to IMAGE_ACQUIRED. Inventory
 * depletion already happens synchronously inside
 * ImagingStudy::markImageAcquired() (triggerConsumptionIfDue()) — this
 * event isn't for that. It's an extension point for future listeners, e.g.
 * ingesting Orthanc's radiation dose structured report into
 * radiation_exposure_logs instead of today's manual entry — nothing
 * listens yet.
 *
 * Carries only the study id so listeners re-load fresh state (safe under queueing).
 */
class StudyImagesAcquired
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public int $studyId) {}
}
