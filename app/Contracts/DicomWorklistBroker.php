<?php

namespace App\Contracts;

/**
 * Pillar 1.1: Pre-Acquisition DICOM Modality Worklist (MWL) Broker.
 * Bound to a stub (LoggingDicomWorklistBroker) in AppServiceProvider until
 * real scanner hardware exists — swap the binding for an HTTP-backed
 * implementation (same idiom as CallingServiceClient) at that point; no
 * caller of this contract needs to change.
 */
interface DicomWorklistBroker
{
    /**
     * @param array{accession_number: string, patient_id: string, patient_name: string, modality: string, ae_title: ?string, study_instance_uid: string} $record
     * @return string|null The broker's own opaque worklist id, if it creates
     *                      one server-side (used to delete/replace the entry
     *                      later) — null for brokers that don't have one.
     */
    public function pushRecord(array $record): ?string;
}
