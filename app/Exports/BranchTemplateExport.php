<?php

namespace App\Exports;

use App\Support\SharedTime;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BranchTemplateExport implements FromArray, WithEvents, WithHeadings, WithStyles
{
    public function headings(): array
    {
        return [
            'Branch Name',
            'Email',
            'Phone',
            'Address',
            'Timezone',
        ];
    }

    public function array(): array
    {
        $override = SharedTime::defaultTimezoneId();

        return [
            [
                'Head Office', 'headoffice@example.com', '1234567890', '123 Main Street, City', '',
            ],
            [
                'Branch Office', 'branch@example.com', '0987654321', '456 Oak Avenue, Town', $override,
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
                    'Leave blank to inherit the business timezone. Set an IANA timezone from Settings → Manage Timezones only to override this branch.'
                );
            },
        ];
    }
}
