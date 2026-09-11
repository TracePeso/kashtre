<?php

namespace Database\Seeders\Time;

use App\Domain\Time\Enums\PolicyPurpose;
use App\Domain\Time\Enums\PolicyScopeType;
use App\Domain\Time\Services\SharedTimeGateway;
use App\Domain\Time\Services\TimeZoneCatalogueService;
use Illuminate\Database\Seeder;

class CoreTimeEngineSeeder extends Seeder
{
    /**
     * Common IANA zones for East Africa + global hubs (not the full TZDB dump).
     *
     * @var list<array{0: string, 1: string, 2: ?string}>
     */
    private array $zones = [
        ['UTC', 'Coordinated Universal Time', 'Etc'],
        ['Africa/Kampala', 'Africa / Kampala', 'Africa'],
        ['Africa/Nairobi', 'Africa / Nairobi', 'Africa'],
        ['Africa/Lagos', 'Africa / Lagos', 'Africa'],
        ['Africa/Johannesburg', 'Africa / Johannesburg', 'Africa'],
        ['Africa/Cairo', 'Africa / Cairo', 'Africa'],
        ['Europe/London', 'Europe / London', 'Europe'],
        ['Europe/Paris', 'Europe / Paris', 'Europe'],
        ['America/New_York', 'America / New York', 'America'],
        ['America/Chicago', 'America / Chicago', 'America'],
        ['America/Los_Angeles', 'America / Los Angeles', 'America'],
        ['Asia/Dubai', 'Asia / Dubai', 'Asia'],
        ['Asia/Kolkata', 'Asia / Kolkata', 'Asia'],
        ['Asia/Singapore', 'Asia / Singapore', 'Asia'],
        ['Australia/Sydney', 'Australia / Sydney', 'Australia'],
        ['Pacific/Auckland', 'Pacific / Auckland', 'Pacific'],
    ];

    public function run(): void
    {
        /** @var TimeZoneCatalogueService $catalogue */
        $catalogue = app(TimeZoneCatalogueService::class);
        $release = $catalogue->tzdbRelease();

        foreach ($this->zones as [$iana, $name, $region]) {
            $row = $catalogue->ensureKnown($iana);
            $row->update([
                'display_name' => $name,
                'region_code' => $region,
                'tzdb_release' => $release,
                'status' => 'ACTIVE',
            ]);
        }

        // Also ensure every PHP-recognized Africa/* zone is catalogued for search.
        foreach (timezone_identifiers_list() as $id) {
            if (str_starts_with($id, 'Africa/')) {
                $catalogue->ensureKnown($id);
            }
        }

        app(SharedTimeGateway::class)->ensurePlatformFallback();

        app(\App\Domain\Time\Services\ClockHealthService::class)->recordCheck(
            gethostname() ?: 'app',
            0,
            'install-seed',
            ['note' => 'Initial healthy seed'],
        );
    }
}
