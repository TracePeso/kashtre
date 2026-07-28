<?php

namespace App\Jobs;

use App\Contracts\DicomWorklistBroker;
use App\Models\ImagingStudy;
use App\Support\DicomUid;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pillar 1.1: dispatched the moment a study transitions to READY_FOR_STUDY
 * (see ImagingStudy::markReadyForStudy()). ShouldQueue per the spec's
 * technical sign-off requirement — this must never block a technician's
 * console response.
 */
class BroadcastModalityWorklist implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly int $studyId) {}

    public function handle(DicomWorklistBroker $broker): void
    {
        $study = ImagingStudy::find($this->studyId);

        if (! $study) {
            return;
        }

        // Generated once so the modality reuses it and the eventual
        // StableStudy webhook can cross-check the acquired image's UID.
        if (blank($study->study_instance_uid)) {
            $study->study_instance_uid = DicomUid::generate();
            $study->save();
        }

        $client = $study->resolveClient();

        $worklistId = $broker->pushRecord([
            'accession_number' => $study->accession_number,
            'patient_id' => $study->client_id,
            'patient_name' => $client?->full_name ?? 'Unknown',
            'modality' => $study->modality_type,
            'ae_title' => $study->resolveHardwareAeTitle(),
            'study_instance_uid' => $study->study_instance_uid,
        ]);

        if ($worklistId !== null) {
            $study->orthanc_worklist_id = $worklistId;
            $study->save();
        }
    }
}
