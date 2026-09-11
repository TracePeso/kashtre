<?php

// app/Jobs/BroadcastModalityWorklist.php

namespace App\Jobs;

use App\Models\ImagingStudy;
use App\Services\OrthancClient;
use App\Support\DicomUid;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Creates a DICOM Modality Worklist entry in Orthanc (via the Worklists plugin REST API)
 * when a study reaches READY_FOR_STUDY. The modality then pulls it via C-FIND.
 *
 * The modality is the C-FIND *client*; Orthanc is the worklist SCP. This job only tells
 * Orthanc which entry to serve — it never opens a DICOM socket to the scanner.
 */
class BroadcastModalityWorklist implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 15;

    public function __construct(public int $studyId)
    {
    }

    public function uniqueId(): string
    {
        return 'mwl-' . $this->studyId;
    }

    public function handle(OrthancClient $orthanc): void
    {
        $study = ImagingStudy::with(['patient', 'protocol', 'servicePoint'])->find($this->studyId);

        if ($study === null) {
            return; // study deleted before the job ran
        }

        if ($study->status !== 'READY_FOR_STUDY') {
            Log::info("MWL skipped: study {$study->id} is '{$study->status}', not READY_FOR_STUDY.");
            return;
        }

        $aeTitle = $study->servicePoint?->hardware_ae_title;
        if (blank($aeTitle)) {
            Log::warning("MWL skipped: service point {$study->main_module_service_point_id} has no hardware_ae_title.");
            return;
        }

        $modality = $study->protocol?->modality;
        if (blank($modality)) {
            Log::warning("MWL skipped: protocol {$study->protocol_code} has no modality set.");
            return;
        }

        // Generate and persist a StudyInstanceUID once so acquired images can link back
        // (and so Orthanc's DeleteWorklistsOnStableStudy can auto-clean the entry).
        if (blank($study->study_instance_uid)) {
            $study->study_instance_uid = DicomUid::generate();
            $study->save();
        }

        // Replace any stale worklist left over from a previous dispatch.
        if (!blank($study->orthanc_worklist_id)) {
            try {
                $orthanc->deleteWorklist($study->orthanc_worklist_id);
            } catch (Throwable $e) {
                Log::warning("MWL: could not delete stale worklist {$study->orthanc_worklist_id}: {$e->getMessage()}");
            }
        }

        $description = (string) ($study->protocol->protocol_name ?? $study->protocol_code);

        $tags = [
            'AccessionNumber'               => (string) $study->accession_number,
            'PatientID'                     => (string) $study->global_client_id,
            'PatientName'                   => $this->dicomName($study->patient),
            'StudyInstanceUID'              => (string) $study->study_instance_uid,
            'RequestedProcedureDescription' => $description,
            'RequestedProcedureID'          => 'RP-' . $study->accession_number,
            'ScheduledProcedureStepSequence' => [[
                'Modality'                           => (string) $modality,
                'ScheduledStationAETitle'            => mb_substr((string) $aeTitle, 0, 16),
                'ScheduledProcedureStepStartDate'    => now()->format('Ymd'),
                'ScheduledProcedureStepStartTime'    => now()->format('His'),
                'ScheduledProcedureStepDescription'  => $description,
                'ScheduledProcedureStepID'           => 'SPS-' . $study->accession_number,
            ]],
        ];

        if (($birth = $this->dicomDate($study->patient->birth_date ?? null)) !== '') {
            $tags['PatientBirthDate'] = $birth;
        }
        if (($sex = $this->dicomSex($study->patient->sex ?? null)) !== '') {
            $tags['PatientSex'] = $sex;
        }

        $worklistId = $orthanc->createWorklist($tags);

        $study->orthanc_worklist_id = $worklistId;
        $study->save();

        Log::info("MWL created for accession {$study->accession_number}: worklist {$worklistId}");
    }

    private function dicomName(?object $patient): string
    {
        $family = trim((string) ($patient->family_name ?? ''));
        $given  = trim((string) ($patient->given_name ?? ''));

        if ($family !== '' || $given !== '') {
            return $this->pnComponent($family) . '^' . $this->pnComponent($given);
        }

        return $this->pnComponent((string) ($patient->full_name ?? 'ANONYMOUS'));
    }

    private function pnComponent(string $s): string
    {
        return trim(str_replace(['^', '='], ' ', $s));
    }

    private function dicomDate(mixed $date): string
    {
        if ($date instanceof DateTimeInterface) {
            return $date->format('Ymd');
        }
        if (is_string($date) && $date !== '') {
            $digits = preg_replace('/\D/', '', $date);
            return strlen($digits) >= 8 ? substr($digits, 0, 8) : '';
        }
        return '';
    }

    private function dicomSex(mixed $sex): string
    {
        $c = strtoupper(substr((string) $sex, 0, 1));
        if ($c === 'M' || $c === 'F') {
            return $c;
        }
        return $c === '' ? '' : 'O';
    }
}
