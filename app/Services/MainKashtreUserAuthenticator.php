<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MainKashtreUserAuthenticator
{
    public function attempt(string $email, string $password): ?User
    {
        $mainUser = DB::connection('kashtre')
            ->table('users')
            ->where('email', Str::lower($email))
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if (! $mainUser || ! Hash::check($password, $mainUser->password)) {
            return null;
        }

        return User::updateOrCreate(
            ['email' => $mainUser->email],
            [
                'name' => $mainUser->name,
                'staff_uuid' => $mainUser->uuid,
                'permissions' => User::filterHrPermissions($mainUser->permissions ?? []),
                'password' => $mainUser->password,
                'email_verified_at' => $mainUser->email_verified_at ?? now(),
            ]
        );
    }
}
