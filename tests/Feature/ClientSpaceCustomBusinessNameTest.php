<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\ClientSpace;
use App\Services\KashApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientSpaceCustomBusinessNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_kash_api_exports_custom_business_name_as_the_client_space_name(): void
    {
        $this->createSystemBusiness();
        $business = $this->createBusiness('Acme Health');
        $branch = $this->createBranch($business, 'Acme Main');

        $customNamedSpace = ClientSpace::create([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'name' => 'Client Space Alpha',
            'custom_business_name' => 'Acme Diagnostic Hub',
            'description' => 'Primary diagnostics wing',
        ]);

        $fallbackSpace = ClientSpace::create([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'name' => 'Client Space Beta',
            'custom_business_name' => null,
            'description' => 'Secondary services wing',
        ]);

        $payload = app(KashApiService::class)->getClientSpaces(['business_id' => $business->id]);
        $clientSpaces = collect($payload['data'] ?? [])->keyBy('uuid');

        $this->assertSame('Acme Diagnostic Hub', $clientSpaces[$customNamedSpace->uuid]['name']);
        $this->assertSame('Client Space Alpha', $clientSpaces[$customNamedSpace->uuid]['source_name']);
        $this->assertSame('Acme Diagnostic Hub', $clientSpaces[$customNamedSpace->uuid]['custom_business_name']);
        $this->assertSame('Client Space Beta', $clientSpaces[$fallbackSpace->uuid]['name']);
        $this->assertSame('Client Space Beta', $clientSpaces[$fallbackSpace->uuid]['source_name']);
        $this->assertNull($clientSpaces[$fallbackSpace->uuid]['custom_business_name']);
    }

    public function test_hr_client_space_endpoint_returns_custom_business_name_and_original_source_name(): void
    {
        config()->set('services.hr_module.api_key', 'test-hr-key');

        $this->createSystemBusiness();
        $business = $this->createBusiness('Beacon Care');
        $branch = $this->createBranch($business, 'Beacon Branch');
        $clientSpace = ClientSpace::create([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'name' => 'Legacy Client Space',
            'custom_business_name' => 'Beacon Specialty Center',
            'description' => 'Specialty care unit',
        ]);

        $response = $this->withHeader('X-HR-API-Key', 'test-hr-key')
            ->getJson('/api/hr/client-spaces?business_id='.$business->id);

        $response
            ->assertOk()
            ->assertJsonPath('0.uuid', $clientSpace->uuid)
            ->assertJsonPath('0.name', 'Beacon Specialty Center')
            ->assertJsonPath('0.source_name', 'Legacy Client Space')
            ->assertJsonPath('0.custom_business_name', 'Beacon Specialty Center')
            ->assertJsonPath('0.branch.name', 'Beacon Branch');
    }

    private function createSystemBusiness(): Business
    {
        return $this->createBusiness('Kashtre System');
    }

    private function createBusiness(string $name): Business
    {
        $slug = str()->slug($name).'-'.str()->lower((string) str()->uuid());

        return Business::create([
            'name' => $name,
            'email' => $slug.'@example.com',
            'phone' => '0700000000',
            'address' => $name.' Address',
            'account_number' => 'ACC-'.substr(strtoupper(str_replace('-', '', (string) str()->uuid())), 0, 10),
            'currency_code' => 'UGX',
        ]);
    }

    private function createBranch(Business $business, string $name): Branch
    {
        $slug = str()->slug($name).'-'.str()->lower((string) str()->uuid());

        return Branch::create([
            'business_id' => $business->id,
            'name' => $name,
            'email' => $slug.'@example.com',
            'phone' => '0700000001',
            'address' => $name.' Address',
        ]);
    }
}
