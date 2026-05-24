<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Services\HrDefaultShiftTypeService;
use Illuminate\Database\Seeder;

class HrDefaultShiftTypeSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(HrDefaultShiftTypeService::class);

        Organization::query()
            ->orderBy('id')
            ->get()
            ->each(fn (Organization $organization) => $service->seedMissingDefaults($organization));
    }
}
