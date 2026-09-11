<?php

namespace Database\Seeders;

use App\Models\ImagingModality;
use Illuminate\Database\Seeder;

/**
 * Standalone seeder (not wired into DatabaseSeeder) — run explicitly with:
 * php artisan db:seed --class=ImagingModalitySeeder
 *
 * Seeds the exact 6 modalities that used to be hardcoded in
 * ListImagingProtocols::MODALITY_OPTIONS / ImagingStudy::IONIZING_MODALITIES /
 * OrthancDicomWorklistBroker::DICOM_MODALITY_CODES, with the same DICOM
 * mapping and ionizing flags those consts encoded, so existing protocols and
 * studies behave identically after switching to this DB-driven list.
 */
class ImagingModalitySeeder extends Seeder
{
    public function run(): void
    {
        $modalities = [
            ['code' => 'XRAY', 'name' => 'X-Ray', 'dicom_code' => 'DX', 'is_ionizing' => true],
            ['code' => 'CT', 'name' => 'CT Scan', 'dicom_code' => 'CT', 'is_ionizing' => true],
            ['code' => 'MRI', 'name' => 'MRI', 'dicom_code' => 'MR', 'is_ionizing' => false],
            ['code' => 'US', 'name' => 'Ultrasound', 'dicom_code' => 'US', 'is_ionizing' => false],
            ['code' => 'MG', 'name' => 'Mammography', 'dicom_code' => 'MG', 'is_ionizing' => true],
            ['code' => 'FLUORO', 'name' => 'Fluoroscopy', 'dicom_code' => 'XA', 'is_ionizing' => true],
        ];

        foreach ($modalities as $modality) {
            ImagingModality::updateOrCreate(
                ['code' => $modality['code']],
                [
                    'business_id' => null,
                    'name' => $modality['name'],
                    'dicom_code' => $modality['dicom_code'],
                    'is_ionizing' => $modality['is_ionizing'],
                    'is_active' => true,
                ]
            );
        }
    }
}
