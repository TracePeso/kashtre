<?php

namespace App\Support\DemoWorkbook;

use App\Models\Branch;
use App\Models\BulkItem;
use App\Models\Business;
use App\Models\ClientSpace;
use App\Models\ContractorProfile;
use App\Models\Country;
use App\Models\Department;
use App\Models\Group;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\PackageItem;
use App\Models\Qualification;
use App\Models\Room;
use App\Models\ServicePoint;
use App\Models\ServicePointSupervisor;
use App\Models\StaffCategory;
use App\Models\SubGroup;
use App\Models\Title;
use App\Models\User;
use App\Support\SharedTime;
use App\Support\SharedUnits;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Symfony\Component\Console\Output\OutputInterface;

final class DemoWorkbookImporter
{
    /** @var array<string, Business> */
    private array $businesses = [];

    /** @var array<string, Branch> */
    private array $branches = [];

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, ServicePoint> */
    private array $servicePoints = [];

    /** @var array<string, Item> */
    private array $items = [];

    /** @var array<int|string, list<int>> */
    private array $userServicePointIds = [];

    /** @var array<int, true> */
    private array $defaultClientSpaces = [];

    public function __construct(
        private readonly string $path,
        private readonly OutputInterface $output,
        private readonly array $onlyCodes = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function import(): array
    {
        $reader = new WorkbookSheetReader($this->path);

        $this->line('Reading organisations and branches…');
        $organisations = $this->filterByEntity($reader->rows('Organizations'));
        $branches = $this->filterByEntity($reader->rows('Branches'));

        foreach ($organisations as $row) {
            $this->importOrganisation($row);
        }
        foreach ($branches as $row) {
            $this->importBranch($row);
        }

        $this->line('Reading validation lists, units, org units…');
        $this->importVocabularies($this->filterByEntity($reader->rows('Validation Lists'), allowAllEntities: true));
        $this->importUnits($reader->rows('Unit Definitions'));
        $this->importOrgUnitDepartments($this->filterByEntity($reader->rows('Org Units')));

        $this->line('Importing users…');
        foreach ($this->filterByEntity($reader->rows('Users')) as $row) {
            $this->importUser($row);
        }

        $this->line('Applying assignments…');
        foreach ($this->filterByEntity($reader->rows('Assignments')) as $row) {
            $this->importAssignment($row);
        }

        $this->line('Importing rooms, client spaces, service points…');
        foreach ($this->filterByEntity($reader->rows('Rooms')) as $row) {
            $this->importRoom($row);
        }
        foreach ($this->filterByEntity($reader->rows('Client Spaces')) as $row) {
            $this->importClientSpace($row);
        }
        foreach ($this->filterByEntity($reader->rows('Service Points')) as $row) {
            $this->importServicePoint($row);
        }
        foreach ($this->filterByEntity($reader->rows('User Service Points')) as $row) {
            $this->importUserServicePoint($row);
        }
        $this->persistUserServicePoints();

        $this->line('Importing items…');
        foreach ($this->filterByEntity($reader->rows('Items')) as $row) {
            $this->importItem($row);
        }

        $this->line('Importing composite components…');
        foreach ($this->filterByEntity($reader->rows('Composite Components')) as $row) {
            $this->importCompositeComponent($row);
        }

        return $this->summaryLines();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function filterByEntity(array $rows, bool $allowAllEntities = false): array
    {
        return array_values(array_filter($rows, function (array $row) use ($allowAllEntities) {
            $code = $this->entityCode($row);
            if ($allowAllEntities && ($row['entity_name'] ?? '') === 'All entities') {
                return false;
            }
            if ($code === null) {
                $code = $this->codeFromName((string) ($row['entity_name'] ?? $row['organization_name'] ?? ''));
            }
            if ($code === null) {
                return false;
            }
            if ($this->onlyCodes === []) {
                return in_array($code, ['MCC', 'LSH', 'CCTH'], true);
            }

            return in_array($code, $this->onlyCodes, true);
        }));
    }

    private function importOrganisation(array $row): void
    {
        $code = $this->entityCode($row) ?? $this->codeFromName((string) ($row['organization_name'] ?? $row['entity_name'] ?? ''));
        $name = trim((string) ($row['organization_name'] ?? $row['entity_name'] ?? ''));
        if ($code === null || $name === '') {
            return;
        }

        $currency = strtoupper(trim((string) ($row['currency'] ?? 'USD'))) ?: 'USD';
        $country = Country::query()
            ->where('currency_code', $currency)
            ->orWhere('iso_code', $currency === 'USD' ? 'US' : $currency)
            ->first();

        $business = Business::query()->firstOrNew(['entity_code' => $code]);
        $business->fill([
            'name' => $name,
            'email' => strtolower($code).'@demo.kashtre.local',
            'phone' => '+000-100-'.str_pad((string) crc32($code) % 10000, 4, '0', STR_PAD_LEFT),
            'address' => 'International Demo City',
            'currency_code' => $currency,
            'country_id' => $country?->id,
            'account_number' => $business->account_number ?: ('KS'.strtoupper($code).substr((string) time(), -6)),
            'require_2fa' => false,
            'send_password_reset' => false,
        ]);
        $business->save();

        try {
            SharedTime::assignBusinessTimezone($business, SharedTime::defaultTimezoneId(), 'Set from demonstration workbook import');
        } catch (\Throwable $e) {
            $this->line('  Timezone skipped for '.$code.': '.$e->getMessage());
        }

        $this->businesses[$code] = $business;
        $this->line('  Organisation '.$name.' ('.$code.') → business #'.$business->id);
    }

    private function importBranch(array $row): void
    {
        $business = $this->business($row);
        if (! $business) {
            return;
        }

        $name = trim((string) ($row['branch_name'] ?? ''));
        $extId = trim((string) ($row['branch_id'] ?? ''));
        if ($name === '') {
            return;
        }

        $branch = Branch::query()->firstOrNew([
            'business_id' => $business->id,
            'name' => $name,
        ]);
        $code = $this->entityCode($row) ?? 'ORG';
        $branch->fill([
            'email' => Str::slug($name, '.').'@'.strtolower($code).'.demo.kashtre.local',
            'phone' => $business->phone,
            'address' => trim((string) ($row['location'] ?? $row['site_name'] ?? $business->address)),
        ]);
        $branch->save();

        if ($extId !== '') {
            $this->branches[$extId] = $branch;
        }
        $this->branches[$this->entityCode($row).'|'.$name] = $branch;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importVocabularies(array $rows): void
    {
        foreach ($rows as $row) {
            $business = $this->business($row);
            if (! $business) {
                continue;
            }
            $list = strtolower(trim((string) ($row['list_name'] ?? '')));
            $value = trim((string) ($row['allowed_value'] ?? ''));
            if ($value === '') {
                continue;
            }
            if (str_contains($list, 'qualification')) {
                $this->firstNamed(Qualification::class, $business->id, $value);
            } elseif (str_contains($list, 'department')) {
                $this->firstNamed(Department::class, $business->id, $value);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importUnits(array $rows): void
    {
        foreach ($rows as $row) {
            $scope = strtolower(trim((string) ($row['scope'] ?? '')));
            $name = trim((string) ($row['unit_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $targets = [];
            if ($scope === 'platform' || ($row['entity_name'] ?? '') === 'All entities') {
                $targets = $this->businesses;
            } else {
                $business = $this->business($row);
                if ($business) {
                    $targets = [$business];
                }
            }

            foreach ($targets as $business) {
                $this->ensureUnit((int) $business->id, $name, (string) ($row['unit_symbol'] ?? $name));
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importOrgUnitDepartments(array $rows): void
    {
        foreach ($rows as $row) {
            $business = $this->business($row);
            if (! $business) {
                continue;
            }
            foreach (['department', 'org_unit_name'] as $field) {
                $name = trim((string) ($row[$field] ?? ''));
                if ($name !== '') {
                    $this->firstNamed(Department::class, $business->id, $name);
                }
            }
        }
    }

    private function importUser(array $row): void
    {
        $business = $this->business($row);
        if (! $business) {
            return;
        }

        $email = strtolower(trim((string) ($row['email'] ?? '')));
        if ($email === '') {
            return;
        }

        $extId = trim((string) ($row['user_id'] ?? ''));
        $name = trim((string) ($row['display_name'] ?? ''));
        if ($name === '') {
            $name = trim(implode(' ', array_filter([
                (string) ($row['given_name'] ?? ''),
                (string) ($row['middle_name'] ?? ''),
                (string) ($row['family_name'] ?? ''),
            ])));
        }

        $engagement = strtolower(trim((string) ($row['engagement_type'] ?? 'employee')));
        $isContractor = str_contains($engagement, 'contractor');
        $mainBranch = $this->mainBranch($business);

        $qualificationId = $this->firstQualificationId($business, (string) ($row['qualifications'] ?? ''));
        $profession = trim((string) ($row['profession'] ?? ''));
        $titleId = $profession !== '' ? $this->firstNamed(Title::class, $business->id, $profession)->id : null;
        $category = $this->firstNamed(StaffCategory::class, $business->id, $isContractor ? 'Contractor' : 'Employee');

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->fill([
            'name' => $name !== '' ? $name : $email,
            'password' => Hash::make(Business::IMPORTED_USER_DEFAULT_PASSWORD),
            'status' => strtolower((string) ($row['status'] ?? 'active')) === 'inactive' ? 'inactive' : 'active',
            'business_id' => $business->id,
            'branch_id' => $user->branch_id ?: $mainBranch?->id,
            'phone' => $this->nullableString($row['phone'] ?? null),
            'nin' => $this->nullableString($row['id_number'] ?? null),
            'gender' => $this->gender($row['sex'] ?? null),
            'birth_date' => $this->excelDate($row['date_of_birth'] ?? null),
            'hire_date' => $this->excelDate($row['start_date'] ?? null),
            'employment_type' => $isContractor ? 'contractor' : 'employee',
            'employee_code' => $this->nullableString($row['personnel_no'] ?? $extId),
            'qualification_id' => $qualificationId,
            'title_id' => $titleId,
            'staff_category_id' => $category->id,
            'permissions' => DemoWorkbookPermissions::forImportedUser($isContractor),
            'allowed_branches' => Branch::query()->where('business_id', $business->id)->pluck('id')->all(),
            'service_points' => $user->service_points ?: [],
            'email_verified_at' => $user->email_verified_at ?: now(),
        ]);
        $user->save();

        if ($isContractor) {
            ContractorProfile::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'business_id' => $business->id,
                    'bank_name' => 'Demo Bank',
                    'account_name' => $user->name,
                    'account_number' => (string) ($row['bank_account_token'] ?? ('DEMO-BANK-'.$extId)),
                ],
            );
        }

        if ($extId !== '') {
            $this->users[$extId] = $user;
        }
        $this->users[$email] = $user;
    }

    private function importAssignment(array $row): void
    {
        $user = $this->userFromRow($row);
        $business = $this->business($row);
        if (! $user || ! $business || ! $this->isYes($row['is_primary'] ?? 'yes')) {
            return;
        }

        $title = trim((string) ($row['position_title'] ?? ''));
        $department = trim((string) ($row['department'] ?? $row['organizational_home'] ?? ''));
        $branch = $this->branchFromClientSpace((string) ($row['client_space'] ?? ''), $business)
            ?? $this->mainBranch($business);

        $user->forceFill([
            'title_id' => $title !== '' ? $this->firstNamed(Title::class, $business->id, $title)->id : $user->title_id,
            'department_id' => $department !== '' ? $this->firstNamed(Department::class, $business->id, $department)->id : $user->department_id,
            'branch_id' => $branch?->id ?: $user->branch_id,
            'permissions' => DemoWorkbookPermissions::forImportedUser(
                in_array('Contractor', $user->permissions ?? [], true)
                || $user->employment_type === 'contractor'
            ),
        ])->save();
    }

    private function importRoom(array $row): void
    {
        $business = $this->business($row);
        $branch = $this->branchFromRow($row, $business);
        $name = trim((string) ($row['room_name'] ?? ''));
        if (! $business || $name === '') {
            return;
        }

        Room::query()->firstOrCreate(
            [
                'business_id' => $business->id,
                'branch_id' => $branch?->id,
                'name' => $name,
            ],
            ['description' => $this->nullableString($row['building'] ?? null)],
        );
    }

    private function importClientSpace(array $row): void
    {
        $business = $this->business($row);
        $branch = $this->branchFromRow($row, $business);
        $name = trim((string) ($row['client_space_name'] ?? ''));
        if (! $business || $name === '') {
            return;
        }

        $makeDefault = ! isset($this->defaultClientSpaces[$business->id]);
        $space = ClientSpace::query()->firstOrNew([
            'business_id' => $business->id,
            'branch_id' => $branch?->id,
            'name' => $name,
        ]);
        $space->fill([
            'description' => trim((string) ($row['client_space_type'] ?? '')).' — '.trim((string) ($row['physical_location'] ?? '')),
            'is_default' => $space->is_default ?: $makeDefault,
        ]);
        $space->save();
        $this->defaultClientSpaces[$business->id] = true;
    }

    private function importServicePoint(array $row): void
    {
        $business = $this->business($row);
        $name = trim((string) ($row['service_point_name'] ?? ''));
        $extId = trim((string) ($row['service_point_id'] ?? ''));
        if (! $business || $name === '') {
            return;
        }

        $branch = $this->mainBranch($business);
        $point = ServicePoint::query()->firstOrNew([
            'business_id' => $business->id,
            'name' => $name,
        ]);
        $point->fill([
            'description' => $this->nullableString($row['definition'] ?? $row['service_point_type'] ?? null),
            'branch_id' => $point->branch_id ?: $branch?->id,
        ]);
        $point->save();

        if ($extId !== '') {
            $this->servicePoints[$extId] = $point;
        }
        $this->servicePoints[$this->entityCode($row).'|'.$name] = $point;
    }

    private function importUserServicePoint(array $row): void
    {
        $user = $this->userFromRow($row);
        $point = $this->servicePointFromRow($row);
        if (! $user || ! $point) {
            return;
        }

        $key = (string) $user->id;
        $this->userServicePointIds[$key] ??= [];
        $this->userServicePointIds[$key][] = $point->id;

        if ($this->isYes($row['can_supervise'] ?? 'no')) {
            ServicePointSupervisor::query()->firstOrCreate([
                'service_point_id' => $point->id,
                'supervisor_user_id' => $user->id,
                'business_id' => $user->business_id,
            ]);
        }
    }

    private function persistUserServicePoints(): void
    {
        foreach ($this->userServicePointIds as $userId => $pointIds) {
            $user = User::query()->find($userId);
            if (! $user) {
                continue;
            }
            $user->forceFill([
                'service_points' => array_values(array_unique(array_map('intval', $pointIds))),
            ])->save();
        }
    }

    private function importItem(array $row): void
    {
        $business = $this->business($row);
        $name = trim((string) ($row['item_name'] ?? ''));
        if (! $business || $name === '') {
            return;
        }

        $code = trim((string) ($row['item_code'] ?? $row['item_id'] ?? ''));
        if ($code === '') {
            $code = strtoupper($this->entityCode($row) ?? 'ITM').'-'.substr(sha1($name), 0, 10);
        }

        $type = $this->itemType($row['item_type'] ?? 'service');
        $unit = $this->ensureUnit((int) $business->id, (string) ($row['unit'] ?? $row['base_unit'] ?? 'Each'));
        $group = $this->namedOrNull(Group::class, $business->id, (string) ($row['group'] ?? ''));
        $subgroup = $this->namedOrNull(SubGroup::class, $business->id, (string) ($row['subgroup'] ?? ''));
        $department = $this->namedOrNull(Department::class, $business->id, (string) ($row['department'] ?? ''));
        $contractor = $this->contractorProfileFromRow($row);

        $hospitalShare = $this->toPercent($row['entity_share_pct'] ?? null, 100);
        if (in_array($type, ['package', 'bulk'], true)) {
            $hospitalShare = 100;
            $contractor = null;
        }

        $item = Item::withTrashed()->where('code', $code)->first() ?: new Item(['code' => $code]);
        if ($item->trashed()) {
            $item->restore();
        }

        $item->fill([
            'name' => Str::limit($name, 250, ''),
            'code' => $code,
            'type' => $type,
            'description' => $this->nullableString($row['description'] ?? null),
            'other_names' => Str::limit((string) ($row['other_names'] ?? ''), 250, '') ?: null,
            'group_id' => $group?->id,
            'subgroup_id' => $subgroup?->id,
            'department_id' => $department?->id,
            'uom_id' => $unit?->id,
            'default_price' => $this->money($row['unit_price'] ?? 0),
            'purchase_price' => $this->money($row['cost_price'] ?? 0),
            'hospital_share' => $hospitalShare,
            'contractor_account_id' => $contractor?->id,
            'business_id' => $business->id,
            'suom_per_ouom' => is_numeric($row['units_per_pack'] ?? null) ? (float) $row['units_per_pack'] : null,
        ]);
        $item->save();

        $extId = trim((string) ($row['item_id'] ?? ''));
        if ($extId !== '') {
            $this->items[$extId] = $item;
        }
        $this->items[$code] = $item;
    }

    private function importCompositeComponent(array $row): void
    {
        $business = $this->business($row);
        $parent = $this->items[trim((string) ($row['composite_item_id'] ?? ''))] ?? null;
        $child = $this->items[trim((string) ($row['component_item_id'] ?? ''))] ?? null;
        if (! $business || ! $parent || ! $child) {
            return;
        }

        $qty = max(1, (int) ($row['maximum_allowable_quantity'] ?? 1));
        $type = $this->itemType($row['composite_type'] ?? $parent->type);

        if ($type === 'bulk') {
            if ($parent->type !== 'bulk') {
                $parent->forceFill(['type' => 'bulk', 'hospital_share' => 100, 'contractor_account_id' => null])->save();
            }
            BulkItem::query()->firstOrCreate(
                [
                    'bulk_item_id' => $parent->id,
                    'included_item_id' => $child->id,
                    'business_id' => $business->id,
                ],
                ['fixed_quantity' => $qty],
            );

            return;
        }

        if ($parent->type !== 'package') {
            $parent->forceFill(['type' => 'package', 'hospital_share' => 100, 'contractor_account_id' => null])->save();
        }
        PackageItem::query()->firstOrCreate(
            [
                'package_item_id' => $parent->id,
                'included_item_id' => $child->id,
                'business_id' => $business->id,
            ],
            ['max_quantity' => $qty],
        );
    }

    private function ensureUnit(int $businessId, string $name, ?string $symbol = null): ?ItemUnit
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $existing = ItemUnit::query()
            ->where('business_id', $businessId)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();
        if ($existing) {
            return $existing;
        }

        if (SharedUnits::enabled()) {
            SharedUnits::itemUnitsForBusiness($businessId);
            $existing = ItemUnit::query()
                ->where('business_id', $businessId)
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->first();
            if ($existing) {
                return $existing;
            }

            try {
                return SharedUnits::addLocalPackagingUnit($businessId, $name, $symbol ?: $name);
            } catch (\Throwable) {
                // Fall through to a plain item unit.
            }
        }

        return ItemUnit::query()->firstOrCreate(
            ['business_id' => $businessId, 'name' => $name],
            ['description' => 'Imported from demonstration workbook'],
        );
    }

    /**
     * @param  class-string  $class
     */
    private function firstNamed(string $class, int $businessId, string $name): object
    {
        $name = trim($name);

        return $class::query()->firstOrCreate(
            ['business_id' => $businessId, 'name' => $name],
            ['description' => 'Imported from demonstration workbook'],
        );
    }

    /**
     * @param  class-string  $class
     */
    private function namedOrNull(string $class, int $businessId, string $name): ?object
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return $this->firstNamed($class, $businessId, $name);
    }

    private function firstQualificationId(Business $business, string $raw): ?int
    {
        $tokens = array_values(array_filter(array_map('trim', explode(',', $raw))));
        $firstId = null;
        foreach ($tokens as $token) {
            $qualification = $this->firstNamed(Qualification::class, $business->id, $token);
            $firstId ??= $qualification->id;
        }

        return $firstId;
    }

    private function business(array $row): ?Business
    {
        $code = $this->entityCode($row) ?? $this->codeFromName((string) ($row['entity_name'] ?? $row['organization_name'] ?? ''));

        return $code ? ($this->businesses[$code] ?? null) : null;
    }

    private function entityCode(array $row): ?string
    {
        $entityId = strtoupper(trim((string) ($row['entity_id'] ?? '')));
        if (str_starts_with($entityId, 'ENT-')) {
            return substr($entityId, 4);
        }
        if (in_array($entityId, ['MCC', 'LSH', 'CCTH'], true)) {
            return $entityId;
        }

        return $this->codeFromName((string) ($row['entity_name'] ?? $row['organization_name'] ?? ''));
    }

    private function codeFromName(string $name): ?string
    {
        return match (trim($name)) {
            'Metropolitan Care Clinic' => 'MCC',
            'London Surgery Hospital' => 'LSH',
            'Central City Teaching Hospital' => 'CCTH',
            default => null,
        };
    }

    private function mainBranch(Business $business): ?Branch
    {
        foreach ($this->branches as $branch) {
            if ((int) $branch->business_id === (int) $business->id) {
                return $branch;
            }
        }

        return Branch::query()->where('business_id', $business->id)->orderBy('id')->first();
    }

    private function branchFromRow(array $row, ?Business $business): ?Branch
    {
        $extId = trim((string) ($row['branch_id'] ?? ''));
        if ($extId !== '' && isset($this->branches[$extId])) {
            return $this->branches[$extId];
        }
        $name = trim((string) ($row['branch_name'] ?? ''));
        $code = $this->entityCode($row);
        if ($code && $name !== '' && isset($this->branches[$code.'|'.$name])) {
            return $this->branches[$code.'|'.$name];
        }

        return $business ? $this->mainBranch($business) : null;
    }

    private function branchFromClientSpace(string $clientSpace, Business $business): ?Branch
    {
        if ($clientSpace === '') {
            return null;
        }
        $parts = array_map('trim', explode('/', $clientSpace));
        $branchName = $parts[0] ?? '';
        if ($branchName === '') {
            return null;
        }
        $code = $this->entityCode(['entity_name' => $business->name, 'entity_id' => 'ENT-'.$this->codeFromName($business->name)]);

        return $this->branches[($code ?: '').'|'.$branchName] ?? Branch::query()
            ->where('business_id', $business->id)
            ->where('name', $branchName)
            ->first();
    }

    private function userFromRow(array $row): ?User
    {
        $extId = trim((string) ($row['user_id'] ?? ''));
        if ($extId !== '' && isset($this->users[$extId])) {
            return $this->users[$extId];
        }

        return null;
    }

    private function servicePointFromRow(array $row): ?ServicePoint
    {
        $extId = trim((string) ($row['service_point_id'] ?? ''));
        if ($extId !== '' && isset($this->servicePoints[$extId])) {
            return $this->servicePoints[$extId];
        }
        $business = $this->business($row);
        $name = trim((string) ($row['service_point_name'] ?? ''));
        $code = $this->entityCode($row);
        if ($code && $name !== '' && isset($this->servicePoints[$code.'|'.$name])) {
            return $this->servicePoints[$code.'|'.$name];
        }
        if ($business && $name !== '') {
            return ServicePoint::query()->where('business_id', $business->id)->where('name', $name)->first();
        }

        return null;
    }

    private function contractorProfileFromRow(array $row): ?ContractorProfile
    {
        $extId = trim((string) ($row['contractor_user_id'] ?? ''));
        $user = $extId !== '' ? ($this->users[$extId] ?? null) : null;
        if (! $user) {
            return null;
        }

        return ContractorProfile::query()->where('user_id', $user->id)->first();
    }

    private function itemType(mixed $value): string
    {
        return match (strtolower(trim((string) $value))) {
            'good', 'goods' => 'good',
            'package' => 'package',
            'bulk', 'bulk item' => 'bulk',
            default => 'service',
        };
    }

    private function toPercent(mixed $value, int $default = 100): int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $number = (float) $value;
        if ($number <= 1) {
            return (int) round($number * 100);
        }

        return (int) min(100, max(0, $number));
    }

    private function money(mixed $value): string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return '0';
        }

        return (string) round((float) $value, 2);
    }

    private function excelDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_numeric($value) && (float) $value > 20000) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        $stamp = strtotime((string) $value);

        return $stamp ? date('Y-m-d', $stamp) : null;
    }

    private function gender(mixed $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'male', 'm' => 'male',
            'female', 'f' => 'female',
            'other' => 'other',
            default => null,
        };
    }

    private function isYes(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['yes', 'y', '1', 'true'], true);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return list<string>
     */
    private function summaryLines(): array
    {
        $lines = [];
        foreach ($this->businesses as $code => $business) {
            $lines[] = sprintf(
                '%s (%s): users %d, branches %d, rooms %d, service points %d, items %d',
                $business->name,
                $code,
                User::query()->where('business_id', $business->id)->count(),
                Branch::query()->where('business_id', $business->id)->count(),
                Room::query()->where('business_id', $business->id)->count(),
                ServicePoint::query()->where('business_id', $business->id)->count(),
                Item::query()->where('business_id', $business->id)->count(),
            );
        }

        return $lines;
    }

    private function line(string $message): void
    {
        $this->output->writeln($message);
    }
}
