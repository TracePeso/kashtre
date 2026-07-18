<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventoryConsumptionExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * @param  Collection<int, object>  $rows
     * @param  array{
     *     store_name: string,
     *     item_name: ?string,
     *     from: string,
     *     until: string,
     *     period_label: string,
     *     total_quantity_suom: float,
     *     distinct_items: int,
     *     item_day_rows: int
     * }  $meta
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly array $meta,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function title(): string
    {
        return 'Consumption';
    }

    public function headings(): array
    {
        return [
            'Date',
            'Item',
            'Item code',
            'Consumed (sale units)',
            'Store',
            'Period from',
            'Period until',
        ];
    }

    /**
     * @param  object  $row
     */
    public function map($row): array
    {
        return [
            \Carbon\Carbon::parse($row->consumption_date)->toDateString(),
            $row->item_name ?? '—',
            $row->item_code ?? '',
            round((float) $row->total_quantity_suom, 4),
            $this->meta['store_name'],
            $this->meta['from'],
            $this->meta['until'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ],
        ];
    }
}
