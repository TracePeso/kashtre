<?php

namespace App\Services\Imaging;

use App\Contracts\DicomWorklistBroker;
use App\Models\ImagingModality;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pillar 1.1 real implementation, swapped in for LoggingDicomWorklistBroker
 * once Orthanc is configured (AppServiceProvider::register()). Creates a
 * DICOM Modality Worklist entry via Orthanc's Worklists plugin REST API
 * (POST /worklists/create) — the modality then pulls it via C-FIND. This
 * class only tells Orthanc which entry to serve; it never opens a DICOM
 * socket to the scanner itself.
 */
class OrthancDicomWorklistBroker implements DicomWorklistBroker
{
    public function __construct(private readonly OrthancClient $orthanc) {}

    public function pushRecord(array $record): ?string
    {
        $aeTitle = $record['ae_title'];

        if (blank($aeTitle)) {
            Log::warning("MWL skipped: no hardware_ae_title configured for accession {$record['accession_number']}.");

            return null;
        }

        // This app's modality_type vocabulary isn't the DICOM Modality
        // (0008,0060) code set — the admin-managed ImagingModality dictionary
        // maps each one to its real DICOM code (replaces the previous
        // hardcoded DICOM_MODALITY_CODES const).
        $modality = ImagingModality::where('code', $record['modality'])->value('dicom_code');

        if ($modality === null) {
            Log::warning("MWL skipped: no DICOM modality code mapped for '{$record['modality']}' (accession {$record['accession_number']}).");

            return null;
        }

        $tags = [
            'AccessionNumber' => (string) $record['accession_number'],
            'PatientID' => (string) $record['patient_id'],
            'PatientName' => $this->dicomName($record['patient_name']),
            'StudyInstanceUID' => (string) $record['study_instance_uid'],
            'RequestedProcedureID' => 'RP-'.$record['accession_number'],
            'ScheduledProcedureStepSequence' => [[
                'Modality' => $modality,
                'ScheduledStationAETitle' => mb_substr((string) $aeTitle, 0, 16),
                'ScheduledProcedureStepStartDate' => now()->format('Ymd'),
                'ScheduledProcedureStepStartTime' => now()->format('His'),
                'ScheduledProcedureStepID' => 'SPS-'.$record['accession_number'],
            ]],
        ];

        try {
            return $this->orthanc->createWorklist($tags);
        } catch (Throwable $e) {
            Log::error("MWL: failed to create Orthanc worklist for accession {$record['accession_number']}: {$e->getMessage()}");

            return null;
        }
    }

    private function dicomName(string $fullName): string
    {
        $clean = trim(str_replace(['^', '='], ' ', $fullName));

        return $clean !== '' ? $clean : 'ANONYMOUS';
    }
}
