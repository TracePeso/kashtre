<?php

namespace App\Exports\Concerns;

use App\Models\KashtreCashTraySetting;
use App\Support\BusinessBranding;
use Carbon\CarbonInterface;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

trait WithInstitutionExportHeader
{
    protected ?BusinessBranding $exportBranding = null;

    protected ?CarbonInterface $exportGeneratedAt = null;

    public function institutionHeaderStartRow(): int
    {
        return 7;
    }

    public function startCell(): string
    {
        return 'A'.$this->institutionHeaderStartRow();
    }

    public function setExportBranding(?BusinessBranding $branding, ?CarbonInterface $generatedAt = null): static
    {
        $this->exportBranding = $branding;
        $this->exportGeneratedAt = $generatedAt;

        return $this;
    }

    /**
     * @return array<class-string, callable>
     */
    public function institutionHeaderEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event): void {
                if ($this->exportBranding === null) {
                    return;
                }

                $sheet = $event->sheet->getDelegate();
                $generatedAt = ($this->exportGeneratedAt ?? now())->format('d M Y H:i');
                $kashtreSettings = KashtreCashTraySetting::resolved();
                $website = $kashtreSettings->primaryWebsiteLink();

                $rows = array_values(array_filter([
                    $this->exportBranding->name(),
                    $this->exportBranding->address(),
                    collect([
                        $this->exportBranding->phone() ? 'Tel: '.$this->exportBranding->phone() : null,
                        $this->exportBranding->email() ? 'Email: '.$this->exportBranding->email() : null,
                    ])->filter()->implode('  |  '),
                    'Generated: '.$generatedAt,
                    $kashtreSettings->documentPoweredByLine(),
                    $website ? $website['label'].' — '.$website['url'] : null,
                ]));

                foreach ($rows as $index => $line) {
                    $row = $index + 1;
                    $sheet->setCellValue('A'.$row, $line);
                    $sheet->mergeCells('A'.$row.':G'.$row);
                    $sheet->getStyle('A'.$row)->getFont()->setBold($index === 0);
                    $sheet->getStyle('A'.$row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                }

                $sheet->getStyle('A1:A'.($this->institutionHeaderStartRow() - 1))
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('F8FAFC');
            },
        ];
    }
}
