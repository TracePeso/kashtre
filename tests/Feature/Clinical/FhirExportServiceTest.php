<?php

namespace Tests\Feature\Clinical;

use App\Models\CdeObservation;
use App\Models\Client;
use App\Models\ClinicalCondition;
use App\Models\ClinicalMedicationOrder;
use App\Services\Clinical\FhirExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FhirExportServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = [null, 'clinical'];

    public function test_it_exports_a_bundle_with_all_five_resource_types(): void
    {
        Client::create([
            'business_id' => 1,
            'branch_id' => 1,
            'client_id' => 'CLIENT-FHIR-1',
            'visit_id' => 'VISIT-FHIR-1',
            'name' => 'Test Patient',
            'first_name' => 'Test',
            'surname' => 'Patient',
            'sex' => 'Female',
            'date_of_birth' => '1990-05-01',
            'phone_number' => '0700000000',
            'email' => 'fhir-test-'.uniqid().'@example.test',
        ]);

        CdeObservation::create([
            'business_id' => 1,
            'client_id' => 'CLIENT-FHIR-1',
            'visit_id' => 'VISIT-FHIR-1',
            'cde_code' => 'BODY_WEIGHT',
            'captured_value_numeric' => 62,
            'base_value_numeric' => 62,
            'capture_method' => 'MANUAL',
            'validation_status' => 'VALIDATED',
            'captured_at' => now(),
        ]);

        ClinicalCondition::create([
            'business_id' => 1,
            'client_id' => 'CLIENT-FHIR-1',
            'icd11_code' => '5A11',
            'description' => 'Type 2 diabetes mellitus',
            'recorded_by_user_id' => 1,
        ]);

        ClinicalMedicationOrder::create([
            'business_id' => 1,
            'client_id' => 'CLIENT-FHIR-1',
            'ordering_user_id' => 1,
            'drug_code' => 'METFORMIN_500',
            'drug_display_name' => 'Metformin 500mg',
            'dose_amount' => 500,
            'route_code' => 'PO',
            'frequency_code' => 'BID',
            'start_at' => now(),
            'status' => ClinicalMedicationOrder::STATUS_ACTIVE,
        ]);

        $bundle = app(FhirExportService::class)->exportPatientBundle(1, 'CLIENT-FHIR-1');

        $this->assertSame('Bundle', $bundle['resourceType']);
        $this->assertSame('collection', $bundle['type']);

        $resourceTypes = collect($bundle['entry'])->pluck('resource.resourceType');

        $this->assertContains('Patient', $resourceTypes);
        $this->assertContains('Encounter', $resourceTypes);
        $this->assertContains('Observation', $resourceTypes);
        $this->assertContains('Condition', $resourceTypes);
        $this->assertContains('MedicationRequest', $resourceTypes);

        $patient = collect($bundle['entry'])->firstWhere('resource.resourceType', 'Patient')['resource'];
        $this->assertSame('CLIENT-FHIR-1', $patient['id']);
        $this->assertSame('female', $patient['gender']);
        $this->assertSame('1990-05-01', $patient['birthDate']);

        $observation = collect($bundle['entry'])->firstWhere('resource.resourceType', 'Observation')['resource'];
        $this->assertSame(62.0, $observation['valueQuantity']['value']);

        $condition = collect($bundle['entry'])->firstWhere('resource.resourceType', 'Condition')['resource'];
        $this->assertSame('5A11', $condition['code']['coding'][0]['code']);

        $medicationRequest = collect($bundle['entry'])->firstWhere('resource.resourceType', 'MedicationRequest')['resource'];
        $this->assertSame('active', $medicationRequest['status']);
        $this->assertSame('Metformin 500mg', $medicationRequest['medicationCodeableConcept']['text']);
    }
}
