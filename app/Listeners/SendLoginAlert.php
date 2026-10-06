<?php

namespace App\Listeners;

use App\Notifications\LoginAlertNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendLoginAlert
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        try {
            $event->user->notify(new LoginAlertNotification);
        } catch (Throwable $e) {
            Log::warning('Login alert email failed: '.$e->getMessage());
        }
    }
}
