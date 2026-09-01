<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

use App\Models\ItemUnit;
use App\Models\SubGroup;
use App\Models\Group;
use App\Models\Department;
use App\Models\ServicePoint;
use App\Models\Room;
use App\Models\Title;
use App\Models\Qualification;
use App\Models\Section;
use App\Models\PatientCategory;
use App\Models\InsuranceCompany;
use App\Models\Supplier;
use App\Models\Store;

class DynamicTemplateImport implements ToModel, WithHeadingRow
{
    protected $businessId;
    protected $branchId;

    // List of keys/models that have branch_id column
    protected $modelsWithBranchId = [
        'service_point',
        'room',
        'store',
    ];

    protected $mapping = [
        'item_unit' => ItemUnit::class,
        'sub_group' => SubGroup::class,
        'group' => Group::class,
        'department' => Department::class,
        'service_point' => ServicePoint::class,
        'room' => Room::class,
        'title' => Title::class,
        'qualifications' => Qualification::class,
        'sections' => Section::class,
        'patient_category' => PatientCategory::class,
        'insurance_company' => InsuranceCompany::class,
        'supplier' => Supplier::class,
        'store' => Store::class,
    ];

    public function __construct($businessId, $branchId)
    {
        $this->businessId = $businessId;
        $this->branchId = $branchId;
    }

    public function model(array $row)
    {
        foreach ($this->mapping as $key => $modelClass) {
            if (isset($row[$key]) && !empty($row[$key])) {
                $value = $row[$key];

                // Build the "where" criteria depending on whether this model has branch_id
                $where = [
                    'name' => $value,
                    'business_id' => $this->businessId,
                ];

                if (in_array($key, $this->modelsWithBranchId)) {
                    $where['branch_id'] = $this->branchId;
                }

                $modelClass::updateOrCreate(
                    $where,
                    ['name' => $value]
                );
            }
        }

        return null;
    }
}
