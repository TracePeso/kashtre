<?php

namespace Database\Seeders;

use App\Models\ImagingReadinessCheckType;
use Illuminate\Database\Seeder;

/**
 * Standalone seeder (not wired into DatabaseSeeder) — run explicitly with:
 * php artisan db:seed --class=ImagingReadinessCheckTypeSeeder
 *
 * Seeds the exact codes already referenced by ImagingProtocolSeeder's 3
 * system-wide protocols, so existing preparation_requirements/readiness_checks
 * JSON arrays resolve to real master-list rows instead of orphaned codes.
 */
class ImagingReadinessCheckTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'fasting', 'label' => 'Fasting', 'category' => ImagingReadinessCheckType::CATEGORY_PREPARATION],
            ['code' => 'contrast_screening', 'label' => 'Contrast Screening', 'category' => ImagingReadinessCheckType::CATEGORY_PREPARATION],
            ['code' => 'pregnancy_test_negative', 'label' => 'Pregnancy Test Negative', 'category' => ImagingReadinessCheckType::CATEGORY_PREPARATION],
            ['code' => 'creatinine_available', 'label' => 'Creatinine Available', 'category' => ImagingReadinessCheckType::CATEGORY_READINESS],
            ['code' => 'contrast_screening_passed', 'label' => 'Contrast Screening Passed', 'category' => ImagingReadinessCheckType::CATEGORY_READINESS],
            ['code' => 'pelvic_infection_screening_clear', 'label' => 'Pelvic Infection Screening Clear', 'category' => ImagingReadinessCheckType::CATEGORY_READINESS],
        ];

        foreach ($types as $type) {
            ImagingReadinessCheckType::updateOrCreate(
                ['code' => $type['code']],
                [
                    'business_id' => null,
                    'label' => $type['label'],
                    'category' => $type['category'],
                    'is_active' => true,
                ]
            );
        }
    }
}
