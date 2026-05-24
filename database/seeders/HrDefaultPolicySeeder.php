<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Services\HrDefaultPolicyService;
use Illuminate\Database\Seeder;

class HrDefaultPolicySeeder extends Seeder
{
    public function run(): void
    {
        $service = app(HrDefaultPolicyService::class);

        Organization::query()
            ->orderBy('id')
            ->get()
            ->each(fn (Organization $organization) => $service->seedMissingDefaults($organization));
    }
}
