<?php

namespace App\Imports;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Qualification;
use App\Models\ServicePoint;
use App\Models\Title;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StaffTemplateImport implements ToModel, WithHeadingRow
{
    protected $businessId;

    protected $branchId;

    public function __construct($businessId, $branchId)
    {
        $this->businessId = $businessId;
        $this->branchId = $branchId;
    }

    public function model(array $row)
    {
        // Skip completely empty rows
        if (empty(array_filter($row))) {
            return null;
        }

        // Email is the minimum identifier for enrollment (names optional; match main system).
        $emailProbe = $row['email'] ?? $row['Email'] ?? null;
        if ($emailProbe === null || trim((string) $emailProbe) === '') {
            return null;
        }

        // Build full name - check multiple possible column names
        $surname = '';
        $firstName = '';
        $middleName = '';

        // Surname variations
        $surnameVariations = ['surname', 'Surname'];
        foreach ($surnameVariations as $field) {
            if (isset($row[$field])) {
                $surname = is_string($row[$field]) ? $row[$field] : (string) $row[$field];
                break;
            }
        }

        // First name variations
        $firstNameVariations = ['first_name', 'first name', 'First Name'];
        foreach ($firstNameVariations as $field) {
            if (isset($row[$field])) {
                $firstName = is_string($row[$field]) ? $row[$field] : (string) $row[$field];
                break;
            }
        }

        // Middle name variations
        $middleNameVariations = ['middle_name', 'middle name', 'Middle Name'];
        foreach ($middleNameVariations as $field) {
            if (isset($row[$field])) {
                $middleName = is_string($row[$field]) ? $row[$field] : (string) $row[$field];
                break;
            }
        }

        // Build full name (fallback to email local-part)
        $name = trim($surname.' '.$firstName.' '.$middleName);
        if ($name === '') {
            $emailProbeStr = trim((string) $emailProbe);
            $name = $emailProbeStr !== '' ? explode('@', $emailProbeStr)[0] : 'User';
        }

        // Clean and validate data - check multiple possible column names
        $email = '';
        $phone = '';
        $nin = '';
        $gender = null;

        // Email variations
        $emailVariations = ['email', 'Email'];
        foreach ($emailVariations as $field) {
            if (isset($row[$field])) {
                $email = is_string($row[$field]) ? $row[$field] : (string) $row[$field];
                break;
            }
        }
        if (trim($email) === '') {
            $email = trim((string) $emailProbe);
        }

        // Phone variations
        $phoneVariations = ['phone', 'Phone'];
        foreach ($phoneVariations as $field) {
            if (isset($row[$field])) {
                $phone = is_string($row[$field]) ? $row[$field] : (string) $row[$field];
                break;
            }
        }

        // NIN / National ID variations
        $ninVariations = ['nin', 'NIN', 'national_id', 'national_id_nin', 'National ID (NIN)', 'national id (nin)'];
        foreach ($ninVariations as $field) {
            if (isset($row[$field])) {
                $nin = is_string($row[$field]) ? $row[$field] : (string) $row[$field];
                break;
            }
        }

        // Gender variations
        $genderVariations = ['gender', 'gender_male_or_female', 'Gender (male or female)', 'gender_malefemaleother'];
        foreach ($genderVariations as $field) {
            if (isset($row[$field])) {
                $genderValue = is_string($row[$field]) ? $row[$field] : (string) $row[$field];
                $gender = in_array(strtolower($genderValue), ['male', 'female', 'other']) ? strtolower($genderValue) : null;
                break;
            }
        }

        // Birth date / marital (optional HR fields)
        $birthDate = null;
        foreach (['birth_date', 'Birth Date (YYYY-MM-DD)', 'birth_date_yyyy_mm_dd', 'date_of_birth', 'Date of Birth'] as $field) {
            if (! empty($row[$field] ?? null)) {
                $raw = trim((string) $row[$field]);
                try {
                    $birthDate = \Carbon\Carbon::parse($raw)->format('Y-m-d');
                } catch (\Throwable) {
                    $birthDate = null;
                }
                break;
            }
        }

        $maritalStatus = null;
        $allowedMarital = ['single', 'married', 'divorced', 'widowed', 'separated', 'other'];
        foreach (['marital_status', 'Marital Status (single/married/divorced/widowed/separated/other)', 'marital_status_singlemarrieddivorcedwidowedseparatedother', 'marital status'] as $field) {
            if (! empty($row[$field] ?? null)) {
                $mv = strtolower(trim((string) $row[$field]));
                $maritalStatus = in_array($mv, $allowedMarital, true) ? $mv : null;
                break;
            }
        }

        // Status
        $status = 'active'; // default
        $statusVariations = ['status', 'Status', 'status_activeinactivesuspended'];
        foreach ($statusVariations as $field) {
            if (isset($row[$field])) {
                $statusValue = is_string($row[$field]) ? $row[$field] : (string) $row[$field];
                $status = in_array(strtolower($statusValue), ['active', 'inactive', 'suspended']) ? strtolower($statusValue) : 'active';
                break;
            }
        }

        // Qualification
        $qualificationId = null;
        $qualificationVariations = ['qualification_name', 'qualification name', 'Qualification Name'];
        foreach ($qualificationVariations as $field) {
            if (isset($row[$field]) && ! empty($row[$field])) {
                $qualificationName = is_string($row[$field]) ? $row[$field] : (string) $row[$field];
                $qualification = Qualification::where('business_id', $this->businessId)
                    ->where('name', $qualificationName)
                    ->first();
                if ($qualification) {
                    $qualificationId = $qualification->id;
                }
                break;
            }
        }

        // Title
        $titleId = null;
        $titleVariations = ['title_name', 'title name', 'Title Name'];
        foreach ($titleVariations as $field) {
            if (isset($row[$field]) && ! empty($row[$field])) {
                $titleName = is_string($row[$field]) ? $row[$field] : (string) $row[$field];
                $title = Title::where('business_id', $this->businessId)
                    ->where('name', $titleName)
                    ->first();
                if ($title) {
                    $titleId = $title->id;
                }
                break;
            }
        }

        // Department
        $departmentId = null;
        $departmentVariations = ['department_name', 'department name', 'Department Name'];
        foreach ($departmentVariations as $field) {
            if (isset($row[$field]) && ! empty($row[$field])) {
                $departmentName = is_string($row[$field]) ? $row[$field] : (string) $row[$field];
                $department = Department::where('business_id', $this->businessId)
                    ->where('name', $departmentName)
                    ->first();
                if ($department) {
                    $departmentId = $department->id;
                }
                break;
            }
        }

        // Service Point (single selection)
        $servicePoints = [];
        $servicePointVariations = ['service_point_name', 'service point name', 'Service Point Name'];
        foreach ($servicePointVariations as $field) {
            if (isset($row[$field]) && ! empty($row[$field])) {
                $servicePointName = is_string($row[$field]) ? $row[$field] : (string) $row[$field];
                $sp = ServicePoint::where('business_id', $this->businessId)
                    ->where('name', $servicePointName)
                    ->first();
                if ($sp) {
                    $servicePoints[] = $sp->id;
                }
                break;
            }
        }

        // Allowed Branch (single selection)
        $allowedBranches = [$this->branchId]; // default to current branch
        $branchVariations = ['allowed_branch_name', 'allowed branch name', 'Allowed Branch Name'];
        foreach ($branchVariations as $field) {
            if (isset($row[$field]) && ! empty($row[$field])) {
                $branchName = is_string($row[$field]) ? $row[$field] : (string) $row[$field];
                $branch = Branch::where('business_id', $this->businessId)
                    ->where('name', $branchName)
                    ->first();
                if ($branch) {
                    $allowedBranches = [$branch->id];
                }
                break;
            }
        }

        // Permissions - Set default based on contractor status (will be determined below)
        $permissions = [];

        // Check if contractor - convert to lowercase and trim
        // Check multiple possible column names for contractor field
        $contractorField = null;
        $possibleNames = [
            'is_contractor',
            'is contractor',
            'contractor',
            'iscontractor',
            'Is Contractor (Yes/No)',
            'is_contractor_yesno',
            'Is Contractor (Yes or No)',
            'is_contractor_(yes_or_no)',
            'is_contractor_yes_or_no',
            'iscontractor_yes_or_no',
        ];

        foreach ($possibleNames as $contractorFieldName) {
            if (isset($row[$contractorFieldName])) {
                $contractorField = $contractorFieldName;
                break;
            }
        }

        $contractorValue = '';
        if ($contractorField) {
            $contractorValue = strtolower(trim($row[$contractorField] ?? ''));
        }

        Log::info('Contractor Field Name: '.($contractorField ?? 'NOT FOUND'));
        Log::info("Contractor Value: '{$contractorValue}'");
        Log::info('Available columns: '.json_encode(array_keys($row)));

        $isContractor = $contractorValue === 'yes';

        // Contractor fields - check multiple possible column names
        $bankName = '';
        $accountName = '';
        $accountNumber = '';

        // Bank name variations
        $bankNameVariations = ['bank_name', 'bank name', 'Bank Name'];
        foreach ($bankNameVariations as $field) {
            if (isset($row[$field])) {
                $bankName = is_string($row[$field]) ? $row[$field] : (string) $row[$field];
                break;
            }
        }

        // Account name variations
        $accountNameVariations = ['account_name', 'account name', 'Account Name'];
        foreach ($accountNameVariations as $field) {
            if (isset($row[$field])) {
                $accountName = is_string($row[$field]) ? $row[$field] : (string) $row[$field];
                break;
            }
        }

        // Account number variations
        $accountNumberVariations = ['account_number', 'account number', 'Account Number'];
        foreach ($accountNumberVariations as $field) {
            if (isset($row[$field])) {
                $accountNumber = is_string($row[$field]) ? $row[$field] : (string) $row[$field];
                break;
            }
        }

        // Set default permissions based on contractor status
        if ($isContractor) {
            $permissions = [
                'Contractor',
                'View Contractor',
                'Edit Contractor',
                'Add Contractor',
            ];
        } else {
            $permissions = ['View Dashboard'];
        }

        Log::info('=== CREATING STAFF USER ===');
        Log::info("Name: {$name}");
        Log::info("Email: {$email}");
        Log::info('Is Contractor: '.($isContractor ? 'Yes' : 'No'));
        Log::info('Permissions: '.json_encode($permissions));
        Log::info('Qualification ID: '.($qualificationId ?? 'null'));
        Log::info('Title ID: '.($titleId ?? 'null'));
        Log::info('Department ID: '.($departmentId ?? 'null'));

        $user = new User([
            'name' => $name,
            'email' => $email,
            'phone' => $phone ?: null,
            'nin' => $nin ?: null,
            'gender' => $gender,
            'birth_date' => $birthDate,
            'marital_status' => $maritalStatus,
            'status' => $status,
            'business_id' => $this->businessId,
            'branch_id' => $this->branchId,
            'qualification_id' => $qualificationId,
            'title_id' => $titleId,
            'department_id' => $departmentId,
            'service_points' => $servicePoints,
            'allowed_branches' => $allowedBranches,
            'permissions' => $permissions,
            'password' => '', // Empty password for password reset
        ]);

        // Save the user first
        $user->save();

        Log::info("User saved with ID: {$user->id}");
        Log::info('Saved Qualification ID: '.($user->qualification_id ?? 'null'));
        Log::info('Saved Title ID: '.($user->title_id ?? 'null'));
        Log::info('Saved Department ID: '.($user->department_id ?? 'null'));

        // Create contractor profile if needed
        if ($isContractor) {
            // Check if contractor profile already exists for this user
            $existingProfile = \App\Models\ContractorProfile::where('user_id', $user->id)->first();
            if (! $existingProfile) {
                try {
                    $contractorProfile = \App\Models\ContractorProfile::create([
                        'user_id' => $user->id,
                        'business_id' => $this->businessId,
                        'bank_name' => $bankName,
                        'account_name' => $accountName,
                        'account_number' => $accountNumber,
                    ]);
                    Log::info("Created contractor profile for user: {$user->id}");
                } catch (\Exception $e) {
                    Log::error("Failed to create contractor profile for user {$user->id}: ".$e->getMessage());
                }
            } else {
                Log::info("Contractor profile already exists for user: {$user->id}");
            }
        }

        return $user;
    }
}
