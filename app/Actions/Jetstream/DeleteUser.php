<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        $user->deleteProfilePhoto();
        $user->tokens->each->delete();

        // Self-deletion: log the guard out before the row is gone, so
        // ModelActivityObserver's `deleted` hook (which reads Auth::id())
        // doesn't try to insert an activity_logs row referencing a
        // user_id that no longer exists (FK violation). DeleteUserForm
        // calls $auth->logout() again right after this returns, which is
        // a harmless no-op by then. An admin deleting someone else's
        // account is unaffected — Auth::id() still points at the
        // (different, still-valid) admin performing the action.
        if (Auth::check() && Auth::id() === $user->id) {
            Auth::logout();
        }

        $user->delete();
    }
}
