<?php

// app/Services/OrthancWorklistWriter.php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Produces DICOM Modality Worklist (.wl) files that Orthanc's ModalityWorklists plugin
 * serves over C-FIND.
 *
 * A .wl file is a real DICOM dataset — not JSON. We build a DCMTK "dump" text form and
 * convert it with `dump2dcm`, which is the method documented for Orthanc worklists.
 * Files are named "{accession}.wl" so they can be found and removed once consumed.
 */
class OrthancWorklistWriter
{
    /**
     * Write a worklist entry and return the absolute path to the .wl file.
     *
     * @param array{
     *   accession_number:string, study_instance_uid:string, patient_name:string,
     *   patient_id:string, patient_birth_date:string, patient_sex:string,
     *   modality:string, ae_title:string, procedure_description:string,
     *   scheduled_date:string, scheduled_time:string
     * } $e
     */
    public function write(array $e): string
    {
        $dir = $this->dir();
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new RuntimeException("Orthanc worklist directory is missing or not writable: {$dir}");
        }

        $dump    = $this->buildDump($e);
        $wlPath  = $dir . '/' . $this->safeFilename($e['accession_number']) . '.wl';
        $tmpDump = tempnam(sys_get_temp_dir(), 'mwl_');
        $tmpWl   = $wlPath . '.tmp';

        file_put_contents($tmpDump, $dump);

        try {
            $process = new Process([$this->dump2dcm(), $tmpDump, $tmpWl]);
            $process->run();

            if (!$process->isSuccessful()) {
                @unlink($tmpWl);
                throw new ProcessFailedException($process);
            }

            // Atomic publish: rename into place so Orthanc never reads a half-written file.
            rename($tmpWl, $wlPath);
        } finally {
            @unlink($tmpDump);
        }

        return $wlPath;
    }

    /**
     * Remove a study's worklist entry (call once images have been acquired).
     */
    public function remove(string $accessionNumber): void
    {
        $path = $this->dir() . '/' . $this->safeFilename($accessionNumber) . '.wl';
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Generate a globally-unique DICOM StudyInstanceUID.
     *
     * Uses the ISO/IEC UUID-derived root "2.25" (DICOM PS3.5 Annex B.2): "2.25." followed
     * by the decimal form of a random 128-bit UUID. Requires no OID registration. If you
     * own an org root, set DICOM_UID_ROOT and this appends a unique numeric suffix instead.
     */
    public function generateStudyInstanceUid(): string
    {
        $dec = $this->hexToDec(bin2hex(random_bytes(16)));
        $root = rtrim((string) config('services.orthanc.uid_root', '2.25'), '.');

        if ($root === '2.25' || $root === '') {
            return '2.25.' . $dec;
        }

        // Custom org root: keep total length within the 64-char DICOM UI limit.
        $suffix = substr($dec, 0, max(1, 63 - strlen($root)));
        return $root . '.' . ltrim($suffix, '0') ?: ($root . '.0');
    }

    // --- internals -------------------------------------------------------------------

    private function buildDump(array $e): string
    {
        $v = fn (string $val) => $this->dcmValue($val);

        $lines = [
            '(0008,0005) CS [ISO_IR 100]',                                  // SpecificCharacterSet
            '(0008,0050) SH [' . $v($e['accession_number']) . ']',          // AccessionNumber
            '(0008,0090) PN []',                                            // ReferringPhysicianName (present, empty)
            '(0010,0010) PN [' . $v($e['patient_name']) . ']',              // PatientName
            '(0010,0020) LO [' . $v($e['patient_id']) . ']',                // PatientID
        ];

        if (!blank($e['patient_birth_date'])) {
            $lines[] = '(0010,0030) DA [' . $v($e['patient_birth_date']) . ']'; // PatientBirthDate
        }
        if (!blank($e['patient_sex'])) {
            $lines[] = '(0010,0040) CS [' . $v($e['patient_sex']) . ']';        // PatientSex
        }

        $lines[] = '(0020,000d) UI [' . $v($e['study_instance_uid']) . ']';    // StudyInstanceUID
        $lines[] = '(0032,1060) LO [' . $v($e['procedure_description']) . ']'; // RequestedProcedureDescription
        $lines[] = '(0040,1001) SH [' . $v('RP-' . $e['accession_number']) . ']'; // RequestedProcedureID

        // Scheduled Procedure Step Sequence (0040,0100) — required, one item.
        $lines[] = '(0040,0100) SQ';
        $lines[] = '(fffe,e000) na';
        $lines[] = '  (0008,0060) CS [' . $v($e['modality']) . ']';            // Modality
        $lines[] = '  (0040,0001) AE [' . $v($this->truncate($e['ae_title'], 16)) . ']'; // ScheduledStationAETitle
        $lines[] = '  (0040,0002) DA [' . $v($e['scheduled_date']) . ']';      // ScheduledProcedureStepStartDate
        $lines[] = '  (0040,0003) TM [' . $v($e['scheduled_time']) . ']';      // ScheduledProcedureStepStartTime
        $lines[] = '  (0040,0007) LO [' . $v($e['procedure_description']) . ']'; // ScheduledProcedureStepDescription
        $lines[] = '  (0040,0009) SH [' . $v('SPS-' . $e['accession_number']) . ']'; // ScheduledProcedureStepID
        $lines[] = '(fffe,e00d) na';
        $lines[] = '(fffe,e0dd) na';

        return implode("\n", $lines) . "\n";
    }

    /**
     * dump2dcm delimits values with [ ] — strip anything that would break that grammar.
     */
    private function dcmValue(string $value): string
    {
        $value = str_replace(['[', ']', '\\'], '', $value);
        $value = preg_replace('/[\r\n\t]+/', ' ', $value);
        return trim($value);
    }

    private function truncate(string $value, int $max): string
    {
        return mb_substr($value, 0, $max);
    }

    private function safeFilename(string $accession): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $accession);
        return $safe === '' ? 'unknown' : $safe;
    }

    private function hexToDec(string $hex): string
    {
        if (function_exists('gmp_init')) {
            return gmp_strval(gmp_init($hex, 16), 10);
        }
        if (function_exists('bcadd')) {
            $dec = '0';
            foreach (str_split(strtolower($hex)) as $ch) {
                $dec = bcadd(bcmul($dec, '16'), (string) hexdec($ch));
            }
            return $dec;
        }
        throw new RuntimeException('Enable ext-gmp or ext-bcmath to generate DICOM UIDs.');
    }

    private function dir(): string
    {
        return rtrim((string) config('services.orthanc.worklist_dir'), '/');
    }

    private function dump2dcm(): string
    {
        return (string) config('services.orthanc.dump2dcm', 'dump2dcm');
    }
}
