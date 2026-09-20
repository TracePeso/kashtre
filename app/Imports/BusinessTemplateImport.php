<?php

namespace App\Imports;

use App\Models\Business;
use App\Support\SharedTime;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewBusinessCreatedMail;

class BusinessTemplateImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        // Skip empty rows
        if (empty($row['name']) || empty($row['email']) || empty($row['phone'])) {
            return null;
        }

        // Build full name - check multiple possible column names
        $name = '';
        $email = '';
        $phone = '';
        $address = '';
        
        // Name variations
        $nameVariations = ['name', 'Name'];
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

        // Generate time-based account number with prefix 'KS'
        $accountNumber = 'KS' . time() . rand(10, 99);

        $business = new Business([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'account_number' => $accountNumber,
            'logo' => null, // Logo will need to be uploaded separately
            'require_2fa' => $this->requireTwoFactorFromRow($row),
        ]);

        // Save the business first
        $business->save();

        $timezone = SharedTime::timezoneFromSpreadsheetRow($row) ?: SharedTime::defaultTimezoneId();
        try {
            SharedTime::assignBusinessTimezone($business, $timezone, 'Set from business bulk upload');
        } catch (\Throwable $e) {
            Log::warning('Business timezone was not applied during bulk upload: '.$e->getMessage(), [
                'business_id' => $business->id,
                'timezone' => $timezone,
            ]);
        }

        // Send welcome email
        try {
            Mail::to($business->email)->send(new NewBusinessCreatedMail($business));
        } catch (\Exception $e) {
            // Handle email sending errors silently
            // The business is still created even if email fails
        }

        return $business;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:businesses,email',
            'phone' => 'required|max:20',
            'address' => 'required|string|max:255',
            'timezone' => SharedTime::timezoneValidationRule(required: false),
            'operational_timezone' => SharedTime::timezoneValidationRule(required: false),
            'require_2fa' => 'nullable',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'name.required' => 'The name field is required.',
            'name.string' => 'The name must be a string.',
            'name.max' => 'The name may not be greater than 255 characters.',
            'email.required' => 'The email field is required.',
            'email.email' => 'The email must be a valid email address.',
            'email.unique' => 'The email has already been taken.',
            'phone.required' => 'The phone field is required.',
            'phone.max' => 'The phone may not be greater than 20 characters.',
            'address.required' => 'The address field is required.',
            'address.string' => 'The address must be a string.',
            'address.max' => 'The address may not be greater than 255 characters.',
            'timezone.in' => 'The timezone must be an IANA name from Settings → Manage Timezones.',
            'operational_timezone.in' => 'The timezone must be an IANA name from Settings → Manage Timezones.',
        ];
    }

    private function requireTwoFactorFromRow(array $row): bool
    {
        foreach (['require_2fa', 'require_two_factor', 'two_factor', '2fa'] as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null) {
                continue;
            }

            return Business::parseRequireTwoFactorFlag($row[$key], true);
        }

        return true;
    }
} 