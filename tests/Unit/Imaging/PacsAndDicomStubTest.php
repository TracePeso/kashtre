<?php

namespace Tests\Unit\Imaging;

use App\Contracts\DicomWorklistBroker;
use App\Contracts\PacsClient;
use App\Models\ImagingStudy;
use App\Services\Imaging\LoggingDicomWorklistBroker;
use App\Services\Imaging\OrthancClient;
use App\Services\Imaging\OrthancDicomWorklistBroker;
use App\Services\Imaging\OrthancPacsClient;
use App\Services\Imaging\StubPacsClient;
use App\Support\DicomUid;
use Tests\TestCase;

class PacsAndDicomStubTest extends TestCase
{
    public function test_container_resolves_the_orthanc_implementations_by_default(): void
    {
        $this->assertInstanceOf(OrthancDicomWorklistBroker::class, app(DicomWorklistBroker::class));
        $this->assertInstanceOf(OrthancPacsClient::class, app(PacsClient::class));
    }

    public function test_dicom_broker_stub_does_not_throw_when_pushing_a_record(): void
    {
        $broker = new LoggingDicomWorklistBroker();

        $result = $broker->pushRecord([
            'accession_number' => 'ACC-2026-000001',
            'patient_id' => 'CL-001',
            'patient_name' => 'Test Patient',
            'modality' => 'XRAY',
            'ae_title' => null,
            'study_instance_uid' => '2.25.123',
        ]);

        $this->assertNull($result);
    }

    public function test_pacs_stub_reports_not_available_when_unconfigured(): void
    {
        config(['services.pacs.viewer_url' => null]);

        $client = new StubPacsClient();
        $study = new ImagingStudy(['accession_number' => 'ACC-2026-000001']);

        $this->assertNull($client->viewerUrl($study));
        $this->assertFalse($client->archive($study));
        $this->assertFalse($client->retrieve($study));
    }

    public function test_resolve_hardware_ae_title_is_null_without_a_room(): void
    {
        $study = new ImagingStudy(['main_room_id' => null]);

        $this->assertNull($study->resolveHardwareAeTitle());
    }

    public function test_dicom_uid_generates_a_2_25_rooted_uid_by_default(): void
    {
        $uid = DicomUid::generate();

        $this->assertStringStartsWith('2.25.', $uid);
        $this->assertLessThanOrEqual(64, strlen($uid));
    }

    public function test_dicom_uid_uses_a_custom_root_when_configured(): void
    {
        config(['services.orthanc.uid_root' => '1.2.826.0.1.3680043.8.498']);

        $uid = DicomUid::generate();

        $this->assertStringStartsWith('1.2.826.0.1.3680043.8.498.', $uid);
        $this->assertLessThanOrEqual(64, strlen($uid));
    }

    public function test_orthanc_worklist_broker_skips_without_calling_orthanc_when_ae_title_is_blank(): void
    {
        $orthanc = $this->createMock(OrthancClient::class);
        $orthanc->expects($this->never())->method('createWorklist');

        $broker = new OrthancDicomWorklistBroker($orthanc);

        $result = $broker->pushRecord([
            'accession_number' => 'ACC-2026-000002',
            'patient_id' => 'CL-002',
            'patient_name' => 'Test Patient',
            'modality' => 'CT',
            'ae_title' => null,
            'study_instance_uid' => '2.25.456',
        ]);

        $this->assertNull($result);
    }

    public function test_orthanc_worklist_broker_skips_without_calling_orthanc_when_modality_has_no_dicom_mapping(): void
    {
        $orthanc = $this->createMock(OrthancClient::class);
        $orthanc->expects($this->never())->method('createWorklist');

        $broker = new OrthancDicomWorklistBroker($orthanc);

        $result = $broker->pushRecord([
            'accession_number' => 'ACC-2026-000003',
            'patient_id' => 'CL-003',
            'patient_name' => 'Test Patient',
            'modality' => 'SOME_UNMAPPED_MODALITY',
            'ae_title' => 'CT_ROOM_1',
            'study_instance_uid' => '2.25.789',
        ]);

        $this->assertNull($result);
    }

    public function test_orthanc_pacs_client_reports_not_available_without_calling_orthanc_when_study_id_is_blank(): void
    {
        $orthanc = $this->createMock(OrthancClient::class);
        $orthanc->expects($this->never())->method('studyExists');

        $client = new OrthancPacsClient($orthanc);
        $study = new ImagingStudy(['accession_number' => 'ACC-2026-000004', 'orthanc_study_id' => null]);

        $this->assertNull($client->viewerUrl($study));
        $this->assertFalse($client->archive($study));
        $this->assertFalse($client->retrieve($study));
    }
}
