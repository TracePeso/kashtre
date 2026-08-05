<x-authentication-layout>
    @php
        $canUseSecurityQuestions = (bool) ($canUseSecurityQuestions ?? false);
        $challengeQuestions = $challengeQuestions ?? [];
        $challengeMode = $challengeMode ?? 'code';
        if (! in_array($challengeMode, ['code', 'recovery', 'security'], true)) {
            $challengeMode = 'code';
        }
        if ($challengeMode === 'security' && ! $canUseSecurityQuestions) {
            $challengeMode = 'code';
        }

        $switchTo = fn (string $mode) => route('two-factor.login', ['mode' => $mode]);
    @endphp

    <h1 class="text-3xl text-gray-800 dark:text-gray-100 font-bold mb-6">{{ __('Confirm access') }}</h1>

    @if ($challengeMode === 'code')
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Please confirm access to your account by entering the authentication code provided by your authenticator application.') }}
        </p>
    @elseif ($challengeMode === 'recovery')
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Please confirm access to your account by entering one of your emergency recovery codes.') }}
        </p>
    @elseif ($challengeMode === 'security')
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Answer your security questions to finish signing in.') }}
        </p>
    @endif

    <x-validation-errors class="mb-4" />

    <form
        method="POST"
        action="{{ $challengeMode === 'security' ? route('two-factor.security-questions') : route('two-factor.login') }}"
        class="space-y-6"
    >
        @csrf

        @if ($challengeMode === 'code')
            <div>
                <x-label for="code" value="{{ __('Code') }}" />
                <input
                    id="code"
                    type="text"
                    inputmode="numeric"
                    name="code"
                    autofocus
                    autocomplete="one-time-code"
                    class="form-input w-full mt-1"
                />
            </div>
        @elseif ($challengeMode === 'recovery')
            <div>
                <x-label for="recovery_code" value="{{ __('Recovery Code') }}" />
                <input
                    id="recovery_code"
                    type="text"
                    name="recovery_code"
                    autofocus
                    autocomplete="one-time-code"
                    class="form-input w-full mt-1"
                />
            </div>
        @elseif ($challengeMode === 'security')
            <div class="space-y-5">
                @forelse ($challengeQuestions as $question)
                    <div>
                        <x-label
                            for="security_answer_{{ $question['key'] }}"
                            value="{{ $question['label'] }}"
                        />
                        <input
                            id="security_answer_{{ $question['key'] }}"
                            type="password"
                            name="security_answers[{{ $question['key'] }}]"
                            autocomplete="off"
                            @if ($loop->first) autofocus @endif
                            class="form-input block w-full mt-1"
                            placeholder="{{ __('Your answer') }}"
                        />
                    </div>
                @empty
                    <p class="text-sm text-red-600">
                        {{ __('Security questions are enabled, but no challenge questions could be loaded. Please contact support or use your authenticator app.') }}
                    </p>
                @endforelse
            </div>
        @endif

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between pt-1">
            <div class="flex flex-col gap-2 text-sm">
                @if ($challengeMode === 'code')
                    <a href="{{ $switchTo('recovery') }}" class="text-blue-600 dark:text-blue-400 underline hover:no-underline">
                        {{ __('Use a recovery code') }}
                    </a>

                    @if ($canUseSecurityQuestions)
                        <a href="{{ $switchTo('security') }}" class="text-blue-600 dark:text-blue-400 underline hover:no-underline">
                            {{ __('Use security questions') }}
                        </a>
                    @endif
                @elseif ($challengeMode === 'recovery')
                    <a href="{{ $switchTo('code') }}" class="text-blue-600 dark:text-blue-400 underline hover:no-underline">
                        {{ __('Use an authentication code') }}
                    </a>

                    @if ($canUseSecurityQuestions)
                        <a href="{{ $switchTo('security') }}" class="text-blue-600 dark:text-blue-400 underline hover:no-underline">
                            {{ __('Use security questions') }}
                        </a>
                    @endif
                @elseif ($challengeMode === 'security')
                    <a href="{{ $switchTo('code') }}" class="text-blue-600 dark:text-blue-400 underline hover:no-underline">
                        {{ __('Use an authentication code') }}
                    </a>

                    <a href="{{ $switchTo('recovery') }}" class="text-blue-600 dark:text-blue-400 underline hover:no-underline">
                        {{ __('Use a recovery code') }}
                    </a>
                @endif
            </div>

            <x-button class="w-full sm:w-auto justify-center sm:ml-4">
                {{ __('Log in') }}
            </x-button>
        </div>
    </form>
</x-authentication-layout>
