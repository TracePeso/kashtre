<?php

namespace App\Providers;


use App\Models\Business;
use App\Models\CallingModuleConfig;
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
        $this->clearStaleLocalViteHotFile();

        // View::composer('*', function ($view) {
        //     $view->with('business', Auth::check() ? Auth::user()->business : null);
        // });

        View::composer('*', function ($view) {
            static $sharedViewData;

            if ($sharedViewData === null) {
                $user = Auth::user();

                $sharedViewData = [
                    'business' => $user?->business,
                    'permissions' => (array) ($user?->permissions ?? []),
                    'callingModuleEnabled' => false,
                    'callingModuleConfig' => null,
                    'userIsACaller' => false,
                    'globalActiveEmergency' => false,
                    'activeEmergencyAlert' => null,
                ];

                if ($user) {
                    $callingModuleConfig = CallingModuleConfig::where('business_id', $user->business_id)
                        ->where('is_active', true)
                        ->first();

                    $sharedViewData['callingModuleConfig'] = $callingModuleConfig;
                    $sharedViewData['callingModuleEnabled'] = (bool) $callingModuleConfig;

                    if ($sharedViewData['callingModuleEnabled']) {
                        $sessionCallerId = session('caller_id');

                        $sharedViewData['userIsACaller'] = (bool) ($sessionCallerId && Caller::where('id', $sessionCallerId)
                            ->where('business_id', $user->business_id)
                            ->where('status', 'active')
                            ->exists());

                        $activeEmergencyAlert = app(EmergencyAlertService::class)
                            ->resolveActiveAlertForBusiness($user->business_id);

                        $sharedViewData['activeEmergencyAlert'] = $activeEmergencyAlert;
                        $sharedViewData['globalActiveEmergency'] = (bool) $activeEmergencyAlert;
                    }
                }
            }

            $view->with($sharedViewData);
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
         

    }

    private function clearStaleLocalViteHotFile(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $hotFile = public_path('hot');

        if (! is_file($hotFile)) {
            return;
        }

        $hotUrl = trim((string) file_get_contents($hotFile));
        $host = parse_url($hotUrl, PHP_URL_HOST);
        $port = parse_url($hotUrl, PHP_URL_PORT);

        if (! is_string($host) || ! is_numeric($port)) {
            return;
        }

        if ($this->isTcpEndpointReachable($host, (int) $port)) {
            return;
        }

        @unlink($hotFile);
    }

    private function isTcpEndpointReachable(string $host, int $port): bool
    {
        $connection = @fsockopen($host, $port, $errorCode, $errorMessage, 0.2);

        if (! is_resource($connection)) {
            return false;
        }

        fclose($connection);

        return true;
    }
}
