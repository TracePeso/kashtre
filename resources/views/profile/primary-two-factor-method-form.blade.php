<x-action-section>
    <x-slot name="title">
        {{ __('Primary Sign-In Method') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Choose which two-factor method is shown first when you sign in.') }}
    </x-slot>

    <x-slot name="content">
        <div class="max-w-xl text-sm text-gray-600 dark:text-gray-400">
            <p>
                {{ __('You can still switch to your other configured method on the sign-in screen.') }}
            </p>
        </div>

        <div class="mt-4 max-w-xl space-y-3">
            <label @class([
                'flex items-start gap-3 rounded-lg border p-4 cursor-pointer transition',
                'border-indigo-500 ring-1 ring-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/20' => $primaryMethod === 'authenticator',
                'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600' => $primaryMethod !== 'authenticator',
                'opacity-60 cursor-not-allowed' => ! $authenticatorEnabled,
            ])>
                <input
                    type="radio"
                    name="primary_two_factor_method"
                    value="authenticator"
                    wire:model.live="primaryMethod"
                    @disabled(! $authenticatorEnabled)
                    class="mt-1 border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:focus:ring-indigo-600"
                />
                <span class="flex-1">
                    <span class="flex items-center gap-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ __('Authenticator app') }}
                        @if ($currentPrimary === 'authenticator')
                            <span class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                {{ __('Primary') }}
                            </span>
                        @endif
                    </span>
                    <span class="mt-1 block text-sm text-gray-600 dark:text-gray-400">
                        @if ($authenticatorEnabled)
                            {{ __('Use a code from Google Authenticator or a similar app.') }}
                        @else
                            {{ __('Enable the authenticator app above to use this option.') }}
                        @endif
                    </span>
                </span>
            </label>

            <label @class([
                'flex items-start gap-3 rounded-lg border p-4 cursor-pointer transition',
                'border-indigo-500 ring-1 ring-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/20' => $primaryMethod === 'security_questions',
                'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600' => $primaryMethod !== 'security_questions',
                'opacity-60 cursor-not-allowed' => ! $securityQuestionsEnabled,
            ])>
                <input
                    type="radio"
                    name="primary_two_factor_method"
                    value="security_questions"
                    wire:model.live="primaryMethod"
                    @disabled(! $securityQuestionsEnabled)
                    class="mt-1 border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:focus:ring-indigo-600"
                />
                <span class="flex-1">
                    <span class="flex items-center gap-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ __('Security questions') }}
                        @if ($currentPrimary === 'security_questions')
                            <span class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                {{ __('Primary') }}
                            </span>
                        @endif
                    </span>
                    <span class="mt-1 block text-sm text-gray-600 dark:text-gray-400">
                        @if ($securityQuestionsEnabled)
                            {{ __('Answer two of your configured security questions at sign-in.') }}
                        @else
                            {{ __('Configure security questions below to use this option.') }}
                        @endif
                    </span>
                </span>
            </label>
        </div>

        <x-input-error for="primaryMethod" class="mt-3" />

        <div class="mt-5">
            <x-confirms-password wire:then="save">
                <x-button type="button" wire:loading.attr="disabled">
                    {{ __('Save Preference') }}
                </x-button>
            </x-confirms-password>
        </div>
    </x-slot>
</x-action-section>
