<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\ConfirmsPasswords;
use Livewire\Component;

class PrimaryTwoFactorMethodForm extends Component
{
    use ConfirmsPasswords;

    public string $primaryMethod = 'authenticator';

    public function mount(): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $this->primaryMethod = $user->effectivePrimaryTwoFactorMethod();
    }

    public function save(): void
    {
        $this->ensurePasswordIsConfirmed();

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $this->validate([
            'primaryMethod' => ['required', Rule::in(['authenticator', 'security_questions'])],
        ]);

        if ($this->primaryMethod === 'authenticator' && ! $user->hasAuthenticatorConfigured()) {
            throw ValidationException::withMessages([
                'primaryMethod' => [__('Enable the authenticator app before setting it as your primary sign-in method.')],
            ]);
        }

        if ($this->primaryMethod === 'security_questions' && ! $user->hasSecurityQuestionsConfigured()) {
            throw ValidationException::withMessages([
                'primaryMethod' => [__('Configure security questions before setting them as your primary sign-in method.')],
            ]);
        }

        $user->forceFill([
            'primary_two_factor_method' => $this->primaryMethod,
        ])->save();

        $this->dispatch('saved');
    }

    public function render()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return view('profile.primary-two-factor-method-form', [
            'authenticatorEnabled' => $user->hasAuthenticatorConfigured(),
            'securityQuestionsEnabled' => $user->hasSecurityQuestionsConfigured(),
            'currentPrimary' => $user->effectivePrimaryTwoFactorMethod(),
        ]);
    }
}
