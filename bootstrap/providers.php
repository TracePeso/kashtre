<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\ModuleDispatcherServiceProvider::class,
    App\Providers\ClinicalRisIntegrationServiceProvider::class,
    App\Providers\ClinicalInventoryIntegrationServiceProvider::class,
    App\Providers\ClinicalLimsIntegrationServiceProvider::class,
    App\Providers\ClinicalGatewayServiceProvider::class,
    App\Providers\LivewireServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\BroadcastServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\RouteServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\JetstreamServiceProvider::class,
    Lab404\Impersonate\ImpersonateServiceProvider::class,
];
