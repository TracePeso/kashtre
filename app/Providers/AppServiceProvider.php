<?php

namespace App\Providers;


use App\Models\Business;
use App\Models\CallingModuleConfig;
use App\Models\InventoryModuleConfig;
use App\Models\Caller;
use App\Models\Transaction;
use App\Services\EmergencyAlertService;


// Import models and observers
use App\Models\User;
use App\Observers\ModelActivityObserver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use App\Livewire\Items\SimpleItems;
use App\Livewire\Items\CompositeItems;
use App\Livewire\Admins;
use App\Livewire\AuditLogs;
use App\Livewire\Transactions\Transactions;
use App\Livewire\Suppliers\ListSuppliers;
use App\Livewire\Stores\ListStores;
use App\Livewire\ItemUnits\ListItemUnits;
use App\Livewire\Departments\ListDepartments;
use App\Livewire\Groups\ListGroups;
use App\Livewire\Titles\ListTitles;
use App\Livewire\Roles\ListRoles;
use App\Livewire\Sections\ListSections;
use App\Livewire\Rooms\ListRooms;
use App\Livewire\Qualifications\ListQualifications;
use App\Livewire\SubGroups\ListSubGroups;
use App\Livewire\PatientCategory\ListPatientCategories;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // View::composer('*', function ($view) {
        //     $view->with('business', Auth::check() ? Auth::user()->business : null);
        // });

        View::composer('*', function ($view) {
            $user = Auth::user();

            $view->with('business', $user?->business);
            $view->with('permissions', (array) ($user?->permissions ?? []));

            // Calling module flags
            $callingModuleEnabled = false;
            $callingModuleConfig  = null;
            $userIsACaller = false;
            if ($user) {
                $callingModuleConfig  = CallingModuleConfig::where('business_id', $user->business_id)
                    ->where('is_active', true)
                    ->first();
                $callingModuleEnabled = (bool) $callingModuleConfig;

                if ($callingModuleEnabled) {
                    $sessionCallerId = session('caller_id');

                    // User is a caller if they have a session caller_id pointing to an active Caller
                    $userIsACaller = $sessionCallerId && Caller::where('id', $sessionCallerId)
                        ->where('business_id', $user->business_id)
                        ->where('status', 'active')
                        ->exists();
                }
            }
            $view->with('callingModuleEnabled', $callingModuleEnabled);
            $view->with('callingModuleConfig', $callingModuleConfig);
            $view->with('userIsACaller', $userIsACaller);

            $inventoryModuleEnabled = false;
            $inventoryModuleConfig = null;
            if ($user) {
                $inventoryModuleConfig = InventoryModuleConfig::where('business_id', $user->business_id)
                    ->where('is_active', true)
                    ->first();
                $inventoryModuleEnabled = (bool) $inventoryModuleConfig;
            }
            $view->with('inventoryModuleEnabled', $inventoryModuleEnabled);
            $view->with('inventoryModuleConfig', $inventoryModuleConfig);

            $activeEmergencyAlert = ($user && $callingModuleEnabled)
                ? app(EmergencyAlertService::class)->resolveActiveAlertForBusiness($user->business_id)
                : null;
            $globalActiveEmergency = (bool) $activeEmergencyAlert;
            $view->with('globalActiveEmergency', $globalActiveEmergency);
            $view->with('activeEmergencyAlert', $activeEmergencyAlert);
        });

         // Register observers
         User::observe(ModelActivityObserver::class);
         Business::observe(ModelActivityObserver::class);
         Transaction::observe(ModelActivityObserver::class);
         
         // Register Livewire components
         Livewire::component('items.simple-items', SimpleItems::class);
         Livewire::component('items.composite-items', CompositeItems::class);
         Livewire::component('admins', Admins::class);
         Livewire::component('audit-logs', AuditLogs::class);
         Livewire::component('transactions.transactions', Transactions::class);
         Livewire::component('suppliers.list-suppliers', ListSuppliers::class);
         Livewire::component('stores.list-stores', ListStores::class);
         Livewire::component('item-units.list-item-units', ListItemUnits::class);
         Livewire::component('departments.list-departments', ListDepartments::class);
         Livewire::component('groups.list-groups', ListGroups::class);
         Livewire::component('titles.list-titles', ListTitles::class);
         Livewire::component('roles.list-roles', ListRoles::class);
         Livewire::component('sections.list-sections', ListSections::class);
         Livewire::component('rooms.list-rooms', ListRooms::class);
         Livewire::component('qualifications.list-qualifications', ListQualifications::class);
         Livewire::component('sub-groups.list-sub-groups', ListSubGroups::class);
         Livewire::component('patient-category.list-patient-categories', ListPatientCategories::class);
    }
}
