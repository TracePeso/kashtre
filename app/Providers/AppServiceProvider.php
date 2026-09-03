<?php

namespace App\Providers;


use App\Models\Business;
use App\Models\InventoryModuleConfig;
use App\Models\KashtreCashTraySetting;
use App\Support\BusinessBranding;
use App\Support\DocumentViewData;
use App\Support\InventoryBusinessContext;
use App\Models\Transaction;


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
use Illuminate\Support\Facades\Cache;
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
            $key = ($user?->id ?? 0).'|'.(string) session(InventoryBusinessContext::SESSION_KEY, '');

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
        $inventoryModuleEnabled = false;
        $inventoryModuleConfig = null;
        $inventoryAdminContextBusiness = null;

        if ($user) {
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

        }

        $hrModuleUrl     = rtrim(config('services.hr_module.url', ''), '/');
        $hrModuleEnabled = $hrModuleUrl !== '' && config('services.hr_module.api_key') !== null;

        $hrNavigation = [];
        if ($hrModuleEnabled && $user) {
            // Normally just a cache read — the scheduled `hr:refresh-navigation`
            // command (routes/console.php, every 4 minutes) keeps this warm from
            // the HR module's live navigation endpoint. But that requires
            // Laravel's scheduler to actually be running (schedule:work locally,
            // or a cron hitting schedule:run in production), so on a cold cache
            // we self-heal by fetching live, once, and re-warming the cache —
            // no hardcoded copy of the HR module's nav list to keep in sync.
            $hrNavigation = Cache::get('hr.navigation');
            if ($hrNavigation === null) {
                try {
                    $hrNavigation = app(\App\Services\HrModuleApiClient::class)->navigation();
                    Cache::put('hr.navigation', $hrNavigation, 300);
                } catch (\Throwable $e) {
                    $hrNavigation = [];
                }
            }
        }

        return [
            'business' => $user?->business,
            'businessBranding' => BusinessBranding::for($user?->business),
            'permissions' => (array) ($user?->permissions ?? []),
            'callingModuleEnabled' => false,
            'callingModuleConfig' => null,
            'userIsACaller' => false,
            'inventoryModuleEnabled' => $inventoryModuleEnabled,
            'inventoryModuleConfig' => $inventoryModuleConfig,
            'inventoryAdminContextBusiness' => $inventoryAdminContextBusiness,
            'globalActiveEmergency' => false,
            'activeEmergencyAlert' => null,
            'cashTraySettings' => KashtreCashTraySetting::resolved(),
            'hrModuleUrl' => $hrModuleUrl,
            'hrModuleEnabled' => $hrModuleEnabled,
            'hrNavigation' => $hrNavigation,
        ];
    }
}
