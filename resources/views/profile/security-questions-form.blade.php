<x-action-section>
    <x-slot name="title">
        {{ __('Security Questions') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Set up security questions as an alternate two-factor sign-in method.') }}
    </x-slot>

    <x-slot name="content">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            @if ($enabled)
                {{ __('Security questions are configured.') }}
            @else
                {{ __('Security questions are not configured.') }}
            @endif
        </h3>

        <div class="mt-3 max-w-xl text-sm text-gray-600 dark:text-gray-400">
            <p>
                {{ __('Choose :count unique questions and answers. At login you will be asked to answer :challenge of them if you use this backup method.', [
                    'count' => $requiredCount,
                    'challenge' => config('security_questions.challenge_count', 2),
                ]) }}
            </p>
            <p class="mt-2">
                {{ __('Configure both methods above, then choose your primary sign-in method in the section below.') }}
            </p>
        </div>

        @if ($enabled && ! $editing)
            <div class="mt-4 max-w-xl">
                <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                    @foreach ($configuredQuestions as $question)
                        <li class="rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2">
                            {{ $questionBank[$question->question_key] ?? $question->question_key }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($editing || ! $enabled)
            <div class="mt-4 max-w-xl space-y-4">
                @foreach ($questions as $index => $question)
                    <div class="rounded-md border border-gray-200 dark:border-gray-700 p-4 space-y-3">
                        <div>
                            <x-label for="question_key_{{ $index }}" value="{{ __('Question :number', ['number' => $index + 1]) }}" />
                            <select
                                id="question_key_{{ $index }}"
                                wire:model.live="questions.{{ $index }}.question_key"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            >
                                <option value="">{{ __('Select a question') }}</option>
                                @foreach ($questionBank as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error for="questions.{{ $index }}.question_key" class="mt-2" />
                        </div>

                        <div>
                            <x-label for="answer_{{ $index }}" value="{{ __('Answer') }}" />
                            <div class="relative mt-1" x-data="{ show: false }">
                                <x-input
                                    id="answer_{{ $index }}"
                                    type="password"
                                    class="block w-full pr-10"
                                    autocomplete="off"
                                    wire:model="questions.{{ $index }}.answer"
                                    x-bind:type="show ? 'text' : 'password'"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                    x-on:click="show = ! show"
                                    x-bind:aria-label="show ? '{{ __('Hide answer') }}' : '{{ __('Show answer') }}'"
                                >
                                    <svg x-show="! show" x-cloak class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                    <svg x-show="show" x-cloak class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                </button>
                            </div>
                            <x-input-error for="questions.{{ $index }}.answer" class="mt-2" />
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-5 flex flex-wrap items-center gap-3">
            @if (! $enabled || $editing)
                <x-confirms-password wire:then="save">
                    <x-button type="button" wire:loading.attr="disabled">
                        {{ $enabled ? __('Update Security Questions') : __('Save Security Questions') }}
                    </x-button>
                </x-confirms-password>

                @if ($enabled)
                    <x-secondary-button type="button" wire:click="cancelEditing">
                        {{ __('Cancel') }}
                    </x-secondary-button>
                @endif
            @else
                <x-confirms-password wire:then="startEditing">
                    <x-secondary-button type="button">
                        {{ __('Update Questions') }}
                    </x-secondary-button>
                </x-confirms-password>

                <x-confirms-password wire:then="disable">
                    <x-danger-button type="button" wire:loading.attr="disabled">
                        {{ __('Remove Security Questions') }}
                    </x-danger-button>
                </x-confirms-password>
            @endif
        </div>
    </x-slot>
</x-action-section>
