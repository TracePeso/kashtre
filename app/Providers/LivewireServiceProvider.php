<?php

namespace App\Providers;

use App\Livewire\Admins;
use App\Livewire\AuditLogs;
use App\Livewire\Departments\ListDepartments;
use App\Livewire\Groups\ListGroups;
use App\Livewire\ItemImportanceCategories\ListItemImportanceCategories;
use App\Livewire\ItemUnits\ListItemUnits;
use App\Livewire\Items\CompositeItems;
use App\Livewire\Items\SimpleItems;
use App\Livewire\PatientCategory\ListPatientCategories;
use App\Livewire\Qualifications\ListQualifications;
use App\Livewire\Roles\ListRoles;
use App\Livewire\Rooms\ListRooms;
use App\Livewire\Sections\ListSections;
use App\Livewire\Stores\ListStores;
use App\Livewire\SubGroups\ListSubGroups;
use App\Livewire\Suppliers\ListSuppliers;
use App\Livewire\Titles\ListTitles;
use App\Livewire\Transactions\Transactions;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class LivewireServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, class-string>
     */
    protected array $components = [
        'items.simple-items' => SimpleItems::class,
        'items.composite-items' => CompositeItems::class,
        'admins' => Admins::class,
        'audit-logs' => AuditLogs::class,
        'transactions.transactions' => Transactions::class,
        'suppliers.list-suppliers' => ListSuppliers::class,
        'stores.list-stores' => ListStores::class,
        'item-importance-categories.list-item-importance-categories' => ListItemImportanceCategories::class,
        'item-units.list-item-units' => ListItemUnits::class,
        'departments.list-departments' => ListDepartments::class,
        'groups.list-groups' => ListGroups::class,
        'titles.list-titles' => ListTitles::class,
        'roles.list-roles' => ListRoles::class,
        'sections.list-sections' => ListSections::class,
        'rooms.list-rooms' => ListRooms::class,
        'qualifications.list-qualifications' => ListQualifications::class,
        'sub-groups.list-sub-groups' => ListSubGroups::class,
        'patient-category.list-patient-categories' => ListPatientCategories::class,
    ];

    /**
     * Legacy aliases from old lowercase/camelCase Livewire folder names.
     *
     * @var array<string, class-string>
     */
    protected array $legacyAliases = [
        'itemImportanceCategories.list-item-importance-categories' => ListItemImportanceCategories::class,
        'itemUnits.list-item-units' => ListItemUnits::class,
        'suppliers.list-suppliers' => ListSuppliers::class,
        'stores.list-stores' => ListStores::class,
        'departments.list-departments' => ListDepartments::class,
        'groups.list-groups' => ListGroups::class,
        'titles.list-titles' => ListTitles::class,
        'roles.list-roles' => ListRoles::class,
        'sections.list-sections' => ListSections::class,
        'rooms.list-rooms' => ListRooms::class,
        'qualifications.list-qualifications' => ListQualifications::class,
        'subGroups.list-sub-groups' => ListSubGroups::class,
        'patientCategory.list-patient-categories' => ListPatientCategories::class,
    ];

    public function boot(): void
    {
        foreach (array_merge($this->components, $this->legacyAliases) as $name => $class) {
            Livewire::component($name, $class);
        }

        Livewire::resolveMissingComponent(function (string $name) {
            $aliases = array_merge($this->components, $this->legacyAliases);

            return $aliases[$name] ?? null;
        });
    }
}
