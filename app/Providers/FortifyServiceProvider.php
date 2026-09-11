<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Controllers\Auth\SecurityQuestionLoginController;
use App\Http\Middleware\NormalizeTwoFactorChallengeInput;
use App\Http\Responses\FailedTwoFactorLoginResponse as AppFailedTwoFactorLoginResponse;
use App\Http\Responses\TwoFactorChallengeViewResponse as AppTwoFactorChallengeViewResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedTwoFactorLoginResponse;
use Laravel\Fortify\Contracts\TwoFactorChallengeViewResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
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
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Jetstream's boot() calls Fortify::viewPrefix('auth.'), which binds a
        // SimpleViewResponse and would otherwise wipe security-question data.
        // Re-bind after that by registering in boot (app providers boot after
        // package providers).
        $this->app->singleton(TwoFactorChallengeViewResponse::class, AppTwoFactorChallengeViewResponse::class);
        $this->app->singleton(FailedTwoFactorLoginResponse::class, AppFailedTwoFactorLoginResponse::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('security-questions', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id').'|'.$request->ip());
        });

        Route::middleware(config('fortify.middleware', ['web']))->group(function () {
            Route::post('/user/two-factor-security-questions', [SecurityQuestionLoginController::class, 'store'])
                ->middleware(['throttle:security-questions', NormalizeTwoFactorChallengeInput::class])
                ->name('two-factor.security-questions');
        });
    }
}
