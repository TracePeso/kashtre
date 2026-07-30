<x-action-section>
    <x-slot name="title">
        {{ __('Security Questions') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Set up security questions as a backup when you cannot use your authenticator app at login.') }}
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
                {{ __('Your authenticator app remains the primary two-factor method. Security questions only help when you cannot access the app.') }}
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
                            <x-input
                                id="answer_{{ $index }}"
                                type="password"
                                class="mt-1 block w-full"
                                autocomplete="off"
                                wire:model="questions.{{ $index }}.answer"
                            />
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
