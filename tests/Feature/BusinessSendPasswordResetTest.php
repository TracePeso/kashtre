<?php

namespace Tests\Feature;

use App\Imports\StaffTemplateImport;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessSendPasswordResetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_new_business_sends_password_reset_by_default(): void
    {
        $business = $this->makeHospital();

        $this->assertTrue($business->sendsPasswordResetLink());
        $this->assertTrue($business->send_password_reset);
        $this->assertSame('', $business->importedUserPasswordHash());
    }

    public function test_bulk_import_defaults_to_sending_password_reset(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $import = new \App\Imports\BusinessTemplateImport();
        $created = $import->model([
            'name' => 'Bulk Reset Default Biz',
            'email' => 'bulk-reset-'.Str::random(8).'@example.com',
            'phone' => '256700000031',
            'address' => 'Kampala',
        ]);

        $this->assertInstanceOf(Business::class, $created);
        $this->assertTrue($created->sendsPasswordResetLink());
    }

    public function test_bulk_import_can_turn_password_reset_off(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $import = new \App\Imports\BusinessTemplateImport();
        $created = $import->model([
            'name' => 'Bulk Reset Off Biz',
            'email' => 'bulk-reset-off-'.Str::random(8).'@example.com',
            'phone' => '256700000032',
            'address' => 'Kampala',
            'send_password_reset' => 'no',
        ]);

        $this->assertInstanceOf(Business::class, $created);
        $this->assertFalse($created->sendsPasswordResetLink());
        $this->assertTrue(Hash::check(Business::IMPORTED_USER_DEFAULT_PASSWORD, $created->importedUserPasswordHash()));
    }

    public function test_imported_staff_get_default_password_when_reset_link_is_off(): void
    {
        $business = $this->makeHospital(['send_password_reset' => false]);
        $import = new StaffTemplateImport($business->id, null);

        $user = $import->model([
            'email' => 'imported-default-'.Str::random(8).'@example.com',
            'surname' => 'Demo',
            'first_name' => 'User',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $user->refresh();
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_imported_staff_get_empty_password_when_reset_link_is_on(): void
    {
        $business = $this->makeHospital(['send_password_reset' => true]);
        $import = new StaffTemplateImport($business->id, null);

        $user = $import->model([
            'email' => 'imported-reset-'.Str::random(8).'@example.com',
            'surname' => 'Demo',
            'first_name' => 'Reset',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $user->refresh();
        $this->assertSame('', $user->password);
    }

    public function test_parse_send_password_reset_flag(): void
    {
        $this->assertTrue(Business::parseSendPasswordResetFlag(null));
        $this->assertTrue(Business::parseSendPasswordResetFlag(''));
        $this->assertTrue(Business::parseSendPasswordResetFlag('yes'));
        $this->assertFalse(Business::parseSendPasswordResetFlag('no'));
        $this->assertFalse(Business::parseSendPasswordResetFlag('0'));
        $this->assertTrue(Business::parseSendPasswordResetFlag('1'));
    }

    private function makeHospital(array $overrides = []): Business
    {
        return Business::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Password Invite Test Biz',
            'email' => 'pwd-biz-'.uniqid().'@example.com',
            'phone' => '0700000000',
            'address' => 'Kampala',
            'account_number' => 'ACC'.strtoupper(uniqid()),
            'entity_code' => 'E'.strtoupper(substr(uniqid(), -5)),
            'currency_code' => 'UGX',
        ], $overrides));
    }
}
