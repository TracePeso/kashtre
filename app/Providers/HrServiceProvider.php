<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class HrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/hr.php'));
        $this->loadMigrationsFrom(database_path('migrations/hr'));
    }
}
