<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Models\Business;
use App\Models\Department;
use App\Models\ServicePoint;
use App\Models\ContractorProfile;
use App\Support\SharedUnits;
use App\Models\Group;
use App\Models\SubGroup;
use App\Models\Branch;

class ItemReferenceExport implements FromArray, WithHeadings, WithStyles, WithMultipleSheets
{
    protected $businessId;
    protected $business;

    public function __construct($businessId)
    {
        $this->businessId = $businessId;
        $this->business = Business::find($businessId);
    }

    public function sheets(): array
    {
        return [
            'Departments' => new DepartmentsSheet($this->businessId),
            'Service Points' => new ServicePointsSheet($this->businessId),
            'Contractors' => new ContractorsSheet($this->businessId),
            'Units of Measure' => new UnitsSheet($this->businessId),
            'Groups' => new GroupsSheet($this->businessId),
            'Subgroups' => new SubgroupsSheet($this->businessId),
            'Branches' => new BranchesSheet($this->businessId),
        ];
    }

    public function headings(): array
    {
        return [];
    }

    public function array(): array
    {
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }
}

class BusinessInfoSheet implements FromArray, WithHeadings, WithStyles
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
        return [
            'Business Information',
            '',
            'Business Name',
            'Business ID',
        ];
    }

    public function array(): array
    {
        return [
            [''],
            [''],
            [$this->business->name ?? 'N/A'],
            [$this->business->id ?? 'N/A'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 14],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5']
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF']]
            ],
            3 => ['font' => ['bold' => true]],
            4 => ['font' => ['bold' => true]],
        ];
    }
}

class DepartmentsSheet implements FromArray, WithHeadings, WithStyles
{
    protected $businessId;

    public function __construct($businessId)
    {
        $this->businessId = $businessId;
    }

    public function headings(): array
    {
        return [
            'Department Name',
        ];
    }

    public function array(): array
    {
        $departments = Department::where('business_id', $this->businessId)->get();
        
        $data = [];
        foreach ($departments as $department) {
            $data[] = [
                $department->name,
            ];
        }
        
        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0']
                ]
            ],
        ];
    }
}

class ServicePointsSheet implements FromArray, WithHeadings, WithStyles
{
    protected $businessId;

    public function __construct($businessId)
    {
        $this->businessId = $businessId;
    }

    public function headings(): array
    {
        return [
            'Service Point Name',
        ];
    }

    public function array(): array
    {
        $servicePoints = ServicePoint::where('business_id', $this->businessId)->get();
        
        $data = [];
        foreach ($servicePoints as $servicePoint) {
            $data[] = [
                $servicePoint->name,
            ];
        }
        
        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0']
                ]
            ],
        ];
    }
}

class ContractorsSheet implements FromArray, WithHeadings, WithStyles
{
    protected $businessId;

    public function __construct($businessId)
    {
        $this->businessId = $businessId;
    }

    public function headings(): array
    {
        return [
            'Contractor Name',
            'Kashtre Account Number',
            'User Email',
            'Bank Name',
            'Account Number',
            'Phone Number',
            'Balance',
            'Qualifications',
        ];
    }

    public function array(): array
    {
        $contractors = ContractorProfile::with('user')
            ->where('business_id', $this->businessId)
            ->get();
        
        $data = [];
        foreach ($contractors as $contractor) {
            $data[] = [
                $contractor->user->name ?? 'N/A',
                $contractor->kashtre_account_number ?? 'N/A',
                $contractor->user->email ?? 'N/A',
                $contractor->bank_name ?? 'N/A',
                $contractor->account_number ?? 'N/A',
                $contractor->phone_number ?? 'N/A',
                $contractor->balance ?? 'N/A',
                $contractor->qualifications ?? 'N/A',
            ];
        }
        
        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0']
                ]
            ],
        ];
    }
}

class UnitsSheet implements FromArray, WithHeadings, WithStyles
{
    protected $businessId;

    public function __construct($businessId)
    {
        $this->businessId = $businessId;
    }

    public function headings(): array
    {
        return [
            'Unit of Measure Name',
        ];
    }

    public function array(): array
    {
        $units = SharedUnits::itemUnitsForBusiness((int) $this->businessId);
        
        $data = [];
        foreach ($units as $unit) {
            $data[] = [
                $unit->name,
            ];
        }
        
        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0']
                ]
            ],
        ];
    }
}

class GroupsSheet implements FromArray, WithHeadings, WithStyles
{
    protected $businessId;

    public function __construct($businessId)
    {
        $this->businessId = $businessId;
    }

    public function headings(): array
    {
        return [
            'Group Name',
        ];
    }

    public function array(): array
    {
        $groups = Group::where('business_id', $this->businessId)->get();
        
        $data = [];
        foreach ($groups as $group) {
            $data[] = [
                $group->name,
            ];
        }
        
        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0']
                ]
            ],
        ];
    }
}

class SubgroupsSheet implements FromArray, WithHeadings, WithStyles
{
    protected $businessId;

    public function __construct($businessId)
    {
        $this->businessId = $businessId;
    }

    public function headings(): array
    {
        return [
            'Subgroup Name',
        ];
    }

    public function array(): array
    {
        $subgroups = SubGroup::where('business_id', $this->businessId)->get();
        
        $data = [];
        foreach ($subgroups as $subgroup) {
            $data[] = [
                $subgroup->name,
            ];
        }
        
        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB']
                ]
            ]
        ];
    }
}

class BranchesSheet implements FromArray, WithHeadings, WithStyles
{
    protected $businessId;

    public function __construct($businessId)
    {
        $this->businessId = $businessId;
    }

    public function headings(): array
    {
        return [
            'Branch Name',
        ];
    }

    public function array(): array
    {
        $branches = Branch::where('business_id', $this->businessId)->get();
        
        $data = [];
        foreach ($branches as $branch) {
            $data[] = [
                $branch->name,
            ];
        }
        
        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB']
                ]
            ]
        ];
    }
} 