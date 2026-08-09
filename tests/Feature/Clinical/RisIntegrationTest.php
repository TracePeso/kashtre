<?php

namespace Tests\Feature\Clinical;

use App\Contracts\ModuleDispatcher;
use App\Models\CdeObservation;
use App\Models\ClinicalWorkOrder;
use App\Models\ImagingOrder;
use App\Models\ImagingProtocol;
use App\Models\ImagingReport;
use App\Models\ImagingStudy;
use App\Models\User;
use App\Services\Clinical\Facts\DiagnosticOrderPlacedFact;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RisIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = [null, 'clinical'];

    private function protocol(): ImagingProtocol
    {
        return ImagingProtocol::create([
            'business_id' => 1,
            'code' => 'CT_HEAD_PLAIN',
            'name' => 'CT Head Plain',
            'modality_type' => 'CT',
            'is_active' => true,
            'requires_consent' => false,
            'requires_preparation' => false,
            'requires_recovery' => false,
        ]);
    }

    private function user(): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'ris-test-'.uniqid().'@example.test',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'email_verified_at' => now(),
            'business_id' => 1,
        ]);
    }

    public function test_dispatching_the_fact_creates_a_real_imaging_order_and_study(): void
    {
        $this->protocol();
        $clinician = $this->user();

        $response = app(ModuleDispatcher::class)->dispatch(new DiagnosticOrderPlacedFact(
            businessId: 1,
            branchId: null,
            globalClientId: 'CLIENT-RIS-1',
            visitId: 'VISIT-RIS-1',
            protocolCode: 'CT_HEAD_PLAIN',
            orderingClinicianId: $clinician->id,
            clinicalIndication: 'Trauma workup',
        ));

        $this->assertSame('ORDER_RECEIVED', $response['status']);

        $order = ImagingOrder::find($response['imaging_order_id']);
        $this->assertNotNull($order);
        $this->assertSame('CLIENT-RIS-1', $order->client_id);
        $this->assertSame(ImagingOrder::STATUS_ACCEPTED, $order->status);

        $study = ImagingStudy::find($response['imaging_study_id']);
        $this->assertNotNull($study);
        $this->assertSame('CT_HEAD_PLAIN', $study->protocol_code);
    }

    public function test_a_verified_report_lands_as_a_cde_observation_and_completes_the_work_order(): void
    {
        $this->protocol();
        $clinician = $this->user();
        $radiologist = $this->user();
        $verifier = $this->user();

        $response = app(ModuleDispatcher::class)->dispatch(new DiagnosticOrderPlacedFact(
            businessId: 1,
            branchId: null,
            globalClientId: 'CLIENT-RIS-2',
            visitId: 'VISIT-RIS-2',
            protocolCode: 'CT_HEAD_PLAIN',
            orderingClinicianId: $clinician->id,
        ));

        // Normally created by PlaceDiagnosticOrder — done manually here to
        // isolate the inbound (report-validated) half of the loop.
        ClinicalWorkOrder::create([
            'business_id' => 1,
            'client_id' => 'CLIENT-RIS-2',
            'visit_id' => 'VISIT-RIS-2',
            'order_type' => 'RAD_CT_HEAD_PLAIN',
            'ordering_user_id' => $clinician->id,
            'status' => ClinicalWorkOrder::STATUS_IN_PROGRESS,
            'external_module' => 'imaging',
            'external_reference' => (string) $response['imaging_order_id'],
        ]);

        $report = ImagingReport::create([
            'imaging_study_id' => $response['imaging_study_id'],
            'author_user_id' => $radiologist->id,
            'structured_data_payload' => ['impression' => 'No acute intracranial abnormality.'],
        ]);

        $report->markReported($radiologist->id);
        $report->markVerified($verifier->id);

        $observation = CdeObservation::where('client_id', 'CLIENT-RIS-2')
            ->where('cde_code', 'RAD_IMPRESSION_CT_HEAD_PLAIN')
            ->first();

        $this->assertNotNull($observation);
        $this->assertStringContainsString('No acute intracranial abnormality', $observation->captured_value_text);
        $this->assertSame(CdeObservation::METHOD_IMPORTED_DATA, $observation->capture_method);

        $workOrder = ClinicalWorkOrder::where('external_reference', (string) $response['imaging_order_id'])->first();
        $this->assertSame(ClinicalWorkOrder::STATUS_COMPLETED, $workOrder->status);
        $this->assertNotNull($workOrder->completed_at);
    }

    public function test_a_critical_finding_lands_as_a_cde_observation(): void
    {
        $this->protocol();
        $clinician = $this->user();
        $radiologist = $this->user();

        $response = app(ModuleDispatcher::class)->dispatch(new DiagnosticOrderPlacedFact(
            businessId: 1,
            branchId: null,
            globalClientId: 'CLIENT-RIS-3',
            visitId: null,
            protocolCode: 'CT_HEAD_PLAIN',
            orderingClinicianId: $clinician->id,
        ));

        $report = ImagingReport::create([
            'imaging_study_id' => $response['imaging_study_id'],
            'author_user_id' => $radiologist->id,
            'structured_data_payload' => ['impression' => 'Large pneumothorax.'],
            'is_critical_finding' => true,
            'critical_finding_code' => 'PNEUMOTHORAX',
        ]);

        $report->markReported($radiologist->id);

        $observation = CdeObservation::where('client_id', 'CLIENT-RIS-3')
            ->where('cde_code', 'RAD_CRITICAL_FINDING_CT_HEAD_PLAIN')
            ->first();

        $this->assertNotNull($observation);
        $this->assertSame('PNEUMOTHORAX', $observation->captured_value_text);
    }
}
