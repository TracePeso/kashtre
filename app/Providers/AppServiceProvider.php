<?php

namespace App\Providers;


use App\Models\Business;
use App\Models\CallingModuleConfig;
use App\Models\InventoryModuleConfig;
use App\Models\KashtreCashTraySetting;
use App\Support\BusinessBranding;
use App\Support\DocumentViewData;
use App\Support\InventoryBusinessContext;
use App\Models\Caller;
use App\Models\Transaction;
use App\Services\EmergencyAlertService;


// Import models and observers
use App\Models\User;
use App\Models\InventoryOrder;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventorySupplierQuotation;
use App\Models\GoodsReceivedNote;
use App\Models\StockTransfer;
use App\Observers\ClientClinicalEncounterObserver;
use App\Observers\ClientInventoryVisitObserver;
use App\Observers\ModelActivityObserver;
use App\Observers\UserHrSyncObserver;
use App\Models\Client;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
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
        $this->app->singleton(\App\Services\Inventory\InventoryStockAnalyticsService::class);
        $this->app->singleton(\App\Services\Inventory\InventoryStockAgingService::class);
        $this->app->singleton(\App\Services\Inventory\InventoryStockCountShrinkageService::class);
        $this->app->singleton(\App\Services\FinancialYearService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->components->addSecurityScheme(
                    'bearer',
                    SecurityScheme::http('bearer')
                );
            });

        // View::composer('*', function ($view) {
        //     $view->with('business', Auth::check() ? Auth::user()->business : null);
        // });

        // Filament/Livewire pages can render hundreds of nested views. Resolve
        // shared layout data once per request so we do not re-query on every view.
        View::composer('*', function ($view) {
            static $shared = null;
            static $sharedKey = null;

            $user = Auth::user();
            $key = ($user?->id ?? 0).'|'.(string) session(InventoryBusinessContext::SESSION_KEY, '').'|'.(string) session('caller_id', '');

            if ($shared === null || $sharedKey !== $key) {
                $sharedKey = $key;
                $shared = $this->sharedLayoutViewData($user);
            }

            $view->with($shared);
        });

        View::composer(\App\Support\DocumentViewData::documentViewNames(), function ($view): void {
            $view->with(\App\Support\DocumentViewData::merge($view->getData()));
        });

         // Register observers
         User::observe(ModelActivityObserver::class);
         User::observe(UserHrSyncObserver::class);
         Client::observe(ClientClinicalEncounterObserver::class);
         Client::observe(ClientInventoryVisitObserver::class);
         Business::observe(ModelActivityObserver::class);
         Transaction::observe(ModelActivityObserver::class);
         InventoryOrder::observe(ModelActivityObserver::class);
         InventoryPurchaseOrder::observe(ModelActivityObserver::class);
         InventorySupplierQuotation::observe(ModelActivityObserver::class);
         GoodsReceivedNote::observe(ModelActivityObserver::class);
         StockTransfer::observe(ModelActivityObserver::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedLayoutViewData(?User $user): array
    {
        $callingModuleEnabled = false;
        $callingModuleConfig = null;
        $userIsACaller = false;
        $inventoryModuleEnabled = false;
        $inventoryModuleConfig = null;
        $inventoryAdminContextBusiness = null;
        $activeEmergencyAlert = null;

        if ($user) {
            $callingModuleConfig = CallingModuleConfig::query()
                ->where('business_id', $user->business_id)
                ->where('is_active', true)
                ->first();
            $callingModuleEnabled = (bool) $callingModuleConfig;

            if ($callingModuleEnabled) {
                $sessionCallerId = session('caller_id');
                $userIsACaller = $sessionCallerId && Caller::query()
                    ->where('id', $sessionCallerId)
                    ->where('business_id', $user->business_id)
                    ->where('status', 'active')
                    ->exists();
            }

            $inventoryBusinessId = InventoryBusinessContext::isKashtreAdmin() && InventoryBusinessContext::hasContext()
                ? InventoryBusinessContext::effectiveBusinessId()
                : (int) $user->business_id;

            $inventoryModuleConfig = InventoryModuleConfig::query()
                ->where('business_id', $inventoryBusinessId)
                ->where('is_active', true)
                ->first();
            $inventoryModuleEnabled = (bool) $inventoryModuleConfig;

            if (InventoryBusinessContext::isAdminBrowsing()) {
                $inventoryAdminContextBusiness = InventoryBusinessContext::contextBusiness();
            }

            if ($callingModuleEnabled) {
                $activeEmergencyAlert = app(EmergencyAlertService::class)
                    ->resolveActiveAlertForBusiness((int) $user->business_id);
            }
        }

        $hrModuleUrl     = rtrim(config('services.hr_module.url', ''), '/');
        $hrModuleEnabled = $hrModuleUrl !== '' && config('services.hr_module.api_key') !== null;

        return [
            'business' => $user?->business,
            'businessBranding' => BusinessBranding::for($user?->business),
            'permissions' => (array) ($user?->permissions ?? []),
            'callingModuleEnabled' => $callingModuleEnabled,
            'callingModuleConfig' => $callingModuleConfig,
            'userIsACaller' => $userIsACaller,
            'inventoryModuleEnabled' => $inventoryModuleEnabled,
            'inventoryModuleConfig' => $inventoryModuleConfig,
            'inventoryAdminContextBusiness' => $inventoryAdminContextBusiness,
            'globalActiveEmergency' => (bool) $activeEmergencyAlert,
            'activeEmergencyAlert' => $activeEmergencyAlert,
            'cashTraySettings' => KashtreCashTraySetting::resolved(),
            'hrModuleUrl' => $hrModuleUrl,
            'hrModuleEnabled' => $hrModuleEnabled,
        ];
    }
}
