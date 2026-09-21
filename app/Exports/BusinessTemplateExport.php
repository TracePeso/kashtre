<?php

namespace App\Exports;

use App\Support\SharedTime;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BusinessTemplateExport implements FromArray, WithEvents, WithHeadings, WithStyles
{
    public function headings(): array
    {
        return [
            'Name',
            'Email',
            'Phone',
            'Address',
            'Timezone',
            'Require 2FA',
            'Send Password Reset',
        ];
    }

    public function array(): array
    {
        $timezone = SharedTime::defaultTimezoneId();

        return [
            [
                'Sample Business 1', 'business1@example.com', '1234567890', '123 Main Street, City', $timezone, 'yes', 'yes',
            ],
            [
                'Sample Business 2', 'business2@example.com', '0987654321', '456 Oak Avenue, Town', $timezone, 'yes', 'yes',
            ],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getColumnDimension('E')->setWidth(22);
                $sheet->getColumnDimension('F')->setWidth(14);
                $sheet->getColumnDimension('G')->setWidth(22);
                $sheet->getComment('E1')->getText()->createTextRun(
                    'IANA timezone from Settings → Manage Timezones, e.g. Africa/Kampala. If left blank, the default timezone is used.'
                );
                $sheet->getComment('F1')->getText()->createTextRun(
                    'yes or no. If blank, 2FA is required (the default).'
                );
                $sheet->getComment('G1')->getText()->createTextRun(
                    'yes or no. If blank, imported users receive a password reset email. Set to no so they get the default password "password".'
                );
            },
        ];
    }
}
