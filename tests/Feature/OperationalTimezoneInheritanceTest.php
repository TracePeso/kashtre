<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Transaction;
use App\Support\SharedTime;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalTimezoneInheritanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_branch_inherits_business_timezone_until_overridden(): void
    {
        $business = $this->hospitalBusiness();
        $branch = $this->makeBranch($business);

        SharedTime::assignBusinessTimezone($business, 'Africa/Kampala', 'test business tz');
        SharedTime::assignBranchTimezone($branch, null, 'test inherit');

        $inherited = SharedTime::describe((string) $business->id, (string) $branch->id);

        $this->assertSame('Africa/Kampala', $inherited['ianaId']);
        $this->assertTrue($inherited['inherited']);
        $this->assertSame('Inherited from business', $inherited['sourceLabel']);

        SharedTime::assignBranchTimezone($branch, 'Africa/Lagos', 'test override');

        $overridden = SharedTime::describe((string) $business->id, (string) $branch->id);

        $this->assertSame('Africa/Lagos', $overridden['ianaId']);
        $this->assertFalse($overridden['inherited']);
        $this->assertSame('Branch override', $overridden['sourceLabel']);
    }

    public function test_transaction_business_date_uses_resolved_timezone(): void
    {
        $business = $this->hospitalBusiness();
        $branch = $this->makeBranch($business);
        SharedTime::assignBusinessTimezone($business, 'Africa/Kampala', 'test business tz');

        $transaction = Transaction::create([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'amount' => 1000,
            'reference' => 'TZ-TEST-'.Str::random(8),
            'description' => 'Timezone inheritance test',
            'status' => 'completed',
            'type' => 'credit',
            'origin' => 'web',
            'provider' => 'mtn',
            'service' => 'timezone_test',
            'currency' => 'UGX',
        ]);

        $expected = SharedTime::businessToday((string) $business->id, (string) $branch->id);
        $actual = $transaction->date instanceof \DateTimeInterface
            ? $transaction->date->format('Y-m-d')
            : substr((string) $transaction->date, 0, 10);

        $this->assertSame($expected, $actual);

        $local = SharedTime::formatLocal(
            $transaction->created_at,
            (string) $business->id,
            (string) $branch->id,
        );

        $this->assertStringContainsString('Africa/Kampala', SharedTime::describe((string) $business->id, (string) $branch->id)['ianaId']);
        $this->assertNotSame('', $local);
    }

    public function test_business_bulk_import_assigns_timezone(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $options = array_keys(SharedTime::timezoneSelectOptions());
        if ($options === []) {
            $this->markTestSkipped('Timezone catalogue is empty.');
        }

        $iana = in_array('Africa/Nairobi', $options, true) ? 'Africa/Nairobi' : $options[0];
        $import = new \App\Imports\BusinessTemplateImport();
        $created = $import->model([
            'name' => 'Bulk TZ Business',
            'email' => 'bulk-tz-'.Str::random(8).'@example.com',
            'phone' => '256700000011',
            'address' => 'Kampala',
            'timezone' => $iana,
        ]);

        $this->assertInstanceOf(Business::class, $created);
        $this->assertSame($iana, SharedTime::describe((string) $created->id)['ianaId']);
    }

    public function test_branch_bulk_import_inherits_or_overrides_timezone(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $business = $this->hospitalBusiness();
        SharedTime::assignBusinessTimezone($business, 'Africa/Kampala', 'test business tz');

        $import = new \App\Imports\BranchTemplateImport($business->id);
        $inherited = $import->model([
            'branch_name' => 'Inherited Bulk Branch',
            'email' => 'bulk-tz-inherit-'.Str::random(8).'@example.com',
            'phone' => '256700000012',
            'address' => 'Kampala',
            'timezone' => '',
        ]);

        $this->assertInstanceOf(Branch::class, $inherited);
        $inheritedContext = SharedTime::describe((string) $business->id, (string) $inherited->id);
        $this->assertSame('Africa/Kampala', $inheritedContext['ianaId']);
        $this->assertTrue($inheritedContext['inherited']);

        $options = array_keys(SharedTime::timezoneSelectOptions());
        $override = in_array('Africa/Lagos', $options, true) ? 'Africa/Lagos' : ($options[0] ?? 'Africa/Kampala');
        if ($override === 'Africa/Kampala' && count($options) > 1) {
            $override = $options[1];
        }

        $overridden = $import->model([
            'branch_name' => 'Override Bulk Branch',
            'email' => 'bulk-tz-override-'.Str::random(8).'@example.com',
            'phone' => '256700000013',
            'address' => 'Kampala',
            'timezone' => $override,
        ]);

        $overriddenContext = SharedTime::describe((string) $business->id, (string) $overridden->id);
        $this->assertSame($override, $overriddenContext['ianaId']);
        $this->assertFalse($overriddenContext['inherited']);
    }

    public function test_operational_day_window_uses_resolved_timezone(): void
    {
        $business = $this->hospitalBusiness();
        SharedTime::assignBusinessTimezone($business, 'Africa/Lagos', 'window test');

        $window = SharedTime::dayWindow(null, (string) $business->id);
        $this->assertSame('Africa/Lagos', $window['ianaId']);
        $this->assertNotSame('', $window['start']);
        $this->assertTrue($window['end'] > $window['start']);
        $this->assertSame(SharedTime::businessToday((string) $business->id), SharedTime::describe((string) $business->id)['businessDate']);
    }

    private function hospitalBusiness(): Business
    {
        $business = Business::query()->where('id', '!=', 1)->first();

        if (! $business) {
            $this->markTestSkipped('A non-Kashtre business is required.');
        }

        return $business;
    }

    private function makeBranch(Business $business): Branch
    {
        return Branch::query()->create([
            'uuid' => (string) Str::uuid(),
            'business_id' => $business->id,
            'name' => 'Timezone Test Branch',
            'email' => 'tz-branch-'.Str::random(6).'@example.com',
            'phone' => '256700000099',
            'address' => 'Kampala',
        ]);
    }
}
