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
    }
}
