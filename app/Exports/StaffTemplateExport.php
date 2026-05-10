<?php

namespace App\Exports;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Department;
use App\Models\Qualification;
use App\Models\ServicePoint;
use App\Models\Title;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StaffTemplateExport implements FromArray, WithEvents, WithHeadings, WithStyles
{
    protected $businessId;

    protected $branchId;

    protected $business;

    protected $branch;

    public function __construct($businessId, $branchId)
    {
        $this->businessId = $businessId;
        $this->branchId = $branchId;
        $this->business = Business::find($businessId);
        $this->branch = Branch::find($branchId);
    }

    public function headings(): array
    {
        return [
            'Surname',
            'First Name',
            'Middle Name',
            'Email',
            'Phone',
            'National ID (NIN)',
            'Gender (male/female/other)',
            'Birth Date (YYYY-MM-DD)',
            'Marital Status (single/married/divorced/widowed/separated/other)',
            'Qualification Name',
            'Title Name',
            'Department Name',
            'Status (active/inactive/suspended)',
            'Service Point Name',
            'Allowed Branch Name',
            'Is Contractor (Yes/No)',
            'Bank Name',
            'Account Name',
            'Account Number',
        ];
    }

    public function array(): array
    {
        // Return empty array - template with just headers and dropdowns
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Header row
            1 => [
                'font' => ['bold' => true, 'size' => 11],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5'],
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->addDataValidation($event);
            },
        ];
    }

    private function addDataValidation(AfterSheet $event)
    {
        $worksheet = $event->sheet->getDelegate();

        // Get the data for dropdowns
        $qualifications = Qualification::where('business_id', $this->businessId)->pluck('name')->toArray();
        $titles = Title::where('business_id', $this->businessId)->pluck('name')->toArray();
        $departments = Department::where('business_id', $this->businessId)->pluck('name')->toArray();
        $servicePoints = ServicePoint::where('business_id', $this->businessId)->pluck('name')->toArray();
        $branches = Branch::where('business_id', $this->businessId)->pluck('name')->toArray();

        // Set a default range for data validation (rows 2-1000)
        $startRow = 2;
        $endRow = 1000;

        // Column G - Gender (optional)
        $this->addValidationToColumn($worksheet, 'G', $startRow, $endRow, '"male,female,other"', 'Gender', true);

        // Column H - Birth date (free-form YYYY-MM-DD; no list validation)

        // Column I - Marital status (optional)
        $this->addValidationToColumn($worksheet, 'I', $startRow, $endRow, '"single,married,divorced,widowed,separated,other"', 'Marital Status', true);

        // Column J - Qualification dropdown
        if (! empty($qualifications)) {
            $this->addValidationToColumn($worksheet, 'J', $startRow, $endRow, '"'.implode(',', $qualifications).'"', 'Qualification', true);
        }

        // Column K - Title dropdown
        if (! empty($titles)) {
            $this->addValidationToColumn($worksheet, 'K', $startRow, $endRow, '"'.implode(',', $titles).'"', 'Title', true);
        }

        // Column L - Department dropdown
        if (! empty($departments)) {
            $this->addValidationToColumn($worksheet, 'L', $startRow, $endRow, '"'.implode(',', $departments).'"', 'Department', true);
        }

        // Column M - Status dropdown
        $this->addValidationToColumn($worksheet, 'M', $startRow, $endRow, '"active,inactive,suspended"', 'Status', true);

        // Column N - Service Point dropdown
        if (! empty($servicePoints)) {
            $this->addValidationToColumn($worksheet, 'N', $startRow, $endRow, '"'.implode(',', $servicePoints).'"', 'Service Point', true);
        }

        // Column O - Allowed Branch dropdown
        if (! empty($branches)) {
            $this->addValidationToColumn($worksheet, 'O', $startRow, $endRow, '"'.implode(',', $branches).'"', 'Allowed Branch', true);
        }

        // Column P - Is Contractor dropdown
        $this->addValidationToColumn($worksheet, 'P', $startRow, $endRow, '"Yes,No"', 'Is Contractor', true);
    }

    private function addValidationToColumn($worksheet, $column, $startRow, $endRow, $formula, $type, $allowBlank = true)
    {
        for ($row = $startRow; $row <= $endRow; $row++) {
            $validation = $worksheet->getCell($column.$row)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank($allowBlank);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setFormula1($formula);
            $validation->setErrorTitle('Invalid '.$type);
            $validation->setError('Please select a valid '.strtolower($type));
            $validation->setPromptTitle('Select '.$type);
            $validation->setPrompt('Choose a '.strtolower($type).' from the dropdown');
        }
    }
}
