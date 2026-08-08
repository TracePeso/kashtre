<?php

namespace App\Observers;

use App\Jobs\SyncUserToHrModule;
use App\Models\User;

class UserHrSyncObserver
{
    public function created(User $user): void
    {
        $this->dispatch($user);
    }

    public function updated(User $user): void
    {
        $this->dispatch($user);
    }

    private function dispatch(User $user): void
    {
        if ((int) $user->business_id === 1) {
            return;
        }

        SyncUserToHrModule::dispatch($user->id)->afterResponse();
    }
}
