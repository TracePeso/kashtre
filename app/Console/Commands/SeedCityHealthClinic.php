<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Department;
use App\Models\Item;
use App\Models\Qualification;
use App\Models\Section;
use App\Models\ServicePoint;
use App\Models\Title;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedCityHealthClinic extends Command
{
    protected $signature = 'seed:city-health-clinic {--staff=3 : Number of staff users to create} {--password=Kashtre123! : Password to assign to created staff users}';

    protected $description = 'Seed City Health Clinic support data and create reusable staff accounts without duplicating existing records';

    public function handle(): int
    {
        $staffCount = max(1, (int) $this->option('staff'));
        $password = (string) $this->option('password');

        $this->info('Starting City Health Clinic setup...');

        $business = $this->ensureBusiness();
        $branch = $this->ensureMainBranch($business);
        [$qualification, $department, $section, $title] = $this->ensureLookupData($business);
        $servicePointIds = $this->ensureServicePoints($business, $branch);

        if (Item::where('business_id', $business->id)->exists()) {
            $this->line('City Health Clinic items already exist. Skipping item seeder.');
        } else {
            $this->info('Seeding City Health Clinic item data...');
            $this->callSilently('db:seed', ['--class' => 'Database\\Seeders\\CityHealthClinicItemsSeeder']);
        }

        $this->info('Seeding City Health Clinic contractor data...');
        $this->callSilently('db:seed', ['--class' => 'Database\\Seeders\\CityHealthClinicContractorsSeeder']);

        $createdStaff = $this->createStaffUsers(
            $business,
            $branch,
            $servicePointIds,
            $qualification?->id,
            $department?->id,
            $section?->id,
            $title?->id,
            $staffCount,
            $password
        );

        $this->newLine();
        $this->info('City Health Clinic setup completed.');
        $this->line('Business: ' . $business->name);
        $this->line('Branch: ' . $branch->name);
        $this->line('Staff password: ' . $password);
        $this->newLine();
        $this->info('Staff accounts:');

        foreach ($createdStaff as $staff) {
            $this->line(' - ' . $staff['name'] . ' <' . $staff['email'] . '>');
        }

        return self::SUCCESS;
    }

    private function ensureBusiness(): Business
    {
        $business = Business::where('name', 'City Health Clinic')->first();

        if ($business) {
            return $business;
        }

        $business = Business::where('name', 'LIKE', '%City Health Clinic%')->first();

        if ($business) {
            return $business;
        }

        $this->warn('City Health Clinic was not found. Creating it now...');

        return Business::create([
            'name' => 'City Health Clinic',
            'email' => 'info@cityhealthclinic.com',
            'phone' => '+256700000002',
            'address' => 'Nakawa, Kampala',
            'logo' => 'logos/default.png',
            'account_number' => 'KSCHC' . now()->format('His'),
            'date' => now(),
        ]);
    }

    private function ensureMainBranch(Business $business): Branch
    {
        $branch = $business->branches()->orderBy('id')->first();

        if ($branch) {
            return $branch;
        }

        $this->warn('No branch found for City Health Clinic. Creating Main Branch...');

        return $business->branches()->create([
            'name' => 'City Health Clinic - Main Branch',
            'email' => 'main@cityhealthclinic.com',
            'phone' => '+256700000021',
            'address' => 'Nakawa, Kampala',
        ]);
    }

    private function ensureLookupData(Business $business): array
    {
        $qualification = Qualification::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'MBChB'],
            ['description' => 'Medical qualification for City Health Clinic staff']
        );

        $department = Department::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'Administration'],
            ['description' => 'Administration department']
        );

        Department::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'Pharmacy'],
            ['description' => 'Pharmacy department']
        );

        Department::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'Laboratory'],
            ['description' => 'Laboratory department']
        );

        Department::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'Outpatient Department'],
            ['description' => 'Outpatient department']
        );

        $section = Section::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'General Medicine'],
            ['description' => 'General Medicine section']
        );

        $title = Title::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'Mr.'],
            ['description' => 'Default staff title']
        );

        Title::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'Ms.'],
            ['description' => 'Female staff title']
        );

        Title::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'Dr.'],
            ['description' => 'Doctor title']
        );

        return [$qualification, $department, $section, $title];
    }

    private function ensureServicePoints(Business $business, Branch $branch): array
    {
        $names = [
            'Reception',
            'Pharmacy',
            'Laboratory',
        ];

        foreach ($names as $name) {
            ServicePoint::firstOrCreate(
                [
                    'business_id' => $business->id,
                    'branch_id' => $branch->id,
                    'name' => $branch->name . ' - ' . $name,
                ],
                ['description' => $name . ' service point']
            );
        }

        return ServicePoint::where('business_id', $business->id)
            ->where('branch_id', $branch->id)
            ->orderBy('id')
            ->pluck('id')
            ->values()
            ->all();
    }

    private function createStaffUsers(
        Business $business,
        Branch $branch,
        array $servicePointIds,
        ?int $qualificationId,
        ?int $departmentId,
        ?int $sectionId,
        ?int $titleId,
        int $staffCount,
        string $password
    ): array {
        $createdStaff = [];
        $allowedBranches = $business->branches()->pluck('id')->all();

        if (empty($allowedBranches)) {
            $allowedBranches = [$branch->id];
        }

        for ($i = 1; $i <= $staffCount; $i++) {
            $email = 'staff' . $i . '@cityhealthclinic.com';
            $name = 'City Health Clinic Staff ' . $i;

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make($password),
                    'status' => 'active',
                    'phone' => '+2567001000' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'nin' => 'CHCSTAFF' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                    'business_id' => $business->id,
                    'branch_id' => $branch->id,
                    'service_points' => $servicePointIds,
                    'permissions' => [
                        'View Dashboard',
                        'View Dashboard Cards',
                        'View Reports',
                        'Manage Items',
                        'View Suppliers',
                        'View Insurance',
                    ],
                    'allowed_branches' => $allowedBranches,
                    'qualification_id' => $qualificationId,
                    'department_id' => $departmentId,
                    'section_id' => $sectionId,
                    'title_id' => $titleId,
                    'gender' => $i % 2 === 0 ? 'female' : 'male',
                    'email_verified_at' => now(),
                ]
            );

            $createdStaff[] = [
                'name' => $user->name,
                'email' => $user->email,
            ];
        }

        return $createdStaff;
    }
}
