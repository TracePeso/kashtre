<?php

namespace Database\Seeders;

use App\Models\ImagingCriticalFindingType;
use Illuminate\Database\Seeder;

/**
 * Standalone seeder (not wired into DatabaseSeeder) — run explicitly with:
 * php artisan db:seed --class=ImagingCriticalFindingTypeSeeder
 */
class ImagingCriticalFindingTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'INTRACRANIAL_BLEED', 'label' => 'Intracranial Bleed'],
            ['code' => 'PNEUMOTHORAX', 'label' => 'Pneumothorax'],
            ['code' => 'PULMONARY_EMBOLUS', 'label' => 'Pulmonary Embolus'],
            ['code' => 'RUPTURED_ECTOPIC_PREGNANCY', 'label' => 'Ruptured Ectopic Pregnancy'],
        ];

        foreach ($types as $type) {
            ImagingCriticalFindingType::updateOrCreate(
                ['code' => $type['code']],
                [
                    'business_id' => null,
                    'label' => $type['label'],
                    'is_active' => true,
                ]
            );
        }
    }
}
