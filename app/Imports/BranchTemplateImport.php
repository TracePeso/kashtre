<?php

namespace App\Imports;

use App\Models\Branch;
use App\Models\Business;
use App\Support\SharedTime;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\BranchCreatedMail;

class BranchTemplateImport implements ToModel, WithHeadingRow, WithValidation
{
    protected $businessId;

    public function __construct($businessId)
    {
        $this->businessId = $businessId;
    }

    public function model(array $row)
    {
        // Skip empty rows
        if (empty($row['branch_name']) || empty($row['email']) || empty($row['phone'])) {
            return null;
        }

        // Build full name - check multiple possible column names
        $name = '';
        $email = '';
        $phone = '';
        $address = '';
        
        // Name variations
        $nameVariations = ['branch_name', 'branch name', 'Branch Name'];
        foreach ($nameVariations as $field) {
            if (isset($row[$field])) {
                $name = is_string($row[$field]) ? $row[$field] : (string)$row[$field];
                break;
            }
        }
        
        // Email variations
        $emailVariations = ['email', 'Email'];
        foreach ($emailVariations as $field) {
            if (isset($row[$field])) {
                $email = is_string($row[$field]) ? $row[$field] : (string)$row[$field];
                break;
            }
        }
        
        // Phone variations
        $phoneVariations = ['phone', 'Phone'];
        foreach ($phoneVariations as $field) {
            if (isset($row[$field])) {
                $phone = is_string($row[$field]) ? $row[$field] : (string)$row[$field];
                break;
            }
        }
        
        // Address variations
        $addressVariations = ['address', 'Address'];
        foreach ($addressVariations as $field) {
            if (isset($row[$field])) {
                $address = is_string($row[$field]) ? $row[$field] : (string)$row[$field];
                break;
            }
        }

        $branch = new Branch([
            'uuid' => Str::uuid(),
            'business_id' => $this->businessId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
        ]);

        // Save the branch first
        $branch->save();

        $timezone = SharedTime::timezoneFromSpreadsheetRow($row);
        if (is_string($timezone) && strcasecmp($timezone, 'inherit') === 0) {
            $timezone = null;
        }

        try {
            SharedTime::assignBranchTimezone(
                $branch,
                $timezone,
                $timezone
                    ? 'Set as a branch override from branch bulk upload'
                    : 'Inherits the business timezone from branch bulk upload',
            );
        } catch (\Throwable $e) {
            Log::warning('Branch timezone was not applied during bulk upload: '.$e->getMessage(), [
                'branch_id' => $branch->id,
                'timezone' => $timezone,
            ]);
        }

        // Send welcome email
        try {
            $company = Business::find($this->businessId);
            Mail::send(new BranchCreatedMail($branch, $company));
        } catch (\Exception $e) {
            // Handle email sending errors silently
            // The branch is still created even if email fails
        }

        return $branch;
    }

    public function rules(): array
    {
        return [
            'branch_name' => 'nullable|string|max:255',
            'branch name' => 'nullable|string|max:255',
            'Branch Name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|max:20',
            'address' => 'required|string|max:255',
            'timezone' => SharedTime::timezoneValidationRule(required: false, allowInherit: true),
        ];
    }

    public function customValidationMessages()
    {
        return [
            'branch_name.string' => 'The branch name must be a string.',
            'branch_name.max' => 'The branch name may not be greater than 255 characters.',
            'branch name.string' => 'The branch name must be a string.',
            'branch name.max' => 'The branch name may not be greater than 255 characters.',
            'Branch Name.string' => 'The branch name must be a string.',
            'Branch Name.max' => 'The branch name may not be greater than 255 characters.',
            'email.required' => 'The email field is required.',
            'email.email' => 'The email must be a valid email address.',
            'email.max' => 'The email may not be greater than 255 characters.',
            'phone.required' => 'The phone field is required.',
            'phone.max' => 'The phone may not be greater than 20 characters.',
            'address.required' => 'The address field is required.',
            'address.string' => 'The address must be a string.',
            'address.max' => 'The address may not be greater than 255 characters.',
            'timezone.in' => 'Leave timezone blank to inherit, or use an IANA name from Settings → Manage Timezones.',
        ];
    }
} 