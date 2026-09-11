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
use App\Models\Item;
use App\Models\Branch;
use App\Models\Group;
use App\Models\Department;
use App\Models\ItemUnit;
use App\Models\ServicePoint;
use Illuminate\Support\Facades\Log;

class PackageBulkTemplateExport implements FromArray, WithHeadings, WithStyles, WithEvents
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
        // Return empty array - we'll create a custom horizontal layout
        return [];
    }
    
    public function array(): array
    {
        // Create horizontal layout with row headers (like in screenshot)
        // Get branches for dynamic branch price rows
        $branches = Branch::where('business_id', $this->businessId)->orderBy('name')->get();
        
        // Get available items for the constituents list
        $availableItems = Item::where('business_id', $this->businessId)
            ->whereIn('type', ['service', 'good'])
            ->orderBy('name')
            ->pluck('name')
            ->toArray();
        
        $templateData = [
            // Row 1: Column headers (Item1 through Item10 - expandable to 25)
            ['Package/Bulk Item Name', 'Item1', 'Item2', 'Item3', 'Item4', 'Item5', 'Item6', 'Item7', 'Item8', 'Item9', 'Item10', 'Item11', 'Item12', 'Item13', 'Item14', 'Item15'],
            
            // Row 2: Name
            ['Name', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            
            // Row 3: Code
            ['Code (Auto-generated if empty)', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            
            // Row 4: Type
            ['Type (package/bulk)', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            
            // Row 5: Description
            ['Description', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            
            // Row 6: Sale Price
            ['Sale Price', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],

            // Row 7: Purchase Price
            ['Purchase Price', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            
            // Row 8: Validity Period
            ['Validity Period (Days) - Required for packages', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            
            // Row 9: Other Names
            ['Other Names', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
        ];
        
        // Add branch price rows dynamically (expand to 15 items)
        foreach ($branches as $branch) {
            $templateData[] = [$branch->name . ' - Price', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
        }
        
        // Add empty row for spacing
        $templateData[] = ['', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
        $templateData[] = ['', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
        
        // Add constituents header row
        $templateData[] = ['Constituents(full items list where type is good/service)', 'Qty', 'Qty', 'Qty', 'Qty', 'Qty', 'Qty', 'Qty', 'Qty', 'Qty', 'Qty', 'Qty', 'Qty', 'Qty', 'Qty', 'Qty'];
        
        // Add alphabetical list of available items with their codes
        $availableItems = Item::where('business_id', $this->businessId)
            ->whereIn('type', ['service', 'good'])
            ->orderBy('name')
            ->get(['name', 'code']);
            
        foreach ($availableItems as $item) {
            $itemDisplay = $item->name . ' (' . $item->code . ')';
            $templateData[] = [$itemDisplay, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '']; // Empty quantity cells for user to fill
        }
        
        return $templateData;
    }
    
    /**
     * Convert column index to Excel column letter (A, B, C, ..., AA, AB, etc.)
     */
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



    public function styles(Worksheet $sheet)
    {
        // Style the first row (headers)
        $styles = [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB'] // Gray for headers
                ]
            ],
        ];
        
        // Calculate constituents header row number
        $branches = Branch::where('business_id', $this->businessId)->count();
        $constituentsHeaderRow = 7 + $branches + 2; // 7 base fields + branches + 2 empty rows
        
        // Style constituents header row
        $styles[$constituentsHeaderRow] = [
            'font' => ['bold' => true, 'size' => 12],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E5E7EB'] // Gray for constituents header
            ]
        ];
        
        // Style the available items list (blue text)
        $availableItemsCount = Item::where('business_id', $this->businessId)
            ->whereIn('type', ['service', 'good'])
            ->count();
            
        for ($i = $constituentsHeaderRow + 1; $i <= $constituentsHeaderRow + $availableItemsCount; $i++) {
            $styles[$i] = [
                'font' => ['size' => 10, 'color' => ['rgb' => '60A5FA']], // Blue text for item names and codes
            ];
        }
        
        return $styles;
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
        
        // Get the data for dropdowns
        $items = Item::where('business_id', $this->businessId)
            ->whereIn('type', ['service', 'good'])
            ->pluck('name')
            ->toArray();
        $branches = Branch::where('business_id', $this->businessId)->orderBy('name')->get();
        
        // Set a default range for data validation (rows 2-1000)
        $startRow = 2;
        $endRow = 1000;
        
        // Add data validation for Type row (row 4, columns B through Z for Item1-Item25)
        // This automatically supports up to 25 items (B through Z)
        $typeColumns = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        foreach ($typeColumns as $column) {
            $validation = $worksheet->getCell($column . '4')->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(false);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setFormula1('"package,bulk"');
            $validation->setErrorTitle('Invalid Type');
            $validation->setError('Please select either "package" or "bulk"');
            $validation->setPromptTitle('Select Type');
            $validation->setPrompt('Choose the item type');
        }
        
        // Calculate constituents header row
        // 8 base rows + branches + 2 empty rows = the row where constituents header is
        $constituentsHeaderRow = 8 + $branches->count() + 2;
        
        Log::info("=== PACKAGE/BULK TEMPLATE DEBUG ===");
        Log::info("Available items count: " . count($items));
        Log::info("Branches count: " . $branches->count());
        Log::info("Constituents header row: " . $constituentsHeaderRow);
        Log::info("Template supports Item1-Item25 (columns B through Z)");
        Log::info("Type dropdowns added to columns: " . implode(', ', array_slice($typeColumns, 0, 10)) . "..."); // Example slice
        Log::info("Constituents section now shows alphabetical list with item codes instead of dropdowns");
        
        // Add clear instructions for the template in columns beyond the data range (starting at column R)
        $worksheet->setCellValue('R1', 'PACKAGE/BULK TEMPLATE INSTRUCTIONS');
        $worksheet->setCellValue('R2', '1. Fill in package/bulk details in rows 2-7');
        $worksheet->setCellValue('R3', '2. Use Type dropdown to select package/bulk');
        $worksheet->setCellValue('R4', '3. Constituents list shows all available items');
        $worksheet->setCellValue('R5', '4. Items include name and code for easy reference');
        $worksheet->setCellValue('R6', '5. Enter quantities in Qty columns (B, C, D...)');
        $worksheet->setCellValue('R7', '6. Delete unused constituent rows if needed');
        $worksheet->setCellValue('R8', '7. Template supports up to 25 items (B-Z)');
        
        // Style the instructions
        $worksheet->getStyle('R1')->getFont()->setBold(true);
        $worksheet->getStyle('R1')->getFont()->setSize(11);
        $worksheet->getStyle('R1')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0066CC'));
        for ($i = 2; $i <= 8; $i++) {
            $worksheet->getStyle('R' . $i)->getFont()->setSize(9);
        }
        
        // Instructions removed as per user request
    }

    /**
     * Convert column index to Excel column letter (A, B, C, ..., AA, AB, etc.)
     */
    private function getExcelColumnLetter($columnIndex)
    {
        $columnLetter = '';
        while ($columnIndex >= 0) {
            $columnLetter = chr(65 + ($columnIndex % 26)) . $columnLetter;
            $columnIndex = intval($columnIndex / 26) - 1;
        }
        return $columnLetter;
    }
    
    /**
     * Add helpful instructions to the worksheet
     */
    private function addInstructions($worksheet)
    {
        // Add simple instructions at the top
        $worksheet->setCellValue('E1', 'INSTRUCTIONS:');
        $worksheet->setCellValue('E2', '1. Type package/bulk names in columns B, C, D');
        $worksheet->setCellValue('E3', '2. Use Type dropdown to select package/bulk');
        $worksheet->setCellValue('E4', '3. Scroll to Constituents section below');
        $worksheet->setCellValue('E5', '4. Select constituent items from Column A dropdown');
        $worksheet->setCellValue('E6', '5. Enter quantities in Qty columns (B, C, D)');
        
        $worksheet->getStyle('E1')->getFont()->setBold(true);
    }
} 