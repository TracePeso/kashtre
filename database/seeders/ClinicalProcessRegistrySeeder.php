<?php

namespace Database\Seeders;

use App\Models\ClinicalProcessStep;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Ports the SRD §4.3 "Pre-Configured Transition Workflows Catalog" onto
 * the 'clinical' connection. Run standalone:
 *   php artisan db:seed --class="Database\Seeders\ClinicalProcessRegistrySeeder"
 */
class ClinicalProcessRegistrySeeder extends Seeder
{
    public function run(): void
    {
        $db = DB::connection('clinical');

        $processes = [
            'ADMISSION' => [
                'name' => 'Admission Workflow',
                'steps' => [
                    ['ADMISSION_ASSESSMENT', 'Admission Assessment & Triage Verification', true, null, null],
                    ['INITIAL_NURSING_ASSESSMENT', 'Initial Nursing Assessment', true, 'WARD_NURSE', null],
                    ['CLINICAL_RISK_ASSESSMENT', 'Clinical Risk Assessment (Fall, Pressure Sore, VTE)', true, 'WARD_NURSE', null],
                    ['CARE_PLAN_INITIALIZATION', 'Initial Care Plan Initialization', true, null, null],
                    ['BED_ALLOCATION', 'Bed Allocation & Room-to-Store Mapping', true, null, ClinicalProcessStep::EFFECT_ALLOCATE_BED],
                    ['WARD_NURSE_ACCEPTANCE', 'Ward Nurse Acceptance Handshake', true, 'WARD_NURSE', null],
                ],
            ],
            'TRANSFER' => [
                'name' => 'Inter-Space Transfer Workflow',
                'steps' => [
                    ['TRANSFER_REQUEST', 'Transfer Request & Destination Selection', true, null, null],
                    ['MEDICAL_REVIEW', 'Medical Review & Transit Stability Sign-off', true, 'CONSULTANT', null],
                    ['MEDICATION_RECONCILIATION', 'Active Medication Reconciliation', true, null, null],
                    ['TRANSFER_SUMMARY', 'Transfer Clinical Summary Generation (CDE Snapshot)', true, null, null],
                    ['RECEIVING_WARD_ACCEPTANCE', 'Receiving Ward Acceptance Handshake', true, 'WARD_NURSE', null],
                    // Not wired to EFFECT_ALLOCATE_BED/RELEASE_BED yet — the
                    // exit criteria for this chunk only requires bed
                    // side-effects on ADMISSION/DISCHARGE. A real bed swap
                    // (release old + allocate new) is a reasonable Chunk 9
                    // extension once this is exercised in practice.
                    ['BED_CUSTODY_TRANSFER', 'Physical Bed Custody Transfer', true, null, null],
                ],
            ],
            'DISCHARGE' => [
                'name' => 'Discharge Workflow',
                'steps' => [
                    ['CONSULTANT_DISCHARGE_SIGNOFF', 'Consultant Discharge Sign-Off', true, 'CONSULTANT', null],
                    ['NURSING_DISCHARGE_REVIEW', 'Nursing Discharge Review & Line/Drain Removal Check', true, 'WARD_NURSE', null],
                    ['DISCHARGE_MEDICATION_RECONCILIATION', 'Discharge Medication Reconciliation', true, null, null],
                    ['OUTSTANDING_RESULTS_REVIEW', 'Outstanding Laboratory & Radiology Results Review', true, null, null],
                    ['DISCHARGE_SUMMARY_ICD11', 'Clinical Discharge Summary & ICD-11 Finalization', true, null, null],
                    ['DISCHARGE_MEDICATION_ISSUE', 'Discharge Medication Issue / External Voucher', true, null, null],
                    ['FINANCIAL_CLEARANCE', 'Main Module Financial & Clearance Verification', false, null, null],
                    ['ENCOUNTER_CLOSURE', 'Clinical Encounter Closure', true, null, ClinicalProcessStep::EFFECT_RELEASE_BED],
                ],
            ],
            'REFERRAL' => [
                'name' => 'External Referral Workflow',
                'steps' => [
                    ['REFERRAL_JUSTIFICATION', 'Referral Justification & Target Specialty Selection', true, null, null],
                    ['TRANSFER_SUMMARY_GENERATION', 'Inter-Hospital Transfer Summary Generation', true, null, null],
                    ['REFERRAL_SIGNOFF_EXPORT', 'Clinical Sign-off & IPS / C-CDA Export Package', true, 'CONSULTANT', null],
                ],
            ],
            'DEATH_CERT' => [
                'name' => 'Death Certification Workflow',
                'steps' => [
                    ['VERIFICATION_OF_DEATH', 'Verification of Death (Clinical Assessment)', true, 'CONSULTANT', null],
                    ['END_OF_LIFE_KILL_SWITCH', 'System End-of-Life Kill-Switch Execution (Halts MAR/Orders)', true, null, null],
                    ['DEATH_CAUSE_DOCUMENTATION', 'Death Cause Documentation (ICD-11 Mortality Coding)', true, 'CONSULTANT', null],
                    ['MORTUARY_CUSTODY_HANDSHAKE', 'Mortuary Custody Handshake', true, null, ClinicalProcessStep::EFFECT_RELEASE_BED],
                    ['DEATH_CERTIFICATE_SIGNOFF', 'Formal Death Certificate Sign-off & Chart Lock', true, 'CONSULTANT', null],
                ],
            ],
        ];

        foreach ($processes as $code => $definition) {
            $db->table('clinical_processes')->updateOrInsert(
                ['business_id' => null, 'process_code' => $code],
                ['process_name' => $definition['name'], 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );

            $processId = $db->table('clinical_processes')
                ->where('business_id', null)
                ->where('process_code', $code)
                ->value('id');

            foreach ($definition['steps'] as $index => [$stepCode, $stepName, $isMandatory, $requiredRole, $sideEffect]) {
                $db->table('clinical_process_steps')->updateOrInsert(
                    ['process_id' => $processId, 'step_order' => $index + 1],
                    [
                        'step_code' => $stepCode,
                        'step_name' => $stepName,
                        'is_mandatory' => $isMandatory,
                        'required_role' => $requiredRole,
                        'side_effect' => $sideEffect,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}
