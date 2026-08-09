<?php

namespace Database\Seeders;

use App\Models\CdeRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Ports the pre-seeded master dictionaries from the Clinical Module
 * Engineering doc (§2) onto the 'clinical' connection, with the doc's
 * tenant_id renamed to this app's business_id (null = system-wide default,
 * per CdeRegistry::resolve()). Run standalone:
 *   php artisan db:seed --class="Database\Seeders\ClinicalMasterDictionariesSeeder"
 */
class ClinicalMasterDictionariesSeeder extends Seeder
{
    public function run(): void
    {
        $db = DB::connection('clinical');

        // 1. UNITS OF MEASURE
        $units = [
            ['°C', 'Cel', 'Temperature', 'Celsius Vitals'],
            ['°F', '[degF]', 'Temperature', 'Fahrenheit Vitals'],
            ['mmHg', 'mm[Hg]', 'Blood Pressure / Tension', 'Systolic/Diastolic BP'],
            ['cmH20', 'cm[H20]', 'Respiratory / Fluid Pressure', 'Ventilator PEEP'],
            ['bpm', '/min', 'Heart & Respiratory Rates', 'Pulse / Heart Rate'],
            ['breaths/min', '{breaths}/min', 'Heart & Respiratory Rates', 'Respiratory Rate'],
            ['kg', 'kg', 'Mass / Weight', 'Patient Mass'],
            ['g', 'g', 'Mass / Weight', 'Infant Mass'],
            ['mg', 'mg', 'Mass / Weight', 'Drug Mass'],
            ['g/dL', 'g/dL', 'Volumetric Concentration', 'Hemoglobin Concentration'],
            ['mg/dL', 'mg/dL', 'Volumetric Concentration', 'Glucose / Bilirubin'],
            ['mmol/L', 'mmol/L', 'Molar Concentration', 'Electrolytes / Glucose Base'],
            ['umol/L', 'umol/L', 'Molar Concentration', 'Serum Creatinine'],
            ['mL/hr', 'mL/h', 'Infusion & Flow Rates', 'IV Infusion Velocity'],
            ['mg/kg', 'mg/kg', 'Weight-Normalized Dosing', 'Pediatric Dosage Metric'],
            ['mL/min/1.73m2', 'mL/min/{1.73_m2}', 'Renal Function / Clearance', 'eGFR Metric'],
            ['%', '%', 'Percentages & Proportions', 'SpO2 Oxygen Saturation'],
        ];

        foreach ($units as [$label, $ucum, $category, $description]) {
            $db->table('clinical_uom_master')->updateOrInsert(
                ['business_id' => null, 'unit_label' => $label],
                ['ucum_code' => $ucum, 'category' => $category, 'description' => $description, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        $unitId = fn (string $label) => $db->table('clinical_uom_master')->whereNull('business_id')->where('unit_label', $label)->value('id');

        $celsiusId = $unitId('°C');
        $fahrenheitId = $unitId('°F');
        $mmolId = $unitId('mmol/L');
        $mgdlId = $unitId('mg/dL');
        $umolId = $unitId('umol/L');

        // 2. UNIT CONVERSIONS
        $conversions = [];

        if ($mmolId && $mgdlId) {
            $conversions[] = ['GLUCOSE_RANDOM', $mmolId, $mgdlId, 'MULTIPLIER', 18.0182, null, 1];
            $conversions[] = ['GLUCOSE_RANDOM', $mgdlId, $mmolId, 'DIVISOR', 18.0182, null, 1];
        }

        if ($umolId && $mgdlId) {
            $conversions[] = ['CREATININE_SERUM', $umolId, $mgdlId, 'DIVISOR', 88.4, null, 2];
            $conversions[] = ['CREATININE_SERUM', $mgdlId, $umolId, 'MULTIPLIER', 88.4, null, 2];
        }

        if ($celsiusId && $fahrenheitId) {
            $conversions[] = ['TEMP_AXILLARY', $celsiusId, $fahrenheitId, 'POLYNOMIAL', 1, 'C_TO_F', 1];
            $conversions[] = ['TEMP_AXILLARY', $fahrenheitId, $celsiusId, 'POLYNOMIAL', 1, 'F_TO_C', 1];
        }

        foreach ($conversions as [$cdeCode, $fromId, $toId, $type, $factor, $formula, $precision]) {
            $db->table('clinical_uom_conversions_master')->updateOrInsert(
                ['business_id' => null, 'cde_code' => $cdeCode, 'from_unit_id' => $fromId, 'to_unit_id' => $toId],
                ['conversion_type' => $type, 'factor' => $factor, 'formula_expression' => $formula, 'decimal_precision' => $precision, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // 3. REASON CODES
        $reasons = [
            ['SKIPPED_OBS', 'REASON_OBS_REFUSED', 'Patient Refused Observation', false],
            ['SKIPPED_OBS', 'REASON_OBS_OFF_WARD', 'Patient Off Ward / In Transit', false],
            ['SKIPPED_OBS', 'REASON_OBS_IN_THEATRE', 'Patient In Operating Theatre', false],
            ['BREAK_GLASS', 'OVERRIDE_EMERGENCY_RESUS', 'Emergency Resuscitation / Crash Call', true],
            ['BREAK_GLASS', 'OVERRIDE_ON_CALL_COVER', 'On-Call Night / Weekend Coverage', false],
            ['MAR_WASTAGE', 'MAR_WASTAGE_DROPPED', 'Dose Dropped / Contaminated', false],
            ['MAR_WASTAGE', 'MAR_WASTAGE_LINE_BLOWN', 'IV Line Blown / Infiltrated', false],
            ['CDSS_OVERRIDE', 'OVERRIDE_DDI_CLINICAL_JUDGEMENT', 'Clinical Judgement — Benefit Outweighs Interaction Risk', true],
            ['CDSS_OVERRIDE', 'OVERRIDE_ALLERGY_DESENSITIZED', 'Patient Previously Desensitized / Tolerates Under Monitoring', true],
            ['CDSS_OVERRIDE', 'OVERRIDE_DOSE_SPECIALIST_DIRECTED', 'Dose Directed by Specialist Consult', true],
        ];

        foreach ($reasons as [$category, $code, $label, $requiresFreeText]) {
            $db->table('clinical_reason_codes_master')->updateOrInsert(
                ['business_id' => null, 'category_code' => $category, 'reason_code' => $code],
                ['display_label' => $label, 'requires_free_text' => $requiresFreeText, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // 4. ESCALATION TIERS
        $escalations = [
            ['INFO', '#2563EB', 'chime.mp3', 'TOAST', ['WARD_NURSE']],
            ['WARNING', '#D97706', 'beep_dual.mp3', 'HEADER_ALERT', ['WARD_NURSE', 'DUTY_RESIDENT']],
            ['URGENT_REVIEW', '#EA580C', 'beep_continuous.mp3', 'MODAL_POPUP', ['WARD_NURSE', 'DUTY_RESIDENT', 'MATRON']],
            ['CRITICAL_PANIC', '#DC2626', 'siren_full.mp3', 'SCREEN_LOCK', ['WARD_NURSE', 'DUTY_RESIDENT', 'ICU_REGISTRAR', 'MATRON']],
        ];

        foreach ($escalations as [$tier, $color, $sound, $action, $roles]) {
            $db->table('clinical_escalation_rules')->updateOrInsert(
                ['business_id' => null, 'severity_tier' => $tier],
                ['color_hex' => $color, 'auditory_signal' => $sound, 'screen_action' => $action, 'target_roles' => json_encode($roles), 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // 5. ROUTES & FREQUENCIES
        $routesFreqs = [
            ['PO', 'ROUTE', 'Oral', 0],
            ['IV', 'ROUTE', 'Intravenous', 0],
            ['IM', 'ROUTE', 'Intramuscular', 0],
            ['SC', 'ROUTE', 'Subcutaneous', 0],
            ['STAT', 'FREQUENCY', 'Immediately / Emergency', 0],
            ['QD', 'FREQUENCY', 'Once Daily', 1440],
            ['BID', 'FREQUENCY', 'Twice Daily', 720],
            ['TID', 'FREQUENCY', 'Three Times Daily', 480],
            ['QID', 'FREQUENCY', 'Four Times Daily', 360],
            ['Q4H', 'FREQUENCY', 'Every 4 Hours', 240],
            ['PRN', 'FREQUENCY', 'As Needed', 0],
        ];

        foreach ($routesFreqs as [$code, $type, $label, $minutes]) {
            $db->table('pharmacy_route_frequency_master')->updateOrInsert(
                ['business_id' => null, 'type' => $type, 'code' => $code],
                ['display_label' => $label, 'minute_interval' => $minutes, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // 6. CDE REGISTRY (system-wide defaults; base units resolved above)
        $bpmId = $unitId('bpm');
        $percentId = $unitId('%');
        $kgId = $unitId('kg');
        $egfrId = $unitId('mL/min/1.73m2');

        $cdes = [
            ['TEMP_AXILLARY', 'Axillary Temperature', CdeRegistry::TYPE_NUMERIC, $celsiusId, 36.1, 37.2, 39.0, 35.0, 30.0, 43.0],
            ['GLUCOSE_RANDOM', 'Random Blood Glucose', CdeRegistry::TYPE_NUMERIC, $mmolId, 4.0, 7.8, 15.0, 3.0, 0.5, 50.0],
            ['PULSE_RATE', 'Pulse Rate', CdeRegistry::TYPE_NUMERIC, $bpmId, 60, 100, 150, 40, 20, 250],
            ['SPO2', 'Oxygen Saturation', CdeRegistry::TYPE_NUMERIC, $percentId, 95, 100, null, 90, 50, 100],
            ['BODY_WEIGHT', 'Body Weight', CdeRegistry::TYPE_NUMERIC, $kgId, null, null, null, null, 0.3, 400],
            // Chunk 6: Deterministic CDSS Shield inputs. eGFR is captured
            // directly as a lab-style value here (imported from LIMS in
            // Chunk 7, or entered manually) rather than computed by the
            // CDSS itself — the CKD-EPI formula engine is a Chunk 9
            // extension, not reinvented here.
            ['CREATININE_SERUM', 'Serum Creatinine', CdeRegistry::TYPE_NUMERIC, $umolId, 60, 110, 400, null, 10, 2000],
            ['EGFR_CALCULATED', 'Estimated GFR', CdeRegistry::TYPE_NUMERIC, $egfrId, 90, 120, null, 15, 1, 200],
        ];

        foreach ($cdes as [$code, $name, $type, $baseUomId, $normMin, $normMax, $critHigh, $critLow, $physMin, $physMax]) {
            $db->table('cde_registry')->updateOrInsert(
                ['business_id' => null, 'cde_code' => $code],
                [
                    'cde_name' => $name,
                    'data_type' => $type,
                    'base_uom_id' => $baseUomId,
                    'normal_range_min' => $normMin,
                    'normal_range_max' => $normMax,
                    'critical_high' => $critHigh,
                    'critical_low' => $critLow,
                    'physiological_min' => $physMin,
                    'physiological_max' => $physMax,
                    'is_graphable' => true,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        // TEXT CDEs — no base unit/heuristic bounds.
        $textCdes = [
            // Free-text allergy capture (e.g. "Penicillin", "Sulfa
            // Drugs"); the CDSS shield matches this against a drug's
            // generic_name/other_names.
            ['ALLERGY_MEDICATION', 'Medication Allergy'],
            // Chunk 8: the Bedside Scratchpad's manual-entry fallback —
            // works with zero dependency on the AI Gateway.
            ['BEDSIDE_NOTE', 'Bedside Rough Note'],
        ];

        foreach ($textCdes as [$code, $name]) {
            $db->table('cde_registry')->updateOrInsert(
                ['business_id' => null, 'cde_code' => $code],
                [
                    'cde_name' => $name,
                    'data_type' => CdeRegistry::TYPE_TEXT,
                    'base_uom_id' => null,
                    'is_graphable' => false,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
