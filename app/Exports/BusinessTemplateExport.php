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
        ];
    }

    public function array(): array
    {
        $timezone = SharedTime::defaultTimezoneId();

        return [
            [
                'Sample Business 1', 'business1@example.com', '1234567890', '123 Main Street, City', $timezone,
            ],
            [
                'Sample Business 2', 'business2@example.com', '0987654321', '456 Oak Avenue, Town', $timezone,
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
                $sheet->getComment('E1')->getText()->createTextRun(
                    'IANA timezone from Settings → Manage Timezones, e.g. Africa/Kampala. If left blank, the default timezone is used.'
                );
            },
        ];
    }
}
