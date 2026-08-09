<?php

namespace App\Providers;

use App\Contracts\ModuleDispatcher;
use App\Services\Clinical\Dispatch\HttpModuleDispatcher;
use App\Services\Clinical\Dispatch\LocalFactReceiverRegistry;
use App\Services\Clinical\Dispatch\LocalModuleDispatcher;
use Illuminate\Support\ServiceProvider;

/**
 * Clinical Module Chunk 0. Binds ModuleDispatcher to the driver named by
 * services.dispatch.driver (env DISPATCH_DRIVER, default 'local'). Other
 * modules register their local fact receivers against the
 * LocalFactReceiverRegistry singleton from their own provider's boot() —
 * see e.g. Chunk 3's Imaging receiver registration.
 */
class ModuleDispatcherServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LocalFactReceiverRegistry::class);

        $this->app->singleton(ModuleDispatcher::class, function ($app) {
            return config('services.dispatch.driver', 'local') === 'http'
                ? $app->make(HttpModuleDispatcher::class)
                : $app->make(LocalModuleDispatcher::class);
        });
    }
}
