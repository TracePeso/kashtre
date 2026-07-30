<x-authentication-layout>
    <h1 class="text-3xl text-gray-800 dark:text-gray-100 font-bold mb-6">{{ __('Confirm access') }}</h1>
    <div x-data="{ mode: 'code' }">
        <div class="mb-4" x-show="mode === 'code'">
            {{ __('Please confirm access to your account by entering the authentication code provided by your authenticator application.') }}
        </div>

        <div class="mb-4" x-show="mode === 'recovery'">
            {{ __('Please confirm access to your account by entering one of your emergency recovery codes.') }}
        </div>

        @if ($canUseSecurityQuestions ?? false)
            <div class="mb-4" x-show="mode === 'security'" x-cloak>
                {{ __('Answer your security questions to sign in when you cannot use your authenticator app.') }}
            </div>
        @endif

        <x-validation-errors class="mb-4" />

        <form
            method="POST"
            action="{{ ($canUseSecurityQuestions ?? false) ? '#' : route('two-factor.login') }}"
            x-bind:action="mode === 'security' ? '{{ route('two-factor.security-questions') }}' : '{{ route('two-factor.login') }}'"
        >
            @csrf
            <div class="space-y-4">
                <div x-show="mode === 'code'">
                    <x-label for="code" value="{{ __('Code') }}" />
                    <x-input id="code" type="text" inputmode="numeric" name="code" autofocus x-ref="code" autocomplete="one-time-code" />
                </div>

                <div x-show="mode === 'recovery'">
                    <x-label for="recovery_code" value="{{ __('Recovery Code') }}" />
                    <x-input id="recovery_code" type="text" name="recovery_code" x-ref="recovery_code" autocomplete="one-time-code" />
                </div>

                @if ($canUseSecurityQuestions ?? false)
                    <div x-show="mode === 'security'" x-cloak class="space-y-4">
                        @foreach ($challengeQuestions ?? [] as $question)
                            <div>
                                <x-label for="security_answer_{{ $question['key'] }}" value="{{ $question['label'] }}" />
                                <x-input
                                    id="security_answer_{{ $question['key'] }}"
                                    type="password"
                                    name="security_answers[{{ $question['key'] }}]"
                                    x-ref="security_answer_{{ $question['key'] }}"
                                    autocomplete="off"
                                />
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex flex-col gap-3 mt-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col gap-2 text-sm">
                    <button
                        type="button"
                        class="text-left underline hover:no-underline"
                        x-show="mode === 'code'"
                        x-on:click="
                            mode = 'recovery';
                            $nextTick(() => { $refs.recovery_code.focus() })
                        "
                    >
                        {{ __('Use a recovery code') }}
                    </button>

                    @if ($canUseSecurityQuestions ?? false)
                        <button
                            type="button"
                            class="text-left underline hover:no-underline"
                            x-show="mode === 'code'"
                            x-on:click="
                                mode = 'security';
                                $nextTick(() => {
                                    const first = document.querySelector('[id^=security_answer_]');
                                    if (first) first.focus();
                                })
                            "
                        >
                            {{ __('Use security questions') }}
                        </button>
                    @endif

                    <button
                        type="button"
                        class="text-left text-gray-600 hover:text-gray-900 underline cursor-pointer"
                        x-show="mode !== 'code'"
                        x-on:click="
                            mode = 'code';
                            $nextTick(() => { $refs.code.focus() })
                        "
                    >
                        {{ __('Use an authentication code') }}
                    </button>
                </div>

                <x-button class="sm:ml-4">
                    {{ __('Log in') }}
                </x-button>
            </div>
        </form>
    </div>
</x-authentication-layout>
