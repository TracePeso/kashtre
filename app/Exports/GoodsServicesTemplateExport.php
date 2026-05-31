<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use App\Models\Business;
use App\Models\Group;
use App\Models\SubGroup;
use App\Models\Department;
use App\Models\ItemUnit;
use App\Models\ServicePoint;
use App\Models\ContractorProfile;
use App\Models\Branch;
use App\Services\ContractorValidationService;
use Illuminate\Support\Facades\Log;

class GoodsServicesTemplateExport implements FromArray, WithHeadings, WithStyles, WithEvents
{
    protected $businessId;
    protected $business;

    public function __construct($businessId)
    {
        $this->businessId = $businessId;
        $this->business = Business::find($businessId);
    }

    public function headings(): array
    {
        $baseHeaders = [
            'Name',
            'Generic Name',
            'Category',
            'Code (Auto-generated if empty)',
            'Type (service/good)',
            'Description',
            'Group Name',
            'Subgroup Name',
            'Department Name',
            'Unit of Measure',
            'Default Price',
            'VAT Rate (%)',
            'Hospital Share (%)',
            'Contractor Username',
            'Other Names',
        ];

        // Get branches for this business to create dynamic columns
        $branches = Branch::where('business_id', $this->businessId)->orderBy('name')->get();
        
        // Add pricing columns for each branch
        foreach ($branches as $branch) {
            $baseHeaders[] = $branch->name . ' - Price';
        }
        
        // Add service point columns for each branch
        foreach ($branches as $branch) {
            $baseHeaders[] = $branch->name . ' - Service Point';
        }

        return $baseHeaders;
    }

