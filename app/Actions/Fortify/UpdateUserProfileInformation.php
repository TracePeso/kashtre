<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        $input['presentation_timezone'] = trim((string) ($input['presentation_timezone'] ?? '')) ?: null;

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
            'presentation_timezone' => ['nullable', 'string', 'max:64', Rule::in(timezone_identifiers_list())],
        ])->validateWithBag('updateProfileInformation');

        if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
        }

        $presentationTimezone = $input['presentation_timezone'] ?? null;

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input, $presentationTimezone);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
                'presentation_timezone' => $presentationTimezone,
            ])->save();
        }

        if ($presentationTimezone && $user->business_id && (bool) config('time.enabled')) {
            try {
                $time = app(\App\Domain\Time\Services\SharedTimeGateway::class);
                $settings = $time->tenantSettings((string) $user->business_id);
                if ($settings->allow_user_presentation_timezone) {
                    $time->setScopeTimezone(
                        (string) $user->business_id,
                        \App\Domain\Time\Enums\PolicyScopeType::USER,
                        (string) $user->id,
                        $presentationTimezone,
                        \App\Domain\Time\Enums\PolicyPurpose::PRESENTATION,
                        'User profile presentation timezone',
                    );
                }
            } catch (\Throwable) {
                // Profile save should not fail if Time Engine is unavailable.
            }
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input, ?string $presentationTimezone = null): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'presentation_timezone' => $presentationTimezone,
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
