<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Services\HrDefaultLeaveTypeService;
use Illuminate\Database\Seeder;

class HrDefaultLeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(HrDefaultLeaveTypeService::class);

        Organization::query()
            ->orderBy('id')
            ->get()
            ->each(fn (Organization $organization) => $service->seedMissingDefaults($organization));
    }
}
