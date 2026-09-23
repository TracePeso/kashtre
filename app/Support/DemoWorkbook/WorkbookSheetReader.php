<?php

namespace App\Support\DemoWorkbook;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

final class WorkbookSheetReader
{
    public function __construct(private readonly string $path)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(string $sheetName): array
    {
        /** @var Xlsx $reader */
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $reader->setLoadSheetsOnly([$sheetName]);

        $spreadsheet = $reader->load($this->path);
        $sheet = $spreadsheet->getSheetByName($sheetName);

        if (! $sheet) {
            $spreadsheet->disconnectWorksheets();

            return [];
        }

        $highestRow = (int) $sheet->getHighestDataRow();
        $highestColIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $matrix = $sheet->rangeToArray(
            'A5:'.Coordinate::stringFromColumnIndex($highestColIndex).$highestRow,
            null,
            true,
            false,
            false,
        );

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $sheet);

        if ($matrix === []) {
            return [];
        }

        $headerRow = array_shift($matrix);
        $headers = [];
        foreach ($headerRow as $index => $label) {
            $label = trim((string) $label);
            $headers[$index] = $label === '' ? null : Str::slug($label, '_');
        }

        $rows = [];
        foreach ($matrix as $raw) {
            $row = [];
            $empty = true;
            foreach ($headers as $index => $key) {
                if ($key === null) {
                    continue;
                }
                $value = $raw[$index] ?? null;
                if ($value !== null && $value !== '') {
                    $empty = false;
                }
                $row[$key] = $value;
            }
            if (! $empty) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