    public function array(): array
    {
        // Return empty array - clean template with no sample data
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5']
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF']]
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $this->addDataValidation($event);
            },
        ];
    }

    private function addDataValidation(AfterSheet $event)
    {
        $worksheet = $event->sheet->getDelegate();
        
        Log::info('=== addDataValidation DEBUG ===');
        Log::info('Business ID: ' . $this->businessId);
        
        // Get the data for dropdowns
        $groups = Group::where('business_id', $this->businessId)->pluck('name')->toArray();
        $subGroups = SubGroup::where('business_id', $this->businessId)->pluck('name')->toArray();
        $departments = Department::where('business_id', $this->businessId)->pluck('name')->toArray();
        $units = ItemUnit::where('business_id', $this->businessId)->pluck('name')->toArray();
        $servicePoints = ServicePoint::where('business_id', $this->businessId)->pluck('name')->toArray();
        $contractors = ContractorValidationService::getAvailableContractors($this->businessId);
        $branches = Branch::where('business_id', $this->businessId)->orderBy('name')->get();
        
        Log::info('Groups count: ' . count($groups));
        Log::info('SubGroups count: ' . count($subGroups));
        Log::info('SubGroups: ' . json_encode($subGroups));
        Log::info('Contractors retrieved: ' . json_encode($contractors));
        Log::info('Contractors count: ' . count($contractors));
        
        // Set a default range for data validation (rows 2-1000)
        $startRow = 2;
        $endRow = 1000;
        
        // Column mappings for the new structure
        $columns = [
            'E' => 'Type',        // Type (service/good)
            'G' => 'Group',       // Group Name
            'H' => 'Subgroup',    // Subgroup Name
            'I' => 'Department',  // Department Name
            'J' => 'Unit',        // Unit of Measure
            'K' => 'DefaultPrice', // Default Price
            'L' => 'VATRate',     // VAT Rate (%)
            'M' => 'HospitalShare', // Hospital Share (%)
            'N' => 'Contractor',  // Contractor Username
        ];
        
        // Add data validation for Type column (E)
        $this->addValidationToColumn($worksheet, 'E', $startRow, $endRow, '"service,good"', 'Type', false);
        
        // Add data validation for Group Name column (G)
        if (!empty($groups)) {
            $groupList = '"' . implode(',', $groups) . '"';
            $this->addValidationToColumn($worksheet, 'G', $startRow, $endRow, $groupList, 'Group');
        }
        
        // Add data validation for Subgroup Name column (H)
        if (!empty($subGroups)) {
            $subgroupList = '"' . implode(',', $subGroups) . '"';
            $this->addValidationToColumn($worksheet, 'H', $startRow, $endRow, $subgroupList, 'Subgroup');
        }
        
        // Add data validation for Department Name column (I)
        if (!empty($departments)) {
            $departmentList = '"' . implode(',', $departments) . '"';
            $this->addValidationToColumn($worksheet, 'I', $startRow, $endRow, $departmentList, 'Department');
        }
        
        // Add data validation for Unit of Measure column (J)
        if (!empty($units)) {
            $unitList = '"' . implode(',', $units) . '"';
            $this->addValidationToColumn($worksheet, 'J', $startRow, $endRow, $unitList, 'Unit');
        }
        
        // Add conditional validation for hospital share and contractor relationship
        $this->addConditionalValidation($worksheet, $startRow, $endRow);
        
        // Add data validation for Contractor column - Required when hospital share < 100%
        if (!empty($contractors)) {
            // Calculate the correct column for Contractor Username
            $headers = $this->headings();
            $contractorColumnIndex = array_search('Contractor Username', $headers);
            $contractorColumn = $this->getColumnLetter($contractorColumnIndex + 1); // +1 because array is 0-indexed
            
            Log::info('Contractor Username column found at index: ' . $contractorColumnIndex . ', column letter: ' . $contractorColumn);
            
            // Use simple format like packages/bulk template
            $contractorList = '"' . implode(',', $contractors) . '"';
            $this->addValidationToColumn($worksheet, $contractorColumn, $startRow, $endRow, $contractorList, 'Contractor');
        }
        
        // Add data validation for service point columns (dynamic) - filter by branch
        $headers = $this->headings();
        
        foreach ($headers as $index => $header) {
            if (strpos($header, ' - Service Point') !== false) {
                // Extract branch name from header
                $branchName = str_replace(' - Service Point', '', $header);
                
                // Get service points for this specific branch
                $branch = $branches->where('name', $branchName)->first();
                if ($branch) {
                    $branchServicePoints = ServicePoint::where('business_id', $this->businessId)
                        ->where('branch_id', $branch->id)
                        ->pluck('name')
                        ->toArray();
                    
                    if (!empty($branchServicePoints)) {
                        $columnLetter = $this->getColumnLetter($index + 1);
                        $servicePointList = '"' . implode(',', $branchServicePoints) . '"';
                        $this->addValidationToColumn($worksheet, $columnLetter, $startRow, $endRow, $servicePointList, 'Service Point');
                    }
                }
            }
        }
    }
    
    private function addValidationToColumn($worksheet, $column, $startRow, $endRow, $formula, $type, $allowBlank = true)
    {
        Log::info("=== addValidationToColumn DEBUG ===");
        Log::info("Column: $column, StartRow: $startRow, EndRow: $endRow, Type: $type");
        Log::info("Formula: " . $formula);
        Log::info("Formula length: " . strlen($formula));
        
        for ($row = $startRow; $row <= $endRow; $row++) {
            $validation = $worksheet->getCell($column . $row)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank($allowBlank);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setFormula1($formula);
            $validation->setErrorTitle('Invalid ' . $type);
            $validation->setError('Please select a valid ' . strtolower($type));
            $validation->setPromptTitle('Select ' . $type);
            $validation->setPrompt('Choose a ' . strtolower($type) . ' from the dropdown');
            
            if ($row == $startRow) {
                Log::info("Applied validation to cell $column$row with formula: " . $formula);
            }
        }
        Log::info("=== END addValidationToColumn DEBUG ===");
    }
    
    private function getColumnLetter($columnIndex)
    {
        $columnLetter = '';
        while ($columnIndex > 0) {
            $columnIndex--;
            $columnLetter = chr(65 + ($columnIndex % 26)) . $columnLetter;
            $columnIndex = intval($columnIndex / 26);
        }
        return $columnLetter;
    }
    
    /**
     * Add conditional validation for hospital share and contractor relationship
     * This adds custom validation rules to ensure contractor is selected when hospital share < 100%
     */
    private function addConditionalValidation($worksheet, $startRow, $endRow)
    {
        // Add custom validation for hospital share column (K)
        for ($row = $startRow; $row <= $endRow; $row++) {
            $validation = $worksheet->getCell('K' . $row)->getDataValidation();
            $validation->setType(DataValidation::TYPE_CUSTOM);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(false);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setFormula1('=AND(K' . $row . '>=0,K' . $row . '<=100)');
            $validation->setErrorTitle('Invalid Hospital Share');
            $validation->setError('Hospital share must be between 0 and 100');
            $validation->setPromptTitle('Hospital Share');
            $validation->setPrompt('Enter a value between 0 and 100');
        }
        
        // Add conditional validation for contractor column (L) - Required when hospital share < 100%
        for ($row = $startRow; $row <= $endRow; $row++) {
            $validation = $worksheet->getCell('L' . $row)->getDataValidation();
            $validation->setType(DataValidation::TYPE_CUSTOM);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true); // Allow blank initially
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setFormula1('=OR(K' . $row . '=100,LEN(L' . $row . ')>0)');
            $validation->setErrorTitle('Contractor Required');
            $validation->setError('Contractor is required when hospital share is less than 100%. Please select a contractor or set hospital share to 100%.');
            $validation->setPromptTitle('Contractor Selection');
            $validation->setPrompt('Select a contractor when hospital share < 100%, or leave empty if share = 100%');
        }
    }
} 