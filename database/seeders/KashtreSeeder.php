<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Branch;
use App\Models\User;
use App\Models\Organization;
use App\Models\Qualification;
use App\Models\Department;
use App\Models\Section;
use App\Models\Title;
use App\Models\Group;
use App\Models\SubGroup;
use App\Models\ServicePoint;
use App\Models\Room;
use App\Models\ItemUnit;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\InsuranceCompany;
use App\Models\PatientCategory;
use App\Models\ContractorProfile;
use App\Services\HrDefaultLeaveTypeService;
use App\Services\HrDefaultShiftTypeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class KashtreSeeder extends Seeder
{
    public function run(): void
    {
        // Create 2 businesses with comprehensive data
        $this->createBusinessWithData('Kashtre Medical Center', 'katznicho@gmail.com', '256700000001', 'Kampala, Uganda');
        $this->createBusinessWithData('City Health Clinic', 'admin@cityhealth.com', '256700000002', 'Nakawa, Kampala');
    }

    private function createBusinessWithData($businessName, $email, $phone, $address)
    {
        // 1. Create the Business
        $business = Business::create([
            'name' => $businessName,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'logo' => 'logos/default.png',
            'account_number' => 'KS' . Str::random(8),
            'date' => now(),
        ]);

        $organization = Organization::firstOrCreate(
            ['external_business_uuid' => $business->uuid],
            ['name' => $business->name]
        );
        app(HrDefaultShiftTypeService::class)->seedMissingDefaults($organization);
        app(HrDefaultLeaveTypeService::class)->seedMissingDefaults($organization);

        // 2. Create 2 Branches
        $branches = [];
        for ($i = 1; $i <= 2; $i++) {
            $branches[] = Branch::create([
                'uuid' => Str::uuid(),
                'business_id' => $business->id,
                'name' => $businessName . ' - Branch ' . $i,
                'email' => 'branch' . $i . '@' . strtolower(str_replace(' ', '', $businessName)) . '.com',
                'phone' => '2567000000' . (10 + $i),
                'address' => $address . ' - Branch ' . $i,
            ]);
        }

        // 3. Create Qualifications
        $qualifications = [
            'MBChB', 'PhD', 'Diploma in Nursing', 'Bachelor of Dental Surgery', 
            'Master of Public Health', 'BSc Nursing', 'Diploma in Pharmacy'
        ];

        foreach ($qualifications as $name) {
            Qualification::firstOrCreate([
                'business_id' => $business->id,
                'name' => $name,
            ]);
        }

        // 4. Create Departments
        $departments = [
            'Administration', 'Finance', 'Outpatient Department', 'Laboratory', 
            'Pharmacy', 'Radiology', 'Emergency', 'Surgery', 'Pediatrics'
        ];

        foreach ($departments as $name) {
            Department::firstOrCreate([
                'business_id' => $business->id,
                'name' => $name,
            ]);
        }

        // 5. Create Sections
        $sections = [
            'General Medicine', 'Pediatrics', 'Surgery', 'Radiology', 
            'Obstetrics & Gynecology', 'Emergency Medicine', 'Cardiology'
        ];

        foreach ($sections as $name) {
            Section::firstOrCreate([
                'business_id' => $business->id,
                'name' => $name,
            ]);
        }

        // 6. Create Titles
        $titles = [
            'Dr.', 'Mr.', 'Ms.', 'Prof.', 'Eng.', 'Sr.', 'Mrs.'
        ];

        foreach ($titles as $name) {
            Title::firstOrCreate([
                'business_id' => $business->id,
                'name' => $name,
            ]);
        }

        // 7. Create Groups
        $groups = [
            'Medicines', 'Medical Supplies', 'Laboratory Tests', 'Consultations', 
            'Procedures', 'Equipment', 'Services'
        ];

        foreach ($groups as $name) {
            Group::firstOrCreate([
                'business_id' => $business->id,
                'name' => $name,
            ]);
        }

        // 8. Create SubGroups (standalone, not linked to groups due to schema limitation)
        $subGroups = [
            'Antibiotics', 'Pain Killers', 'Vitamins', 'Antimalarials',
            'Syringes', 'Bandages', 'Gloves', 'Masks',
            'Blood Tests', 'Urine Tests', 'Stool Tests', 'X-Ray',
            'General Consultation', 'Specialist Consultation', 'Emergency Consultation',
            'Minor Surgery', 'Dental Procedures', 'Vaccinations',
            'Medical Devices', 'Monitoring Equipment', 'Surgical Tools',
            'Nursing Care', 'Pharmacy Services', 'Laboratory Services'
        ];

        foreach ($subGroups as $name) {
            SubGroup::firstOrCreate([
                'business_id' => $business->id,
                'name' => $name,
                'description' => 'Sub group for ' . $name,
            ]);
        }

        // 9. Create Service Points for each Branch
        foreach ($branches as $branch) {
            $servicePoints = [
                'Reception', 'Pharmacy', 'Laboratory', 'Consultation Room', 
                'Emergency Room', 'Radiology', 'Nursing Station'
            ];

            foreach ($servicePoints as $name) {
                ServicePoint::firstOrCreate([
                    'business_id' => $business->id,
                    'branch_id' => $branch->id,
                    'name' => $branch->name . ' - ' . $name,
                ]);
            }
        }

        // 10. Create Rooms for each Branch
        foreach ($branches as $branch) {
            $rooms = [
                'Consultation Room 1', 'Consultation Room 2', 'Emergency Room', 
                'Laboratory Room', 'Pharmacy Room', 'Waiting Room', 'Nursing Station'
            ];

            foreach ($rooms as $name) {
                Room::firstOrCreate([
                    'business_id' => $business->id,
                    'branch_id' => $branch->id,
                    'name' => $name,
                    'description' => 'Room for ' . $name,
                ]);
            }
        }

        // 11. Create Item Units
        $itemUnits = [
            'Tablets', 'Capsules', 'Bottles', 'Pieces', 'Boxes', 'Packs', 
            'Units', 'Tests', 'Procedures', 'Consultations'
        ];

        foreach ($itemUnits as $name) {
            ItemUnit::firstOrCreate([
                'business_id' => $business->id,
                'name' => $name,
            ]);
        }

        // 12. Create Stores
        $stores = [
            'Main Store', 'Pharmacy Store', 'Laboratory Store', 'Emergency Store'
        ];

        foreach ($stores as $name) {
            Store::firstOrCreate([
                'business_id' => $business->id,
                'name' => $name,
                'description' => 'Store for ' . $name,
            ]);
        }

        // 13. Create Suppliers
        $suppliers = [
            'MedPharm Ltd', 'HealthCare Supplies', 'Medical Equipment Co', 
            'Pharmaceutical Distributors', 'Lab Supplies Inc'
        ];

        foreach ($suppliers as $name) {
            Supplier::firstOrCreate([
                'business_id' => $business->id,
                'name' => $name,
                'description' => 'Supplier for ' . $name,
            ]);
        }

        // 14. Create Insurance Companies
        $insuranceCompanies = [
            'AAR Insurance', 'UAP Insurance', 'Jubilee Insurance', 
            'Prudential Insurance', 'National Insurance'
        ];

        foreach ($insuranceCompanies as $name) {
            InsuranceCompany::firstOrCreate([
                'business_id' => $business->id,
                'name' => $name,
                'description' => 'Insurance provider: ' . $name,
            ]);
        }

        // 15. Create Patient Categories
        $patientCategories = [
            'General Patient', 'VIP Patient', 'Insurance Patient', 
            'Emergency Patient', 'Referral Patient', 'Staff Patient'
        ];

        foreach ($patientCategories as $name) {
            PatientCategory::firstOrCreate([
                'business_id' => $business->id,
                'name' => $name,
                'description' => 'Category for ' . $name,
            ]);
        }

        // 16. Create Contractor Profiles (only for City Health Clinic)
        if ($business->name === 'City Health Clinic') {
            $contractors = [
                'Dr. John Smith', 'Dr. Sarah Johnson', 'Dr. Michael Brown'
            ];

            foreach ($contractors as $name) {
                ContractorProfile::firstOrCreate([
                    'business_id' => $business->id,
                    'user_id' => null, // Will be linked when user is created
                    'bank_name' => 'Stanbic Bank',
                    'account_name' => $name,
                    'account_number' => 'ACC' . Str::random(8),
                    'account_balance' => 0.00,
                    'kashtre_account_number' => 'KASH' . Str::random(6),
                    'signing_qualifications' => 'MBChB, PhD',
                ]);
            }
        }

        // 17. Create Users for this Business (including contractors)
        $this->createUsersForBusiness($business, $branches);
    }

    private function createUsersForBusiness($business, $branches)
    {
        // Fetch lookup IDs scoped to this business
        $qualificationId = Qualification::where('business_id', $business->id)->where('name', 'MBChB')->value('id');
        $departmentId = Department::where('business_id', $business->id)->where('name', 'Administration')->value('id');
        $sectionId = Section::where('business_id', $business->id)->where('name', 'General Medicine')->value('id');
        $titleId = Title::where('business_id', $business->id)->where('name', 'Dr.')->value('id');

        // Get service points for the first branch
        $servicePoints = ServicePoint::where('business_id', $business->id)
            ->where('branch_id', $branches[0]->id)
            ->pluck('id')
            ->toArray();

        // User 1 - Admin
        User::create([
            'uuid' => Str::uuid(),
            'name' => $business->name . ' Admin',
            'email' => 'admin@' . strtolower(str_replace(' ', '', $business->name)) . '.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'phone' => '2567000000' . rand(10, 99),
            'nin' => 'CF' . Str::random(12),
            'profile_photo_path' => null,
            'business_id' => $business->id,
            'branch_id' => $branches[0]->id,
            'service_points' => $servicePoints,
            'permissions' => [
                'View Dashboard', 'View Dashboard Cards', 'View Dashboard Charts',
                'Manage Users', 'Manage Settings', 'View Reports', 'Manage Items',
                'Manage Suppliers', 'Manage Insurance', 'Manage Patient Categories'
            ],
            'allowed_branches' => collect($branches)->pluck('id')->toArray(),
            'qualification_id' => $qualificationId,
            'department_id' => $departmentId,
            'section_id' => $sectionId,
            'title_id' => $titleId,
            'gender' => 'male',
        ]);

        // User 2 - Staff
        $servicePoints2 = ServicePoint::where('business_id', $business->id)
            ->where('branch_id', $branches[1]->id)
            ->pluck('id')
            ->toArray();

        User::create([
            'uuid' => Str::uuid(),
            'name' => $business->name . ' Staff',
            'email' => 'staff@' . strtolower(str_replace(' ', '', $business->name)) . '.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'phone' => '2567000000' . rand(10, 99),
            'nin' => 'CF' . Str::random(12),
            'profile_photo_path' => null,
            'business_id' => $business->id,
            'branch_id' => $branches[1]->id,
            'service_points' => $servicePoints2,
            'permissions' => [
                'View Dashboard', 'View Dashboard Cards', 'View Reports',
                'Manage Items', 'View Suppliers', 'View Insurance'
            ],
            'allowed_branches' => [$branches[1]->id],
            'qualification_id' => $qualificationId,
            'department_id' => $departmentId,
            'section_id' => $sectionId,
            'title_id' => $titleId,
            'gender' => 'female',
        ]);

        // Create 3 Contractors who are also Staff (for City Health Clinic only)
        if ($business->name === 'City Health Clinic') {
            $contractorNames = ['Dr. John Smith', 'Dr. Sarah Johnson', 'Dr. Michael Brown'];
            $contractorEmails = ['john.smith@cityhealth.com', 'sarah.johnson@cityhealth.com', 'michael.brown@cityhealth.com'];
            $contractorPhones = ['2567000001', '2567000002', '2567000003'];
            
            foreach ($contractorNames as $index => $name) {
                $contractorUser = User::create([
                    'uuid' => Str::uuid(),
                    'name' => $name,
                    'email' => $contractorEmails[$index],
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'phone' => $contractorPhones[$index],
                    'nin' => 'CF' . Str::random(12),
                    'profile_photo_path' => null,
                    'business_id' => $business->id,
                    'branch_id' => $branches[0]->id, // Main branch
                    'service_points' => $servicePoints,
                    'permissions' => [
                        'View Dashboard', 'View Dashboard Cards', 'View Reports',
                        'Manage Items', 'View Suppliers', 'View Insurance',
                        'Manage Contractor Profile'
                    ],
                    'allowed_branches' => collect($branches)->pluck('id')->toArray(),
                    'qualification_id' => $qualificationId,
                    'department_id' => $departmentId,
                    'section_id' => $sectionId,
                    'title_id' => $titleId,
                    'gender' => $index === 1 ? 'female' : 'male',
                ]);

                // Link the contractor profile to this user
                ContractorProfile::where('business_id', $business->id)
                    ->where('account_name', $name)
                    ->update(['user_id' => $contractorUser->id]);
            }
        }
    }
}
