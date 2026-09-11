<?php

namespace Database\Seeders;

use App\Models\ImagingProtocol;
use Illuminate\Database\Seeder;

/**
 * Standalone seeder (not wired into DatabaseSeeder) — run explicitly with:
 * php artisan db:seed --class=ImagingProtocolSeeder
 *
 * Seeds a couple of system-wide protocols (business_id null) so later chunks
 * have real Protocol Engine data to build order intake and worklists against.
 */
class ImagingProtocolSeeder extends Seeder
{
    public function run(): void
    {
        ImagingProtocol::updateOrCreate(
            ['code' => 'CHEST-XRAY'],
            [
                'business_id' => null,
                'name' => 'Chest X-Ray',
                'modality_type' => 'XRAY',
                'is_active' => true,
                'requires_consent' => false,
                'is_contrast_enhanced' => false,
                'requires_recovery' => false,
                'preparation_requirements' => [],
                'readiness_checks' => [],
                'reporting_template' => [
                    'sections' => ['Lungs', 'Pleura', 'Heart', 'Impression'],
                ],
                'consumables_recipe' => [],
            ]
        );

        ImagingProtocol::updateOrCreate(
            ['code' => 'CT-ABDOMEN-CONTRAST'],
            [
                'business_id' => null,
                'name' => 'CT Abdomen (Contrast)',
                'modality_type' => 'CT',
                'is_active' => true,
                'requires_consent' => true,
                'is_contrast_enhanced' => true,
                'requires_recovery' => false,
                'preparation_requirements' => ['fasting', 'contrast_screening'],
                'readiness_checks' => ['creatinine_available', 'contrast_screening_passed'],
                'reporting_template' => [
                    'sections' => ['Liver', 'Gallbladder', 'Kidneys', 'Pancreas', 'Bowel', 'Impression'],
                ],
                'consumables_recipe' => [
                    ['sku' => 'CONTRAST-NONIONIC-100ML', 'qty' => 1],
                    ['sku' => 'INJECTOR-SYRINGE-KIT', 'qty' => 1],
                    ['sku' => 'INDWELLING-CATHETER-LINE', 'qty' => 1],
                ],
                // default_contrast_item_id is intentionally left unset here:
                // Items are strictly per-business (real business_id FK), so a
                // system-wide protocol has no single "right" Item to point
                // at — each business sets its own via Settings > Manage
                // Imaging Protocols once it has a matching Item in its catalog.
                'default_contrast_volume_ml' => 100,
                'default_kvp_metrics' => '120 kVp',
            ]
        );

        // Interventional/sedation example for Pillar 16 (Procedure -> Recovery
        // -> Discharge) — HSG is the spec's own recurring example of a
        // high-risk study needing both consent and post-procedure recovery.
        ImagingProtocol::updateOrCreate(
            ['code' => 'HSG'],
            [
                'business_id' => null,
                'name' => 'Hysterosalpingography (HSG)',
                'modality_type' => 'FLUORO',
                'is_active' => true,
                'requires_consent' => true,
                'is_contrast_enhanced' => true,
                'requires_recovery' => true,
                'preparation_requirements' => ['pregnancy_test_negative'],
                'readiness_checks' => ['pelvic_infection_screening_clear'],
                'reporting_template' => [
                    'sections' => ['Uterine Cavity', 'Fallopian Tubes', 'Impression'],
                ],
                'consumables_recipe' => [
                    ['sku' => 'CONTRAST-NONIONIC-100ML', 'qty' => 1],
                    ['sku' => 'HSG-CATHETER-KIT', 'qty' => 1],
                ],
                'default_contrast_volume_ml' => 20,
            ]
        );
    }
}
