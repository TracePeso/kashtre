<?php

namespace App\Providers;

use App\Actions\Jetstream\DeleteUser;
use App\Livewire\Profile\PrimaryTwoFactorMethodForm;
use App\Livewire\Profile\SecurityQuestionsForm;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Jetstream;
use Livewire\Livewire;

class JetstreamServiceProvider extends ServiceProvider
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
        $this->configurePermissions();

        Jetstream::deleteUsersUsing(DeleteUser::class);

        Livewire::component('profile.security-questions-form', SecurityQuestionsForm::class);
        Livewire::component('profile.primary-two-factor-method-form', PrimaryTwoFactorMethodForm::class);
    }

    /**
     * Configure the permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {
        Jetstream::defaultApiTokenPermissions(['read']);

        Jetstream::permissions([
            'create',
            'read',
            'update',
            'delete',
        ]);
    }
}
