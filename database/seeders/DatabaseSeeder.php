<?php

namespace Database\Seeders;


use Illuminate\Database\Seeder;
use Database\Seeders\KashtreSeeder;
use Database\Seeders\CurrencyCountrySeeder;
use Database\Seeders\DummyDataSeeder;
use Database\Seeders\TestDataSeeder;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {

        $this->call([
            CurrencyCountrySeeder::class,
            KashtreSeeder::class,
            HrDefaultShiftTypeSeeder::class,
            HrDefaultLeaveTypeSeeder::class,
            HrDefaultPolicySeeder::class,
        ]);
    }
}
