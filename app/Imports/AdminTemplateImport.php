<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use App\Models\User;
use App\Models\Business;

class AdminTemplateImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        // Skip empty rows
        if (empty($row['surname']) || empty($row['first_name']) || empty($row['email'])) {
            return null;
        }

        // Build full name
        $name = trim($row['surname'] . ' ' . $row['first_name'] . ' ' . ($row['middle_name'] ?? ''));

        // Clean and validate data
        $phone = is_string($row['phone'] ?? '') ? $row['phone'] : '';
        $nin = is_string($row['nin'] ?? '') ? $row['nin'] : '';
        $gender = in_array($row['gender'] ?? '', ['male', 'female']) ? $row['gender'] : 'male';

        $business = Business::find(1);
        $branch = $business?->branches()->first();

        return new User([
            'name' => $name,
            'email' => $row['email'],
            'phone' => $phone,
            'nin' => $nin,
            'gender' => $gender,
            'business_id' => 1,
            'branch_id' => $branch?->id,
            'status' => 'active', // Default status
            'allowed_branches' => [1],
            'permissions' => [''], // Default permission
            'password' => $business?->importedUserPasswordHash() ?? '',
            'service_points' => [],
        ]);
    }

    public function rules(): array
    {
        return [
            'surname' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable',
            'nin' => 'nullable',
            'gender' => 'nullable|in:male,female',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'surname.required' => 'Surname is required.',
            'first_name.required' => 'First name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.unique' => 'Email already exists.',
            'gender.in' => 'Gender must be male or female.',
        ];
    }
} 